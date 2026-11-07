<?php

namespace Tests\Feature;

use App\Actions\Attendance\ClosureWorkflow;
use App\Models\AssessmentPeriod;
use App\Models\Enrollment;
use App\Models\ReportExportRequest;
use App\Models\ReportVersion;
use App\Models\User;
use App\Services\Reports\ReportExportLifecycle;
use Tests\TestCase;

class ReportExportLifecycleTest extends TestCase
{
    private function scenario(): array
    {
        config()->set('queue.default', 'database');

        $this->travelTo(now()->setDateTime(2026, 10, 23, 12, 0, 0));

        $period = AssessmentPeriod::factory()->create([
            'starts_on' => '2026-10-01',
            'ends_on' => '2026-10-22',
        ]);

        $period->offering->cycle->update([
            'ends_on' => '2026-10-22',
        ]);

        Enrollment::factory()->create([
            'offering_id' => $period->offering_id,
        ]);

        $coordinator = User::factory()->coordinator()->create();

        app(ClosureWorkflow::class)->closePeriod($coordinator, $period);

        $period->refresh();

        return [
            ReportVersion::findOrFail($period->current_report_id),
            $period->offering->teacher,
        ];
    }

    public function test_repeated_request_does_not_create_another_active_request(): void
    {
        [$report, $teacher] = $this->scenario();

        $lifecycle = app(ReportExportLifecycle::class);

        $token = $lifecycle->request($report, $teacher, 'PDF');

        $this->assertNotNull($token);

        $this->assertNull(
            $lifecycle->request($report, $teacher, 'PDF')
        );

        $record = ReportExportRequest::query()->sole();

        $this->assertSame('PDF', $record->format);
        $this->assertSame('pending', $record->state);
        $this->assertSame($token, $record->request_token);
        $this->assertSame(0, $record->attempts);
        $this->assertSame($teacher->id, $record->requested_by);
        $this->assertSame($report->id, $record->report_version_id);
    }

    public function test_only_current_token_can_start_and_finish_a_request(): void
    {
        [$report, $teacher] = $this->scenario();

        $lifecycle = app(ReportExportLifecycle::class);

        $token = $lifecycle->request($report, $teacher, 'PDF');

        $this->assertFalse(
            $lifecycle->started($report->id, $teacher->id, 'PDF', 'invalid-token')
        );

        $this->assertTrue(
            $lifecycle->started($report->id, $teacher->id, 'PDF', $token)
        );

        $record = ReportExportRequest::query()->sole();

        $this->assertSame('processing', $record->state);
        $this->assertSame(1, $record->attempts);
        $this->assertNotNull($record->lease_expires_at);

        $this->assertTrue(
            $lifecycle->succeeded($report->id, $teacher->id, 'PDF', $token)
        );

        $this->assertSame(
            'available',
            $record->fresh()->state
        );

        $this->assertFalse(
            $lifecycle->failed($report->id, $teacher->id, 'PDF', $token)
        );
    }

    public function test_expired_pending_request_gets_new_token(): void
    {
        [$report, $teacher] = $this->scenario();

        $lifecycle = app(ReportExportLifecycle::class);

        $oldToken = $lifecycle->request($report, $teacher, 'PDF');

        $this->travel(11)->minutes();

        $newToken = $lifecycle->request($report, $teacher, 'PDF');

        $this->assertNotNull($newToken);
        $this->assertNotSame($oldToken, $newToken);

        $this->assertFalse(
            $lifecycle->started($report->id, $teacher->id, 'PDF', $oldToken)
        );

        $this->assertTrue(
            $lifecycle->started($report->id, $teacher->id, 'PDF', $newToken)
        );

        $this->assertSame(1, ReportExportRequest::query()->count());
    }

    public function test_expired_processing_lease_allows_recovery(): void
    {
        [$report, $teacher] = $this->scenario();

        $lifecycle = app(ReportExportLifecycle::class);

        $oldToken = $lifecycle->request($report, $teacher, 'PDF');

        $this->assertTrue(
            $lifecycle->started($report->id, $teacher->id, 'PDF', $oldToken)
        );

        $this->travel(4)->minutes();

        $newToken = $lifecycle->request($report, $teacher, 'PDF');

        $this->assertNotNull($newToken);
        $this->assertNotSame($oldToken, $newToken);

        $this->assertFalse(
            $lifecycle->succeeded($report->id, $teacher->id, 'PDF', $oldToken)
        );

        $this->assertTrue(
            $lifecycle->started($report->id, $teacher->id, 'PDF', $newToken)
        );

        $this->assertTrue(
            $lifecycle->succeeded($report->id, $teacher->id, 'PDF', $newToken)
        );

        $this->assertSame(
            'available',
            ReportExportRequest::query()->sole()->state
        );
    }

    public function test_failed_pdf_can_retry_without_affecting_csv(): void
    {
        [$report, $teacher] = $this->scenario();

        $lifecycle = app(ReportExportLifecycle::class);

        $pdfToken = $lifecycle->request($report, $teacher, 'PDF');
        $csvToken = $lifecycle->request($report, $teacher, 'CSV');

        $this->assertNotNull($pdfToken);
        $this->assertNotNull($csvToken);

        $this->assertSame(2, ReportExportRequest::query()->count());

        $this->assertTrue(
            $lifecycle->started($report->id, $teacher->id, 'PDF', $pdfToken)
        );

        $this->assertTrue(
            $lifecycle->failed($report->id, $teacher->id, 'PDF', $pdfToken)
        );

        $retryToken = $lifecycle->request($report, $teacher, 'PDF');

        $this->assertNotNull($retryToken);
        $this->assertNotSame($pdfToken, $retryToken);

        $pdf = ReportExportRequest::where('format', 'PDF')->firstOrFail();
        $csv = ReportExportRequest::where('format', 'CSV')->firstOrFail();

        $this->assertSame('pending', $pdf->state);
        $this->assertSame('pending', $csv->state);

        $this->assertSame(2, ReportExportRequest::query()->count());
    }
}
