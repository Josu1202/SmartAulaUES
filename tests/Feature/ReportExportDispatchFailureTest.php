<?php

namespace Tests\Feature;

use App\Actions\Attendance\ClosureWorkflow;
use App\Models\AssessmentPeriod;
use App\Models\Enrollment;
use App\Models\ReportVersion;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use RuntimeException;
use Tests\TestCase;

class ReportExportDispatchFailureTest extends TestCase
{
    private function scenario(): array
    {
        config()->set('queue.default', 'database');

        $this->travelTo(
            now()->setDateTime(2026, 10, 23, 12, 0, 0)
        );

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

        app(ClosureWorkflow::class)->closePeriod(
            $coordinator,
            $period
        );

        $period->refresh();

        return [
            ReportVersion::findOrFail($period->current_report_id),
            $period->offering->teacher,
        ];
    }

    private function verifyDispatchFailure(string $format): void
    {
        [$report, $teacher] = $this->scenario();

        Bus::shouldReceive('dispatch')
            ->once()
            ->andThrow(new RuntimeException('Fallo simulado de cola'));

        $route = $format === 'PDF'
            ? 'reports.generate'
            : 'reports.csv.generate';

        $this->withSession([
            'password_hash_web' => $teacher->password,
        ])->actingAs($teacher)
            ->post(route($route, $report))
            ->assertStatus(503)
            ->assertDontSee('Fallo simulado de cola');

        $this->assertDatabaseHas('report_export_requests', [
            'report_version_id' => $report->id,
            'requested_by' => $teacher->id,
            'format' => $format,
            'state' => 'failed',
        ]);
    }

    public function test_pdf_dispatch_failure_is_recoverable(): void
    {
        $this->verifyDispatchFailure('PDF');
    }

    public function test_csv_dispatch_failure_is_recoverable(): void
    {
        $this->verifyDispatchFailure('CSV');
    }
}
