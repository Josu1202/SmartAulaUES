<?php

namespace Tests\Feature;

use App\Models\AbsenceRequest;
use App\Models\AssessmentPeriod;
use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\User;
use Tests\TestCase;

class TeacherPeriodTrackingTest extends TestCase
{
    private function asUser(User $user): static
    {
        return $this->withSession(['password_hash_web' => $user->password])->actingAs($user);
    }

    public function test_teacher_sees_only_operational_indicators_for_the_selected_period(): void
    {
        $period = AssessmentPeriod::factory()->create([
            'closure_issues' => ['INCIDENCIA_ADMINISTRATIVA_PRIVADA_B01'],
            'closure_checked_at' => now()->subDay(),
        ]);
        $enrollment = Enrollment::factory()->create(['offering_id' => $period->offering_id]);
        $session = ClassSession::factory()->create([
            'offering_id' => $period->offering_id,
            'period_id' => $period->id,
            'state' => 'in_progress',
            'started_at' => now()->subHour(),
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
        ]);
        AbsenceRequest::create([
            'offering_id' => $period->offering_id,
            'session_id' => $session->id,
            'enrollment_id' => $enrollment->id,
            'status' => 'pending',
            'reason' => 'MOTIVO_PRIVADO_B01',
        ]);
        $otherPeriod = AssessmentPeriod::factory()->create(['offering_id' => $period->offering_id, 'number' => 2]);
        $otherEnrollment = Enrollment::factory()->create(['offering_id' => $period->offering_id]);
        $otherSession = ClassSession::factory()->create([
            'offering_id' => $period->offering_id,
            'period_id' => $otherPeriod->id,
            'state' => 'in_progress',
            'started_at' => now()->subHour(),
        ]);
        AbsenceRequest::create([
            'offering_id' => $period->offering_id,
            'session_id' => $otherSession->id,
            'enrollment_id' => $otherEnrollment->id,
            'status' => 'pending',
            'reason' => 'OTRO_COMPUTO_PRIVADO_B01',
        ]);

        $response = $this->asUser($period->offering->teacher)
            ->get('/dashboard?ranking_group_id='.$period->offering_id.'&ranking_period_id='.$period->id);

        $response->assertOk()->assertSee('Seguimiento del cómputo')
            ->assertSee('Permisos pendientes del cómputo')
            ->assertSee('Sesiones en curso')
            ->assertDontSee('INCIDENCIA_ADMINISTRATIVA_PRIVADA_B01')
            ->assertDontSee('MOTIVO_PRIVADO_B01');
        $tracking = $response->viewData('absenceRanking')['period_tracking'];
        $this->assertSame('open', $tracking['state']);
        $this->assertSame(1, $tracking['pending_requests']);
        $this->assertSame(1, $tracking['in_progress_sessions']);
        $this->assertNotNull($tracking['last_checked_at']);
    }

    public function test_reopened_period_is_presented_as_review_not_as_definitive(): void
    {
        $period = AssessmentPeriod::factory()->create([
            'state' => 'open',
            'reopened_at' => now()->subDay(),
        ]);
        Enrollment::factory()->create(['offering_id' => $period->offering_id]);

        $response = $this->asUser($period->offering->teacher)
            ->get('/dashboard?ranking_group_id='.$period->offering_id.'&ranking_period_id='.$period->id);

        $response->assertOk()->assertSee('En revisión (reabierto)');
        $this->assertSame('review', $response->viewData('absenceRanking')['period_tracking']['state']);
    }

    public function test_foreign_teacher_and_student_cannot_read_group_tracking(): void
    {
        $period = AssessmentPeriod::factory()->create();
        $student = Enrollment::factory()->create(['offering_id' => $period->offering_id])->student;
        $otherTeacher = User::factory()->teacher()->create();
        $url = '/dashboard?ranking_group_id='.$period->offering_id.'&ranking_period_id='.$period->id;

        $this->asUser($otherTeacher)->get($url)->assertNotFound();
        $this->asUser($student)->get($url)->assertOk()->assertDontSee('Seguimiento del cómputo');
    }
}
