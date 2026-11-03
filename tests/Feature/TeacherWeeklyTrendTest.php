<?php

namespace Tests\Feature;

use App\Models\AbsenceRequest;
use App\Models\AssessmentPeriod;
use App\Models\Attendance;
use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class TeacherWeeklyTrendTest extends TestCase
{
    private function asUser(User $user): static
    {
        return $this->withSession(['password_hash_web' => $user->password])->actingAs($user);
    }

    private function createClassSession(AssessmentPeriod $period, string $date, string $state = 'completed'): ClassSession
    {
        $start = CarbonImmutable::parse($date.' 15:00:00', 'UTC');

        return ClassSession::factory()->create([
            'offering_id' => $period->offering_id,
            'period_id' => $period->id,
            'state' => $state,
            // Las sesiones canceladas requieren motivo por class_sessions_rule_3.
            'change_reason' => $state === 'cancelled' ? 'Cancelación ficticia de prueba para tendencias semanales' : null,
            'starts_at' => $start,
            'ends_at' => $start->addHour(),
        ]);
    }

    private function attendance(ClassSession $session, Enrollment $enrollment, string $status): void
    {
        Attendance::create([
            'offering_id' => $session->offering_id,
            'session_id' => $session->id,
            'enrollment_id' => $enrollment->id,
            'status' => $status,
            'recorded_by' => $session->offering->teacher_id,
            'recorded_at' => now(),
        ]);
        if ($status === 'justified') {
            AbsenceRequest::create([
                'offering_id' => $session->offering_id,
                'session_id' => $session->id,
                'enrollment_id' => $enrollment->id,
                'status' => 'approved',
                'reason' => 'Permiso ficticio para probar la tendencia semanal',
            ]);
        }
    }

    public function test_weekly_trend_groups_realized_sessions_by_local_monday_and_separates_missing_results(): void
    {
        $this->travelTo(now()->setDate(2026, 6, 20)->setTime(18, 0));
        $period = AssessmentPeriod::factory()->create();
        $teacher = $period->offering->teacher;
        $alicia = Enrollment::factory()->create(['offering_id' => $period->offering_id]);
        $bruno = Enrollment::factory()->create(['offering_id' => $period->offering_id]);
        $carla = Enrollment::factory()->create(['offering_id' => $period->offering_id]);

        $first = $this->createClassSession($period, '2026-06-01');
        $this->attendance($first, $alicia, 'present');
        $this->attendance($first, $bruno, 'absent');
        $second = $this->createClassSession($period, '2026-06-03');
        $this->attendance($second, $alicia, 'justified');
        $this->attendance($second, $bruno, 'absent');
        $third = $this->createClassSession($period, '2026-06-08');
        $this->attendance($third, $alicia, 'absent');
        $this->attendance($third, $bruno, 'present');
        $this->attendance($third, $carla, 'present');
        $this->createClassSession($period, '2026-06-10', 'cancelled');
        $this->createClassSession($period, '2026-06-30', 'scheduled');

        $response = $this->asUser($teacher)->get('/dashboard?ranking_group_id='.$period->offering_id.'&ranking_period_id='.$period->id);
        $response->assertOk()->assertSee('Tendencia semanal de asistencia')->assertSee('Cobertura de resultados');
        $trend = $response->viewData('absenceRanking')['weekly_trend'];
        $this->assertCount(2, $trend);
        $this->assertSame(['2026-06-01', '2026-06-08'], array_column($trend, 'week_start'));
        $this->assertSame([2, 1], array_column($trend, 'completed_sessions'));
        $this->assertSame([1, 2], array_column($trend, 'present'));
        $this->assertSame([2, 1], array_column($trend, 'absent'));
        $this->assertSame([1, 0], array_column($trend, 'justified'));
        $this->assertSame([6, 3], array_column($trend, 'eligible'));
        $this->assertSame([2, 0], array_column($trend, 'missing'));
        $this->assertSame(['66.67', '100.00'], array_column($trend, 'coverage_percentage'));
    }

    public function test_no_completed_sessions_or_students_shows_honest_empty_state(): void
    {
        $this->travelTo(now()->setDate(2026, 6, 20)->setTime(18, 0));
        $period = AssessmentPeriod::factory()->create();
        Enrollment::factory()->create(['offering_id' => $period->offering_id]);
        $this->createClassSession($period, '2026-06-30', 'scheduled');
        $this->createClassSession($period, '2026-06-10', 'cancelled');

        // Muestra la excepción original si persiste un error 500.
        $this->withoutExceptionHandling();
        $response = $this->asUser($period->offering->teacher)
            ->get('/dashboard?ranking_group_id='.$period->offering_id.'&ranking_period_id='.$period->id);
        $response->assertOk()->assertSee('Todavía no hay clases finalizadas');
        $this->assertSame([], $response->viewData('absenceRanking')['weekly_trend']);
    }

    public function test_weekly_trend_is_not_exposed_to_a_foreign_teacher_or_student(): void
    {
        $period = AssessmentPeriod::factory()->create();
        $student = Enrollment::factory()->create(['offering_id' => $period->offering_id])->student;
        $foreignTeacher = User::factory()->teacher()->create();

        $this->asUser($foreignTeacher)
            ->get('/dashboard?ranking_group_id='.$period->offering_id.'&ranking_period_id='.$period->id)
            ->assertNotFound();
        $this->asUser($student)
            ->get('/dashboard?ranking_group_id='.$period->offering_id.'&ranking_period_id='.$period->id)
            ->assertOk()->assertDontSee('Tendencia semanal de asistencia');
    }
}
