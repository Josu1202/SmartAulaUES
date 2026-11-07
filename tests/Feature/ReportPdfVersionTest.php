<?php

namespace Tests\Feature;

use App\Actions\Attendance\ClosureWorkflow;
use App\Jobs\GenerateReportPdf;
use App\Models\AssessmentPeriod;
use App\Models\Enrollment;
use App\Models\ReportVersion;
use App\Models\User;
use App\Services\Reports\VersionedPdfExporter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class ReportPdfVersionTest extends TestCase
{
    private function asUser(User $user): static
    {
        return $this->withSession(['password_hash_web' => $user->password])->actingAs($user);
    }

    private function closedScenario(): array
    {
        config()->set('queue.default', 'database');
        $this->travelTo(now()->setDateTime(2026, 10, 23, 12, 0, 0));
        $period = AssessmentPeriod::factory()->create(['starts_on' => '2026-10-01', 'ends_on' => '2026-10-22']);
        $period->offering->cycle->update(['ends_on' => '2026-10-22']);
        Enrollment::factory()->create(['offering_id' => $period->offering_id]);
        $coordinator = User::factory()->coordinator()->create();
        app(ClosureWorkflow::class)->closePeriod($coordinator, $period);
        $period->refresh();
        $this->assertSame('closed', $period->state);

        return [$period, ReportVersion::findOrFail($period->current_report_id), $coordinator, $period->offering->teacher];
    }

    public function test_teacher_and_coordinator_can_request_pdf_but_not_download_unfinished_file(): void
    {
        [$period, $report, $coordinator, $teacher] = $this->closedScenario();
        Queue::fake();
        $this->asUser($teacher)->get(route('reports.index'))->assertOk()->assertSee('Versiones certificadas');
        $this->post(route('reports.generate', $report))->assertRedirect(route('reports.index'));
        Queue::assertPushed(GenerateReportPdf::class, fn (GenerateReportPdf $job) => $job->reportId === $report->id && $job->actorId === $teacher->id && $job->queue === 'reports');
        $this->get(route('reports.download', $report))->assertNotFound();
        $this->asUser($coordinator)->get(route('reports.index'))->assertOk();
        $this->post(route('reports.generate', $report))->assertRedirect(route('reports.index'));
        Queue::assertPushed(GenerateReportPdf::class, 2);
        $this->assertNull($period->fresh()->reports()->first()->pdf_path);
    }

    public function test_sync_queue_is_rejected_before_doing_synchronous_pdf_work(): void
    {
        [, $report, , $teacher] = $this->closedScenario();
        Queue::fake();
        config()->set('queue.default', 'sync');
        $this->asUser($teacher)->post(route('reports.generate', $report))->assertStatus(503);
        Queue::assertNothingPushed();
    }

    public function test_foreign_teacher_student_and_inactive_account_cannot_access_a_collective_report(): void
    {
        [, $report] = $this->closedScenario();
        Queue::fake();
        $outsider = User::factory()->teacher()->create();
        $this->asUser($outsider)->get(route('reports.index'))->assertOk()->assertDontSee('Solicitar PDF');
        $this->post(route('reports.generate', $report))->assertForbidden();
        $this->get(route('reports.download', $report))->assertForbidden();

        $student = User::factory()->create();
        $this->asUser($student)->get(route('reports.index'))->assertForbidden();
        $this->post(route('reports.generate', $report))->assertForbidden();
        $this->get(route('reports.download', $report))->assertForbidden();
        Queue::assertNothingPushed();
    }

    public function test_job_generates_private_pdf_and_retry_reuses_exact_file_and_hash(): void
    {
        [, $report, , $teacher] = $this->closedScenario();
        Storage::fake('private');
        $exporter = app(VersionedPdfExporter::class);
        $job = new GenerateReportPdf($report->id, $teacher->id);
        $job->handle($exporter);
        $report->refresh();
        $this->assertSame($exporter->expectedPath($report), $report->pdf_path);
        $this->assertSame(64, strlen($report->file_hash));
        $content = Storage::disk('private')->get($report->pdf_path);
        $this->assertStringStartsWith('%PDF-', $content);
        $this->assertSame(hash('sha256', $content), $report->file_hash);
        $this->assertTrue($exporter->fileIsValid($report));
        $this->asUser($teacher)->get(route('reports.download', $report))->assertOk()->assertHeader('content-type', 'application/pdf');

        $job->handle($exporter);
        $this->assertSame($content, Storage::disk('private')->get($report->pdf_path));
        $this->assertSame($report->file_hash, $report->fresh()->file_hash);
    }

    public function test_tampered_snapshot_is_rejected_without_creating_a_pdf(): void
    {
        [, $report, , $teacher] = $this->closedScenario();
        Storage::fake('private');
        DB::table('report_versions')->where('id', $report->id)->update(['snapshot_hash' => str_repeat('0', 64)]);
        $this->asUser($teacher)->post(route('reports.generate', $report))->assertStatus(409);
        try {
            (new GenerateReportPdf($report->id, $teacher->id))->handle(app(VersionedPdfExporter::class));
            $this->fail('El exportador aceptó un snapshot alterado.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('integridad', $exception->getMessage());
        }
        $this->assertFalse(Storage::disk('private')->exists(app(VersionedPdfExporter::class)->expectedPath($report)));
    }

    public function test_historical_pdf_remains_available_after_reopening_and_reclosing(): void
    {
        [$period, $report, $coordinator, $teacher] = $this->closedScenario();
        Storage::fake('private');
        $exporter = app(VersionedPdfExporter::class);
        (new GenerateReportPdf($report->id, $teacher->id))->handle($exporter);
        $oldPath = $report->fresh()->pdf_path;
        $oldHash = $report->fresh()->file_hash;

        app(ClosureWorkflow::class)->reopenPeriod($coordinator, $period, 'Revisión académica de resultados');
        app(ClosureWorkflow::class)->closePeriod($coordinator, $period);
        $this->assertSame(2, $period->fresh()->reports()->count());
        $this->assertNotEquals($report->id, $period->fresh()->current_report_id);
        $this->assertTrue(Storage::disk('private')->exists($oldPath));
        $this->assertSame($oldHash, $report->fresh()->file_hash);
        $this->asUser($teacher)->get(route('reports.download', $report))->assertOk();
    }
}
