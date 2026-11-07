<?php

namespace Tests\Feature;

use App\Actions\Attendance\ClosureWorkflow;
use App\Jobs\GenerateReportCsv;
use App\Jobs\GenerateReportPdf;
use App\Models\AssessmentPeriod;
use App\Models\Enrollment;
use App\Models\ReportVersion;
use App\Models\User;
use App\Notifications\AcademicNotice;
use App\Services\Reports\ReportGenerationNotifier;
use App\Services\Reports\VersionedCsvExporter;
use App\Services\Reports\VersionedPdfExporter;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class ReportGenerationNotificationTest extends TestCase
{
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

        return [ReportVersion::findOrFail($period->current_report_id), $coordinator, $period->offering->teacher];
    }

    private function asUser(User $actor): static
    {
        return $this->withSession(['password_hash_web' => $actor->password])->actingAs($actor);
    }

    public function test_pdf_and_csv_jobs_notify_requester_once_and_preserve_private_links(): void
    {
        [$report, , $teacher] = $this->closedScenario();
        Storage::fake('private');
        $pdfJob = new GenerateReportPdf($report->id, $teacher->id);
        $csvJob = new GenerateReportCsv($report->id, $teacher->id);
        $pdfJob->handle(app(VersionedPdfExporter::class));
        $pdfJob->handle(app(VersionedPdfExporter::class));
        $csvJob->handle(app(VersionedCsvExporter::class));
        $csvJob->handle(app(VersionedCsvExporter::class));

        $notices = $teacher->notifications()->get();
        $this->assertCount(2, $notices);
        $this->assertSame([AcademicNotice::class], $notices->pluck('type')->unique()->values()->all());
        $this->assertEqualsCanonicalizing([
            route('reports.download', $report, absolute: false),
            route('reports.csv.download', $report, absolute: false),
        ], $notices->pluck('data')->map(fn (array $data) => $data['url'])->all());
        $this->assertSame(0, $notices->filter(fn ($notice) => str_contains($notice->data['url'], '/storage/'))->count());
        $this->assertSame(0, $notices->filter(fn ($notice) => $notice->read_at !== null)->count());
        $this->asUser($teacher)->get(route('notifications.index'))->assertOk()->assertSee('Reporte PDF disponible')->assertSee('Reporte CSV disponible');
    }

    public function test_coordinator_has_independent_notice_and_read_status_remains_owned(): void
    {
        [$report, $coordinator, $teacher] = $this->closedScenario();
        Storage::fake('private');
        $pdfJob = new GenerateReportPdf($report->id, $teacher->id);
        $pdfJob->handle(app(VersionedPdfExporter::class));
        (new GenerateReportPdf($report->id, $coordinator->id))->handle(app(VersionedPdfExporter::class));
        $this->assertCount(1, $teacher->notifications);
        $this->assertCount(1, $coordinator->notifications);
        $notice = $teacher->notifications()->firstOrFail();
        $this->asUser($coordinator)->post(route('notifications.read', $notice->id))->assertNotFound();
        $this->asUser($teacher)->post(route('notifications.read', $notice->id))->assertRedirect(route('notifications.index'));
        $this->assertNotNull($notice->fresh()->read_at);
        $this->assertNull($coordinator->notifications()->firstOrFail()->read_at);
    }

    public function test_failure_notice_is_deduplicated_and_does_not_expose_exception_message(): void
    {
        [$report, , $teacher] = $this->closedScenario();
        $sensitiveError = new RuntimeException('Detalle secreto de infraestructura');
        $job = new GenerateReportPdf($report->id, $teacher->id);
        $job->failed($sensitiveError);
        $job->failed($sensitiveError);
        $notices = $teacher->notifications()->get();
        $this->assertCount(1, $notices);
        $this->assertStringContainsString('No se pudo generar', $notices->first()->data['title']);
        $this->assertStringNotContainsString('Detalle secreto', json_encode($notices->first()->data));
        $this->assertSame(route('reports.index', absolute: false), $notices->first()->data['url']);
        $this->assertNull($report->fresh()->pdf_path);
    }

    public function test_unauthorized_or_inactive_user_receives_no_notice(): void
    {
        [$report, , $teacher] = $this->closedScenario();
        $outsider = User::factory()->teacher()->create();
        $notifier = app(ReportGenerationNotifier::class);
        $notifier->ready($report->id, $outsider->id, 'PDF');
        $notifier->failed($report->id, $outsider->id, 'PDF');
        $teacher->update(['active' => false]);
        $notifier->ready($report->id, $teacher->id, 'CSV');
        $notifier->failed($report->id, $teacher->id, 'CSV');
        $this->assertSame(0, $outsider->notifications()->count());
        $this->assertSame(0, $teacher->notifications()->count());
    }

    public function test_success_after_failure_adds_one_ready_notice_without_overwriting_failure(): void
    {
        [$report, , $teacher] = $this->closedScenario();
        Storage::fake('private');
        $job = new GenerateReportCsv($report->id, $teacher->id);
        $job->failed(new RuntimeException('Error de prueba'));
        $job->handle(app(VersionedCsvExporter::class));
        $job->handle(app(VersionedCsvExporter::class));
        $this->assertSame(2, $teacher->notifications()->count());
        $this->assertTrue(app(VersionedCsvExporter::class)->fileIsValid($report->fresh()));
    }
}
