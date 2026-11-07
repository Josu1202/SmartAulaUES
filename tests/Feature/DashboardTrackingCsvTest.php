<?php

namespace Tests\Feature;

use App\Actions\Attendance\ClosureWorkflow;
use App\Models\AssessmentPeriod;
use App\Models\Attendance;
use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class DashboardTrackingCsvTest extends TestCase
{
    private function asUser(User $user): static
    {
        return $this->withSession(['password_hash_web' => $user->password])->actingAs($user);
    }

    private function url(AssessmentPeriod $period, string $suffix = ''): string
    {
        return '/dashboard/seguimiento.csv?ranking_group_id='.$period->offering_id.'&ranking_period_id='.$period->id.$suffix;
    }

    /** @return array<int, array<int, string|null>> */
    private function records(string $content): array
    {
        $stream = fopen('php://temp', 'w+');
        fwrite($stream, $content);
        rewind($stream);
        $records = [];
        while (($row = fgetcsv($stream, 0, ',', '"', '')) !== false) {
            $records[] = $row;
        }
        fclose($stream);
        $records[0][0] = preg_replace('/^\xEF\xBB\xBF/', '', $records[0][0]);

        return $records;
    }

    private function createAttendanceSession(AssessmentPeriod $period, Enrollment $enrollment, string $date, string $status): void
    {
        $startsAt = CarbonImmutable::parse($date.' 16:00:00', 'UTC');
        $session = ClassSession::factory()->create([
            'offering_id' => $period->offering_id,
            'period_id' => $period->id,
            'state' => 'completed',
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->addHour(),
        ]);
        Attendance::create([
            'offering_id' => $period->offering_id,
            'session_id' => $session->id,
            'enrollment_id' => $enrollment->id,
            'status' => $status,
            'recorded_by' => $period->offering->teacher_id,
            'recorded_at' => now(),
        ]);
    }

    public function test_export_contains_all_students_while_dashboard_stays_at_ten(): void
    {
        $this->travelTo(now()->setDate(2026, 6, 15)->setTime(18, 0));
        $period = AssessmentPeriod::factory()->create();
        for ($i = 0; $i < 12; $i++) {
            Enrollment::factory()->create(['offering_id' => $period->offering_id]);
        }

        $this->asUser($period->offering->teacher);
        $dashboard = $this->get('/dashboard?ranking_group_id='.$period->offering_id.'&ranking_period_id='.$period->id);
        $dashboard->assertOk()->assertSee('Exportar seguimiento CSV');
        $this->assertCount(10, $dashboard->viewData('absenceRanking')['rows']);

        $download = $this->get($this->url($period));
        $download->assertOk();
        $this->assertStringContainsString('attachment', $download->headers->get('Content-Disposition'));
        $this->assertStringContainsString('no-store', $download->headers->get('Cache-Control'));
        $rows = $this->records($download->streamedContent());
        $this->assertCount(13, $rows);
        $this->assertCount(19, $rows[0]);
        $this->assertSame('Ciclo', $rows[0][0]);
        $this->assertSame('Ausentes', $rows[0][13]);
        $this->assertSame('0', $rows[1][13]);
        $this->assertSame('0', $rows[1][15]);
        $this->assertSame('', $rows[1][16]); // Cero sesiones no equivale a 0.00 %.
    }

    public function test_open_period_csv_matches_date_filter_and_retains_real_zero_percent(): void
    {
        $this->travelTo(now()->setDate(2026, 6, 15)->setTime(18, 0));
        $period = AssessmentPeriod::factory()->create();
        $student = Enrollment::factory()->create(['offering_id' => $period->offering_id]);
        $this->createAttendanceSession($period, $student, '2026-06-01', 'absent');
        $this->createAttendanceSession($period, $student, '2026-06-03', 'present');
        $this->asUser($period->offering->teacher);
        $filter = '&ranking_date_from=2026-06-03&ranking_date_to=2026-06-03';

        $dashboard = $this->get('/dashboard?ranking_group_id='.$period->offering_id.'&ranking_period_id='.$period->id.$filter);
        $dashboard->assertOk();
        $ranking = $dashboard->viewData('absenceRanking');
        $csv = $this->records($this->get($this->url($period, $filter))->assertOk()->streamedContent());
        $this->assertSame((string) $ranking['rows'][0]['absent'], $csv[1][13]);
        $this->assertSame((string) $ranking['rows'][0]['eligible'], $csv[1][15]);
        $this->assertSame($ranking['rows'][0]['absence_percentage'], $csv[1][16]);
        $this->assertSame('Sí', $csv[1][8]);
        $this->assertSame('0.00', $csv[1][16]);
    }

    public function test_only_the_assigned_teacher_can_export_a_collective_ranking(): void
    {
        $period = AssessmentPeriod::factory()->create();
        $enrollment = Enrollment::factory()->create(['offering_id' => $period->offering_id]);
        $this->asUser(User::factory()->teacher()->create())->get($this->url($period))->assertNotFound();
        $this->asUser($enrollment->student)->get($this->url($period))->assertForbidden();
        $this->asUser(User::factory()->coordinator()->create())->get($this->url($period))->assertForbidden();
        $this->asUser($period->offering->teacher)->get($this->url($period))->assertOk();
    }

    public function test_rejects_invalid_and_outside_date_ranges(): void
    {
        $this->travelTo(now()->setDate(2026, 6, 10)->setTime(18, 0));
        $period = AssessmentPeriod::factory()->create(['starts_on' => '2026-06-01', 'ends_on' => '2026-06-15']);
        $this->asUser($period->offering->teacher);
        $this->getJson($this->url($period, '&ranking_sort=unknown'))->assertUnprocessable();
        $this->getJson($this->url($period, '&ranking_date_from=2026-06-09&ranking_date_to=2026-06-08'))
            ->assertUnprocessable()->assertJsonValidationErrors('ranking_date_to');
        $this->getJson($this->url($period, '&ranking_date_from=2026-05-31'))
            ->assertUnprocessable()->assertJsonValidationErrors('ranking_date_from');
        $this->getJson('/dashboard/seguimiento.csv')->assertUnprocessable();
    }

    public function test_closed_period_never_date_clips_certified_absences(): void
    {
        $this->travelTo(now()->setDateTime(2026, 10, 23, 6, 0, 0));
        $period = AssessmentPeriod::factory()->create(['starts_on' => '2026-10-01', 'ends_on' => '2026-10-22']);
        $period->offering->cycle->update(['ends_on' => '2026-10-22']);
        $student = Enrollment::factory()->create(['offering_id' => $period->offering_id]);
        $this->createAttendanceSession($period, $student, '2026-10-20', 'absent');
        $this->createAttendanceSession($period, $student, '2026-10-22', 'present');
        app(ClosureWorkflow::class)->closePeriod(User::factory()->coordinator()->create(), $period);
        $period = $period->fresh();
        $snapshotHash = $period->reports()->findOrFail($period->current_report_id)->snapshot_hash;
        $filter = '&ranking_cycle_id='.$period->offering->cycle_id.'&ranking_date_from=2026-10-22&ranking_date_to=2026-10-22';

        $csv = $this->records($this->asUser($period->offering->teacher)
            ->get($this->url($period, $filter))->assertOk()->streamedContent());
        $this->assertSame('Cierre conservado', $csv[1][4]);
        $this->assertNotSame('', $csv[1][5]);
        $this->assertSame('No', $csv[1][8]);
        $this->assertSame('1', $csv[1][13]);
        $this->assertSame('2', $csv[1][15]);
        $this->assertSame($snapshotHash, $period->reports()->findOrFail($period->current_report_id)->snapshot_hash);
    }

    public function test_spreadsheet_formulas_are_neutralized_without_changing_source_name(): void
    {
        $this->travelTo(now()->setDate(2026, 6, 15)->setTime(18, 0));
        $period = AssessmentPeriod::factory()->create();
        $enrollment = Enrollment::factory()->create(['offering_id' => $period->offering_id]);
        $enrollment->student->update(['name' => " \t=1+1", 'student_code' => '@calc']);
        $csv = $this->records($this->asUser($period->offering->teacher)
            ->get($this->url($period))->assertOk()->streamedContent());
        $this->assertSame("' \t=1+1", $csv[1][11]);
        $this->assertSame("'@calc", $csv[1][12]);
        $this->assertSame(" \t=1+1", $enrollment->student->fresh()->name);
    }
}
