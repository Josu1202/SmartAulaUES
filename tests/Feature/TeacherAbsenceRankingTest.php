<?php

namespace Tests\Feature;

use App\Models\AbsenceRequest;
use App\Models\AssessmentPeriod;
use App\Models\Attendance;
use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Offering;
use App\Models\User;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class TeacherAbsenceRankingTest extends TestCase
{
    private function asUser(User $user): static
    {
        return $this->withSession(['password_hash_web' => $user->password])->actingAs($user);
    }

    private function scenario(): array
    {
        $this->travelTo(now()->setDate(2026, 6, 15)->setTime(18, 0));
        $period = AssessmentPeriod::factory()->create();
        $teacher = $period->offering->teacher;
        $alicia = Enrollment::factory()->create(['offering_id' => $period->offering_id]);
        $bruno = Enrollment::factory()->create(['offering_id' => $period->offering_id]);
        $carla = Enrollment::factory()->create(['offering_id' => $period->offering_id]);
        $alicia->student->update(['name' => 'Alicia Ranking']);
        $bruno->student->update(['name' => 'Bruno Ranking']);
        $carla->student->update(['name' => 'Carla Ranking']);

        $this->record($period, [$alicia->id => 'absent', $bruno->id => 'present', $carla->id => 'justified']);
        $this->record($period, [$alicia->id => 'absent', $bruno->id => 'absent', $carla->id => 'present']);
        $this->record($period, [$alicia->id => 'present', $bruno->id => 'present', $carla->id => 'present']);

        return compact('period', 'teacher', 'alicia', 'bruno', 'carla');
    }

    private function record(AssessmentPeriod $period, array $statuses): void
    {
        $start = CarbonImmutable::parse('2026-06-01 14:00:00', 'UTC');
        $session = ClassSession::factory()->create([
            'period_id' => $period->id,
            'offering_id' => $period->offering_id,
            'state' => 'completed',
            'starts_at' => $start,
            'ends_at' => $start->addHour(),
        ]);
        foreach ($statuses as $enrollmentId => $status) {
            Attendance::create([
                'offering_id' => $period->offering_id,
                'session_id' => $session->id,
                'enrollment_id' => $enrollmentId,
                'status' => $status,
                'recorded_by' => $period->offering->teacher_id,
                'recorded_at' => now(),
            ]);
            if ($status === 'justified') {
                AbsenceRequest::create([
                    'offering_id' => $period->offering_id,
                    'session_id' => $session->id,
                    'enrollment_id' => $enrollmentId,
                    'status' => 'approved',
                    'reason' => 'Permiso ficticio para verificar el ranking B01',
                ]);
            }
        }
    }

    public function test_ranking_orders_absences_and_separates_justified_counts_from_eligible_sessions(): void
    {
        ['period' => $period, 'teacher' => $teacher, 'alicia' => $alicia, 'bruno' => $bruno, 'carla' => $carla] = $this->scenario();

        $response = $this->asUser($teacher)->get('/dashboard?ranking_group_id='.$period->offering_id.'&ranking_period_id='.$period->id);
        $response->assertOk()->assertSee('Estudiantes con más ausencias')->assertSee('Ver detalle');
        $ranking = $response->viewData('absenceRanking');
        $this->assertSame([$alicia->id, $bruno->id, $carla->id], array_column($ranking['rows'], 'enrollment_id'));
        $this->assertSame([2, 1, 0], array_column($ranking['rows'], 'absent'));
        $this->assertSame([3, 3, 3], array_column($ranking['rows'], 'eligible'));
        $this->assertSame(['66.67', '33.33', '0.00'], array_column($ranking['rows'], 'absence_percentage'));
        $this->assertSame([0, 0, 1], array_column($ranking['rows'], 'justified'));
    }

    public function test_detail_shows_only_selected_students_sessions_and_keeps_group_and_period(): void
    {
        ['period' => $period, 'teacher' => $teacher, 'alicia' => $alicia] = $this->scenario();
        $response = $this->asUser($teacher)->get('/dashboard?ranking_group_id='.$period->offering_id.'&ranking_period_id='.$period->id.'&ranking_enrollment_id='.$alicia->id);
        $response->assertOk()->assertSee('Detalle de Alicia Ranking');
        $ranking = $response->viewData('absenceRanking');
        $this->assertSame($alicia->id, $ranking['detail']['student']['enrollment_id']);
        $this->assertCount(3, $ranking['detail']['sessions']);
        $this->assertSame(['absent', 'absent', 'present'], array_column($ranking['detail']['sessions'], 'status'));
    }

    public function test_ranking_rejects_another_teachers_group_or_foreign_period_and_detail(): void
    {
        ['period' => $period, 'teacher' => $teacher, 'alicia' => $alicia] = $this->scenario();
        $foreignTeacher = User::factory()->teacher()->create();
        $this->asUser($foreignTeacher)
            ->get('/dashboard?ranking_group_id='.$period->offering_id.'&ranking_period_id='.$period->id)->assertNotFound();

        $otherGroup = Offering::factory()->create(['teacher_id' => $teacher->id, 'cycle_id' => $period->offering->cycle_id]);
        $otherPeriod = AssessmentPeriod::factory()->create(['offering_id' => $otherGroup->id]);
        $this->asUser($teacher)
            ->get('/dashboard?ranking_group_id='.$otherGroup->id.'&ranking_period_id='.$period->id)->assertNotFound();
        $this->get('/dashboard?ranking_group_id='.$otherGroup->id.'&ranking_period_id='.$otherPeriod->id.'&ranking_enrollment_id='.$alicia->id)->assertNotFound();
    }

    public function test_student_cannot_open_ranking_or_other_students_detail_from_url(): void
    {
        ['period' => $period, 'alicia' => $alicia, 'bruno' => $bruno] = $this->scenario();
        $response = $this->asUser($bruno->student)->get('/dashboard?ranking_group_id='.$period->offering_id.'&ranking_period_id='.$period->id.'&ranking_enrollment_id='.$alicia->id);
        $response->assertOk()->assertDontSee('Estudiantes con más ausencias')
            ->assertDontSee('Detalle de Alicia Ranking');
        $this->assertNull($response->viewData('absenceRanking'));
    }

    public function test_zero_eligible_sessions_does_not_invent_absence_percentage(): void
    {
        $this->travelTo(now()->setDate(2026, 6, 15)->setTime(18, 0));
        $period = AssessmentPeriod::factory()->create();
        Enrollment::factory()->create(['offering_id' => $period->offering_id]);
        $response = $this->asUser($period->offering->teacher)->get('/dashboard?ranking_group_id='.$period->offering_id.'&ranking_period_id='.$period->id);
        $response->assertOk();
        $ranking = $response->viewData('absenceRanking');
        $this->assertSame(0, $ranking['rows'][0]['eligible']);
        $this->assertNull($ranking['rows'][0]['absence_percentage']);
        $response->assertSee('Sin sesiones evaluables');
    }
}
