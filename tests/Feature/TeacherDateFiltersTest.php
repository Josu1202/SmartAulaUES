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

class TeacherDateFiltersTest extends TestCase
{
    private function asUser(User $user): static
    {
        return $this->withSession(['password_hash_web' => $user->password])->actingAs($user);
    }

    private function makeSession(AssessmentPeriod $period, string $date, Enrollment $enrollment, string $status): ClassSession
    {
        $start = CarbonImmutable::parse($date.' 16:00:00', 'UTC');
        $session = ClassSession::factory()->create([
            'offering_id' => $period->offering_id,
            'period_id' => $period->id,
            'state' => 'completed',
            'starts_at' => $start,
            'ends_at' => $start->addHour(),
        ]);
        Attendance::create([
            'offering_id' => $period->offering_id,
            'session_id' => $session->id,
            'enrollment_id' => $enrollment->id,
            'status' => $status,
            'recorded_by' => $period->offering->teacher_id,
            'recorded_at' => now(),
        ]);

        return $session;
    }

    public function test_open_period_clips_eligible_counts_trend_matrix_and_detail_inclusively(): void
    {
        $this->travelTo(now()->setDate(2026, 6, 15)->setTime(18, 0));
        $period = AssessmentPeriod::factory()->create();
        $enrollment = Enrollment::factory()->create(['offering_id' => $period->offering_id]);
        $teacher = $period->offering->teacher;
        $first = $this->makeSession($period, '2026-06-01', $enrollment, 'absent');
        $middle = $this->makeSession($period, '2026-06-03', $enrollment, 'present');
        $last = $this->makeSession($period, '2026-06-08', $enrollment, 'absent');
        $url = '/dashboard?ranking_group_id='.$period->offering_id.'&ranking_period_id='.$period->id;

        $whole = $this->asUser($teacher)->get($url)->assertOk()->viewData('absenceRanking');
        $this->assertSame(3, $whole['rows'][0]['eligible']);
        $this->assertSame(2, $whole['rows'][0]['absent']);
        $this->assertCount(3, $whole['matrix']['sessions']);

        $result = $this->get($url.'&ranking_date_from=2026-06-03&ranking_date_to=2026-06-08');
        $result->assertOk()->assertSee('Aplicar fechas')->assertSee('Restablecer fechas');
        $filtered = $result->viewData('absenceRanking');
        $this->assertSame(2, $filtered['rows'][0]['eligible']);
        $this->assertSame(1, $filtered['rows'][0]['absent']);
        $this->assertSame([$middle->id, $last->id], array_column($filtered['matrix']['sessions'], 'id'));
        $this->assertSame(['2026-06-01', '2026-06-08'], array_column($filtered['weekly_trend'], 'week_start'));
        $this->assertSame([1, 1], array_column($filtered['weekly_trend'], 'eligible'));
        $detailResponse = $this->get($url.'&ranking_enrollment_id='.$enrollment->id.'&ranking_date_from=2026-06-03&ranking_date_to=2026-06-08');
        $detailResponse->assertOk();
        $this->assertSame(['2026-06-03', '2026-06-08'], array_column($detailResponse->viewData('absenceRanking')['detail']['sessions'], 'academic_date'));
        $this->assertNotContains($first->id, array_column($filtered['matrix']['sessions'], 'id'));
    }

    public function test_invalid_or_reversed_dates_are_rejected(): void
    {
        $teacher = User::factory()->teacher()->create();
        $this->asUser($teacher)->getJson('/dashboard?ranking_date_from=2026-02-30')->assertUnprocessable();
        $this->getJson('/dashboard?ranking_date_from=2026-06-08&ranking_date_to=2026-06-03')->assertUnprocessable();
    }

    public function test_date_fields_respect_period_bounds_and_server_rejects_outside_dates(): void
    {
        $this->travelTo(now()->setDate(2026, 6, 10)->setTime(18, 0));
        $period = AssessmentPeriod::factory()->create([
            'starts_on' => '2026-06-01',
            'ends_on' => '2026-06-15',
        ]);
        Enrollment::factory()->create(['offering_id' => $period->offering_id]);
        $url = '/dashboard?ranking_group_id='.$period->offering_id.'&ranking_period_id='.$period->id;

        $this->asUser($period->offering->teacher)->get($url)
            ->assertOk()
            ->assertSee('min="2026-06-01" max="2026-06-15"', false)
            ->assertSee('Fechas del cómputo:');

        $this->getJson($url.'&ranking_date_from=2026-05-31&ranking_date_to=2026-06-10')
            ->assertUnprocessable()->assertJsonValidationErrors('ranking_date_from');
        $this->getJson($url.'&ranking_date_from=2026-06-01&ranking_date_to=2026-06-16')
            ->assertUnprocessable()->assertJsonValidationErrors('ranking_date_to');
        $this->getJson($url.'&ranking_date_from=2026-06-16')
            ->assertUnprocessable()->assertJsonValidationErrors('ranking_date_from');
        $this->getJson($url.'&ranking_date_to=2026-05-31')
            ->assertUnprocessable()->assertJsonValidationErrors('ranking_date_to');
        $this->get($url.'&ranking_date_from=2026-06-01&ranking_date_to=2026-06-15')->assertOk();
    }

    public function test_a_date_filter_without_an_authorized_period_is_rejected(): void
    {
        $teacher = User::factory()->teacher()->create();
        $this->asUser($teacher)->getJson('/dashboard?ranking_date_from=2026-06-03')
            ->assertUnprocessable()->assertJsonValidationErrors('ranking_date_from');
    }

    public function test_empty_range_shows_no_evaluable_sessions_without_adding_absences(): void
    {
        $this->travelTo(now()->setDate(2026, 6, 15)->setTime(18, 0));
        $period = AssessmentPeriod::factory()->create();
        $enrollment = Enrollment::factory()->create(['offering_id' => $period->offering_id]);
        $this->makeSession($period, '2026-06-03', $enrollment, 'absent');
        $response = $this->asUser($period->offering->teacher)
            ->get('/dashboard?ranking_group_id='.$period->offering_id.'&ranking_period_id='.$period->id.'&ranking_date_from=2026-06-04&ranking_date_to=2026-06-06');
        $response->assertOk()->assertSee('No hay sesiones dentro de las fechas seleccionadas');
        $ranking = $response->viewData('absenceRanking');
        $this->assertSame(0, $ranking['rows'][0]['eligible']);
        $this->assertSame(0, $ranking['rows'][0]['absent']);
        $this->assertNull($ranking['rows'][0]['absence_percentage']);
        $this->assertSame([], $ranking['weekly_trend']);
        $this->assertSame([], $ranking['matrix']['sessions']);
    }

    public function test_closed_period_preserves_original_snapshot_and_filters_only_visual_sessions(): void
    {
        $this->travelTo(now()->setDateTime(2026, 10, 23, 6, 0, 0));
        $period = AssessmentPeriod::factory()->create(['starts_on' => '2026-10-01', 'ends_on' => '2026-10-22']);
        $period->offering->cycle->update(['ends_on' => '2026-10-22']);
        $enrollment = Enrollment::factory()->create(['offering_id' => $period->offering_id]);
        $first = $this->makeSession($period, '2026-10-20', $enrollment, 'absent');
        $second = $this->makeSession($period, '2026-10-22', $enrollment, 'present');
        app(ClosureWorkflow::class)->closePeriod(User::factory()->coordinator()->create(), $period);
        $this->assertSame('closed', $period->fresh()->state);
        $reportId = $period->fresh()->current_report_id;
        $url = '/dashboard?ranking_cycle_id='.$period->offering->cycle_id.'&ranking_group_id='.$period->offering_id.'&ranking_period_id='.$period->id;
        $filtered = $this->asUser($period->offering->teacher)->get($url.'&ranking_date_from=2026-10-22&ranking_date_to=2026-10-22');
        $filtered->assertOk()->assertSee('el ranking y sus denominadores conservan el snapshot completo');
        $ranking = $filtered->viewData('absenceRanking');
        $this->assertSame('snapshot', $ranking['source']);
        $this->assertFalse($ranking['date_filter']['affects_ranking']);
        $this->assertSame(2, $ranking['rows'][0]['eligible']);
        $this->assertSame(1, $ranking['rows'][0]['absent']);
        $this->assertSame([$second->id], array_column($ranking['matrix']['sessions'], 'id'));
        $this->assertSame([1], array_column($ranking['weekly_trend'], 'eligible'));
        $this->assertSame($reportId, $period->fresh()->current_report_id);
        $this->assertNotContains($first->id, array_column($ranking['matrix']['sessions'], 'id'));
    }

    public function test_foreign_teacher_and_student_cannot_access_collective_date_filtered_data(): void
    {
        $period = AssessmentPeriod::factory()->create();
        $student = Enrollment::factory()->create(['offering_id' => $period->offering_id])->student;
        $other = User::factory()->teacher()->create();
        $url = '/dashboard?ranking_group_id='.$period->offering_id.'&ranking_period_id='.$period->id.'&ranking_date_from=2026-06-01';
        $this->asUser($other)->get($url)->assertNotFound();
        $this->asUser($student)->get($url)->assertOk()->assertDontSee('Aplicar fechas');
    }
}
