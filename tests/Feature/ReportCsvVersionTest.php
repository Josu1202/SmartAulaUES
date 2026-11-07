<?php

namespace Tests\Feature;

use App\Actions\Attendance\ClosureWorkflow;
use App\Jobs\GenerateReportCsv;
use App\Models\AssessmentPeriod;
use App\Models\Enrollment;
use App\Models\ReportVersion;
use App\Models\User;
use App\Services\Reports\VersionedCsvExporter;
use App\Services\Reports\VersionedPdfExporter;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class ReportCsvVersionTest extends TestCase
{
    private function asUser(User $user): static
    {
        return $this->withSession(['password_hash_web' => $user->password])->actingAs($user);
    }

    private function closedScenario(?string $studentName = null): array
    {
        config()->set('queue.default', 'database');
        $this->travelTo(now()->setDateTime(2026, 10, 23, 12, 0, 0));
        $period = AssessmentPeriod::factory()->create(['starts_on' => '2026-10-01', 'ends_on' => '2026-10-22']);
        $period->offering->cycle->update(['ends_on' => '2026-10-22']);
        $enrollment = Enrollment::factory()->create(['offering_id' => $period->offering_id]);
        if ($studentName !== null) {
            $enrollment->student->update(['name' => $studentName]);
        }
        $coordinator = User::factory()->coordinator()->create();
        app(ClosureWorkflow::class)->closePeriod($coordinator, $period);
        $period->refresh();
        $this->assertSame('closed', $period->state);

        return [$period, ReportVersion::findOrFail($period->current_report_id), $coordinator, $period->offering->teacher];
    }

    private function csvRows(string $content): array
    {
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $stream = fopen('php://temp', 'w+');
        fwrite($stream, substr($content, 3));
        rewind($stream);
        $rows = [];
        while (($row = fgetcsv($stream, 0, ',', '"', '')) !== false) {
            $rows[] = $row;
        }
        fclose($stream);

        return $rows;
    }

    public function test_teacher_and_coordinator_can_request_csv_but_not_download_unfinished_file(): void
    {
        [, $report, $coordinator, $teacher] = $this->closedScenario();
        Queue::fake();
        $this->asUser($teacher)->get(route('reports.index'))->assertOk()->assertSee('Solicitar CSV');
        $this->post(route('reports.csv.generate', $report))->assertRedirect(route('reports.index'));
        Queue::assertPushed(GenerateReportCsv::class, fn (GenerateReportCsv $job) => $job->reportId === $report->id && $job->actorId === $teacher->id && $job->queue === 'reports');
        $this->get(route('reports.csv.download', $report))->assertNotFound();
        $this->asUser($coordinator)->post(route('reports.csv.generate', $report))->assertRedirect(route('reports.index'));
        Queue::assertPushed(GenerateReportCsv::class, 2);
        $this->assertNull($report->fresh()->csv_path);
    }

    public function test_sync_queue_is_rejected_for_csv(): void
    {
        [, $report, , $teacher] = $this->closedScenario();
        Queue::fake();
        config()->set('queue.default', 'sync');
        $this->asUser($teacher)->post(route('reports.csv.generate', $report))->assertStatus(503);
        Queue::assertNothingPushed();
    }

    public function test_foreign_teacher_and_student_cannot_request_or_download_collective_csv(): void
    {
        [, $report] = $this->closedScenario();
        Queue::fake();
        $otherTeacher = User::factory()->teacher()->create();
        $this->asUser($otherTeacher)->get(route('reports.index'))->assertOk()->assertDontSee('Solicitar CSV');
        $this->post(route('reports.csv.generate', $report))->assertForbidden();
        $this->get(route('reports.csv.download', $report))->assertForbidden();

        $student = User::factory()->create();
        $this->asUser($student)->get(route('reports.index'))->assertForbidden();
        $this->post(route('reports.csv.generate', $report))->assertForbidden();
        $this->get(route('reports.csv.download', $report))->assertForbidden();
        Queue::assertNothingPushed();
    }

    public function test_worker_revalidates_actor_at_execution_time(): void
    {
        [, $report, , $teacher] = $this->closedScenario();
        Storage::fake('private');
        $teacher->update(['active' => false]);
        $this->expectException(AuthorizationException::class);
        (new GenerateReportCsv($report->id, $teacher->id))->handle(app(VersionedCsvExporter::class));
    }

    public function test_csv_uses_snapshot_data_is_private_hashed_and_idempotent(): void
    {
        [, $report, , $teacher] = $this->closedScenario();
        Storage::fake('private');
        $exporter = app(VersionedCsvExporter::class);
        $job = new GenerateReportCsv($report->id, $teacher->id);
        $job->handle($exporter);
        $report->refresh();
        $this->assertSame($exporter->expectedPath($report), $report->csv_path);
        $this->assertSame(64, strlen($report->csv_hash));
        $this->assertNull($report->pdf_path);
        $this->assertNull($report->file_hash);
        $content = Storage::disk('private')->get($report->csv_path);
        $this->assertSame(hash('sha256', $content), $report->csv_hash);
        $this->assertTrue($exporter->fileIsValid($report));

        $rows = $this->csvRows($content);
        $this->assertCount(2, $rows);
        $this->assertCount(16, $rows[0]);
        $this->assertSame('Estudiante', $rows[0][8]);
        $this->assertSame((string) $report->period_id, $rows[1][0]);
        $this->assertSame((string) $report->version, $rows[1][1]);
        $this->assertSame($report->snapshot['rows'][0]['student_name'], $rows[1][8]);
        $this->assertSame((string) ($report->snapshot['rows'][0]['calculation']['percentage'] ?? ''), $rows[1][14]);
        $this->asUser($teacher)->get(route('reports.csv.download', $report))
            ->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $job->handle($exporter);
        $this->assertSame($content, Storage::disk('private')->get($report->csv_path));
        $this->assertSame($report->csv_hash, $report->fresh()->csv_hash);
    }

    public function test_spreadsheet_formula_with_whitespace_and_commas_is_neutralized(): void
    {
        $unsafeName = "\t=HYPERLINK(\"https://example.invalid\",\"Abrir\")";
        [, $report, , $teacher] = $this->closedScenario($unsafeName);
        Storage::fake('private');
        (new GenerateReportCsv($report->id, $teacher->id))->handle(app(VersionedCsvExporter::class));
        $rows = $this->csvRows(Storage::disk('private')->get($report->fresh()->csv_path));
        $this->assertSame($unsafeName, $report->snapshot['rows'][0]['student_name']);
        $this->assertSame("'".$unsafeName, $rows[1][8]);
        $this->assertCount(16, $rows[1]);
    }

    public function test_tampered_snapshot_or_csv_blocks_generation_and_download(): void
    {
        [, $report, , $teacher] = $this->closedScenario();
        Storage::fake('private');
        $exporter = app(VersionedCsvExporter::class);
        $job = new GenerateReportCsv($report->id, $teacher->id);
        $job->handle($exporter);
        $hash = $report->fresh()->csv_hash;
        Storage::disk('private')->put($exporter->expectedPath($report), "archivo modificado\n");
        $this->asUser($teacher)->get(route('reports.csv.download', $report))->assertNotFound();
        try {
            $job->handle($exporter);
            $this->fail('Se aceptó un CSV alterado.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('integridad', $exception->getMessage());
        }
        $this->assertSame($hash, $report->fresh()->csv_hash);

        DB::table('report_versions')->where('id', $report->id)->update(['snapshot_hash' => str_repeat('0', 64)]);
        $this->post(route('reports.csv.generate', $report))->assertStatus(409);
        $this->get(route('reports.csv.download', $report))->assertStatus(409);
    }

    public function test_csv_and_pdf_metadata_remain_independent(): void
    {
        [, $report, , $teacher] = $this->closedScenario();
        Storage::fake('private');
        app(VersionedPdfExporter::class)->generate($report);
        $report->refresh();
        $pdfPath = $report->pdf_path;
        $pdfHash = $report->file_hash;
        (new GenerateReportCsv($report->id, $teacher->id))->handle(app(VersionedCsvExporter::class));
        $report->refresh();
        $this->assertSame($pdfPath, $report->pdf_path);
        $this->assertSame($pdfHash, $report->file_hash);
        $this->assertTrue(app(VersionedPdfExporter::class)->fileIsValid($report));
        $this->assertTrue(app(VersionedCsvExporter::class)->fileIsValid($report));
    }

    public function test_historical_csv_remains_accessible_after_reopen_and_new_closure(): void
    {
        [$period, $report, $coordinator, $teacher] = $this->closedScenario();
        Storage::fake('private');
        (new GenerateReportCsv($report->id, $teacher->id))->handle(app(VersionedCsvExporter::class));
        $oldHash = $report->fresh()->csv_hash;

        app(ClosureWorkflow::class)->reopenPeriod($coordinator, $period, 'Revisión académica de resultados');
        app(ClosureWorkflow::class)->closePeriod($coordinator, $period);
        $this->assertSame(2, $period->fresh()->reports()->count());
        $this->assertNotEquals($report->id, $period->fresh()->current_report_id);
        $this->assertSame($oldHash, $report->fresh()->csv_hash);
        $this->asUser($teacher)->get(route('reports.csv.download', $report))->assertOk();
    }
}
