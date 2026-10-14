<?php

namespace Tests\Feature;

use App\Actions\Attendance\SessionWorkflow;
use App\Enums\SessionState;
use App\Models\AbsenceRequest;
use App\Models\AssessmentPeriod;
use App\Models\Attendance;
use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\User;
use Tests\TestCase;

class SessionWorkflowTest extends TestCase
{
    public function test_teacher_programs_own_session_but_not_another_group(): void
    {
        $period = AssessmentPeriod::factory()->create();
        $teacher = $period->offering->teacher;
        $other = AssessmentPeriod::factory()->create();
        $data = ['period_id' => $period->id, 'starts_at' => '2026-10-20T08:00', 'ends_at' => '2026-10-20T09:00', 'modality' => 'in_person'];
        $this->actingAs($teacher)->post('/offerings/'.$period->offering_id.'/sessions', $data)->assertRedirect()->assertSessionHasNoErrors();
        $session = ClassSession::sole();
        $this->assertSame('14:00', $session->starts_at->format('H:i'));
        $this->post('/offerings/'.$other->offering_id.'/sessions', [...$data, 'period_id' => $other->id])->assertForbidden();
        $this->post('/admin/offerings/'.$period->offering_id.'/periods', ['number' => 2])->assertForbidden();
    }

    public function test_sessions_validate_period_dates_and_teacher_timetable(): void
    {
        $period = AssessmentPeriod::factory()->create(['starts_on' => '2026-09-01', 'ends_on' => '2026-12-31']);
        $this->actingAs($period->offering->teacher);
        $data = ['period_id' => $period->id, 'starts_at' => '2026-08-31T08:00', 'ends_at' => '2026-08-31T09:00', 'modality' => 'online'];
        $this->post('/offerings/'.$period->offering_id.'/sessions', $data)->assertSessionHasErrors('ends_at');
        $data['starts_at'] = '2026-10-20T08:00';
        $data['ends_at'] = '2026-10-20T09:00';
        $this->post('/offerings/'.$period->offering_id.'/sessions', $data)->assertSessionHasNoErrors();
        $this->post('/offerings/'.$period->offering_id.'/sessions', $data)->assertSessionHasErrors('starts_at');
        $this->assertDatabaseCount('class_sessions', 1);
    }

    public function test_state_lifecycle_generates_absences_and_respects_approved_permissions(): void
    {
        $this->travelTo(now()->setDateTime(2026, 10, 13, 14, 10, 0));
        $session = ClassSession::factory()->create(['starts_at' => '2026-10-13T14:00:00Z', 'ends_at' => '2026-10-13T15:00:00Z']);
        $a = Enrollment::factory()->create(['offering_id' => $session->offering_id]);
        $b = Enrollment::factory()->create(['offering_id' => $session->offering_id]);
        $future = Enrollment::factory()->create(['offering_id' => $session->offering_id, 'active_from' => '2026-10-14']);
        AbsenceRequest::create(['offering_id' => $session->offering_id, 'session_id' => $session->id, 'enrollment_id' => $b->id, 'reason' => 'Permiso de prueba', 'status' => 'approved']);
        $this->actingAs($session->offering->teacher)->post('/sessions/'.$session->id.'/state/start')->assertSessionHasNoErrors();
        $this->assertSame(SessionState::InProgress, $session->fresh()->state);
        $this->post('/sessions/'.$session->id.'/state/finish', ['reason' => 'La clase terminó anticipadamente'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('attendances', ['enrollment_id' => $a->id, 'status' => 'absent']);
        $this->assertDatabaseHas('attendances', ['enrollment_id' => $b->id, 'status' => 'justified']);
        $this->assertDatabaseMissing('attendances', ['enrollment_id' => $future->id]);
        $this->assertSame(SessionState::Completed, $session->fresh()->state);
        $this->post('/sessions/'.$session->id.'/state/start')->assertSessionHasErrors('session');
        $this->assertDatabaseCount('attendances', 2);
    }

    public function test_start_before_schedule_and_cancellation_without_reason_are_rejected(): void
    {
        $this->travelTo(now()->setDateTime(2026, 10, 13, 14, 10, 0));
        $session = ClassSession::factory()->create(['starts_at' => '2026-10-14T14:00:00Z', 'ends_at' => '2026-10-14T15:00:00Z']);
        $this->actingAs($session->offering->teacher)->post('/sessions/'.$session->id.'/state/start')->assertSessionHasErrors('session');
        $this->post('/sessions/'.$session->id.'/state/cancel')->assertSessionHasErrors('reason');
        $this->post('/sessions/'.$session->id.'/state/cancel', ['reason' => 'Actividad institucional'])->assertSessionHasNoErrors();
        $this->assertSame(SessionState::Cancelled, $session->fresh()->state);
    }

    public function test_reprogramming_requires_reason_and_notifies_enrolled_students(): void
    {
        $session = ClassSession::factory()->create(['starts_at' => '2026-10-20T14:00:00Z', 'ends_at' => '2026-10-20T15:00:00Z']);
        $enrollment = Enrollment::factory()->create(['offering_id' => $session->offering_id]);
        $data = ['period_id' => $session->period_id, 'starts_at' => '2026-10-21T08:00', 'ends_at' => '2026-10-21T09:00', 'modality' => 'in_person'];
        $this->actingAs($session->offering->teacher)->put('/offerings/'.$session->offering_id.'/sessions/'.$session->id, $data)->assertSessionHasErrors('reason');
        $this->put('/offerings/'.$session->offering_id.'/sessions/'.$session->id, [...$data, 'reason' => 'Cambio solicitado por la facultad'])->assertSessionHasNoErrors();
        $this->assertSame(1, $enrollment->student->notifications()->count());
    }

    public function test_student_session_page_does_not_expose_other_students(): void
    {
        $session = ClassSession::factory()->create();
        $own = Enrollment::factory()->create(['offering_id' => $session->offering_id]);
        Enrollment::factory()->create(['offering_id' => $session->offering_id, 'student_id' => User::factory()->create(['name' => 'PERSONA_PRIVADA_AJENA'])->id]);
        $this->actingAs($own->student)->get('/sessions/'.$session->id)->assertOk()->assertSee($own->student->name)->assertDontSee('PERSONA_PRIVADA_AJENA');
        $this->post('/sessions/'.$session->id.'/state/start')->assertForbidden();
    }

    public function test_completed_session_preserves_existing_present_rows_and_closes_portals(): void
    {
        $this->travelTo(now()->setDateTime(2026, 10, 13, 14, 10, 0));
        $session = ClassSession::factory()->create(['starts_at' => '2026-10-13T14:00:00Z', 'ends_at' => '2026-10-13T15:00:00Z', 'state' => 'in_progress']);
        $enrollment = Enrollment::factory()->create(['offering_id' => $session->offering_id]);
        Attendance::create(['session_id' => $session->id, 'enrollment_id' => $enrollment->id, 'offering_id' => $session->offering_id, 'status' => 'present', 'method' => 'nfc', 'recorded_by' => $session->offering->teacher_id, 'recorded_at' => now()]);
        $portal = $session->portals()->create(['opens_at' => now(), 'expires_at' => now()->addMinutes(15), 'enabled_methods' => ['manual'], 'token_hash' => hash('sha256', 'fixture')]);
        app(SessionWorkflow::class)->transition($session->offering->teacher, $session, 'finish', 'Fin anticipado de clase');
        $this->assertDatabaseCount('attendances', 1);
        $this->assertSame('present', Attendance::sole()->status->value);
        $this->assertNotNull($portal->fresh()->closed_at);
    }
}
