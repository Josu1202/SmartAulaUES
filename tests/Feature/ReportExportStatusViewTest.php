<?php

namespace Tests\Feature;

use App\Actions\Attendance\ClosureWorkflow;
use App\Models\AssessmentPeriod;
use App\Models\Enrollment;
use App\Models\ReportVersion;
use App\Models\User;
use App\Services\Reports\ReportExportLifecycle;
use Tests\TestCase;

class ReportExportStatusViewTest extends TestCase
{
    private function asUser(User $user): static
    {
        return $this->withSession([
            'password_hash_web' => $user->password,
        ])->actingAs($user);
    }

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
            $coordinator,
        ];
    }

    public function test_pending_pdf_does_not_block_csv_request(): void
    {
        [$report, $teacher] = $this->scenario();

        app(ReportExportLifecycle::class)
            ->request($report, $teacher, 'PDF');

        $this->asUser($teacher)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertSee('PDF pendiente')
            ->assertSee('Solicitar CSV')
            ->assertDontSee('Generando PDF');
    }

    public function test_processing_and_failure_are_visible(): void
    {
        [$report, $teacher] = $this->scenario();

        $lifecycle = app(ReportExportLifecycle::class);

        $token = $lifecycle->request($report, $teacher, 'PDF');

        $this->assertTrue(
            $lifecycle->started(
                $report->id,
                $teacher->id,
                'PDF',
                $token
            )
        );

        $this->asUser($teacher)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertSee('Generando PDF');

        $this->assertTrue(
            $lifecycle->failed(
                $report->id,
                $teacher->id,
                'PDF',
                $token
            )
        );

        $this->get(route('reports.index'))
            ->assertOk()
            ->assertSee('Falló la generación.')
            ->assertSee('Reintentar PDF');
    }

    public function test_expired_requests_offer_retry(): void
    {
        [$report, $teacher] = $this->scenario();

        $lifecycle = app(ReportExportLifecycle::class);

        $lifecycle->request($report, $teacher, 'PDF');

        $csvToken = $lifecycle->request(
            $report,
            $teacher,
            'CSV'
        );

        $this->assertTrue(
            $lifecycle->started(
                $report->id,
                $teacher->id,
                'CSV',
                $csvToken
            )
        );

        $this->travel(11)->minutes();

        $this->asUser($teacher)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertSee('Reintentar PDF')
            ->assertSee('Reintentar CSV')
            ->assertSee('Solicitud anterior sin procesamiento activo.');
    }

    public function test_request_status_is_private_to_requester(): void
    {
        [$report, $teacher, $coordinator] = $this->scenario();

        app(ReportExportLifecycle::class)
            ->request($report, $coordinator, 'PDF');

        $this->asUser($teacher)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertSee('Solicitar PDF')
            ->assertDontSee('PDF pendiente');

        $this->asUser($coordinator)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertSee('PDF pendiente');
    }
}
