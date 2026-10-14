<?php

namespace Tests\Feature;

use App\Models\AcademicCycle;
use App\Models\AssessmentPeriod;
use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Offering;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AcademicAdministrationTest extends TestCase
{
    public function test_coordinator_creates_an_academic_chain_and_audits_every_write(): void
    {
        $this->actingAs(User::factory()->coordinator()->create());
        $this->post('/admin/cycles', ['code' => ' ciclo-2026 ', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31', 'status' => 'active'])->assertSessionHasNoErrors()->assertRedirect('/admin/cycles');
        $this->post('/admin/subjects', ['code' => ' inf-01 ', 'name' => 'Sistemas', 'active' => 1])->assertRedirect('/admin/subjects')->assertSessionHasNoErrors();
        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->create();
        $cycle = AcademicCycle::sole();
        $subject = Subject::sole();
        $this->post('/admin/offerings', ['cycle_id' => $cycle->id, 'subject_id' => $subject->id, 'teacher_id' => $teacher->id, 'group_code' => ' a ', 'capacity' => 20])->assertRedirect()->assertSessionHasNoErrors();
        $offering = Offering::sole();
        $this->post('/admin/offerings/'.$offering->id.'/periods', ['number' => 1, 'starts_on' => '2026-01-01', 'ends_on' => '2026-04-30'])->assertRedirect()->assertSessionHasNoErrors();
        $this->post('/admin/offerings/'.$offering->id.'/enrollments', ['student_id' => $student->id, 'active_from' => '2026-01-01'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('academic_cycles', ['code' => 'CICLO-2026']);
        $this->assertDatabaseHas('offerings', ['group_code' => 'A']);
        $this->assertDatabaseCount('audit_logs', 5);
        $this->get('/offerings/'.$offering->id)->assertOk()->assertSee('Inscribir estudiante')->assertSee($student->name);
    }

    public function test_all_administration_forms_render_with_real_data(): void
    {
        $offering = Offering::factory()->create();
        $period = AssessmentPeriod::factory()->create(['offering_id' => $offering->id]);
        $enrollment = Enrollment::factory()->create(['offering_id' => $offering->id]);
        $user = User::factory()->create();
        $this->actingAs(User::factory()->coordinator()->create());
        foreach (['/admin/users/create', '/admin/users/'.$user->id.'/edit', '/admin/cycles/create', '/admin/cycles/'.$offering->cycle_id.'/edit', '/admin/subjects', '/admin/subjects/create', '/admin/subjects/'.$offering->subject_id.'/edit', '/admin/offerings/create', '/admin/offerings/'.$offering->id.'/edit', '/admin/offerings/'.$offering->id.'/periods/create', '/admin/offerings/'.$offering->id.'/periods/'.$period->id.'/edit', '/admin/offerings/'.$offering->id.'/enrollments/create', '/admin/offerings/'.$offering->id.'/enrollments/'.$enrollment->id.'/edit', '/admin/imports', '/admin/audit', '/profile'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_teacher_and_student_cannot_write_or_open_administration_forms(): void
    {
        foreach ([User::factory()->teacher()->create(), User::factory()->create()] as $user) {
            $this->withSession(['password_hash_web' => $user->password])->actingAs($user);
            foreach (['/admin/users/create', '/admin/subjects', '/admin/offerings/create', '/admin/audit', '/admin/imports'] as $url) {
                $this->get($url)->assertForbidden();
            }
            foreach (['/admin/users', '/admin/cycles', '/admin/subjects', '/admin/offerings', '/admin/imports/preview', '/admin/imports/confirm'] as $url) {
                $this->post($url, [])->assertForbidden();
            }
        }
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_coordinator_creates_a_normalized_user_without_auditing_credentials(): void
    {
        $this->actingAs(User::factory()->coordinator()->create())->post('/admin/users', ['name' => 'Estudiante nuevo', 'email' => ' NUEVO@EXAMPLE.TEST ', 'role' => 'student', 'student_code' => 'ab001', 'active' => 1, 'password' => 'New-password-2026!', 'password_confirmation' => 'New-password-2026!'])->assertSessionHasNoErrors();
        $user = User::where('email', 'nuevo@example.test')->sole();
        $this->assertSame('AB001', $user->student_code);
        $this->assertTrue(Hash::check('New-password-2026!', $user->password));
        $audit = AuditLog::sole();
        $this->assertArrayNotHasKey('password', $audit->after);
        $this->assertStringNotContainsString('New-password-2026!', $audit->toJson());
    }

    public function test_duplicate_email_and_missing_student_code_are_rejected(): void
    {
        User::factory()->create(['email' => 'someone@example.test']);
        $this->actingAs(User::factory()->coordinator()->create())->post('/admin/users', ['name' => 'Duplicado', 'email' => 'SOMEONE@example.test', 'role' => 'student', 'active' => 1, 'password' => 'New-password-2026!', 'password_confirmation' => 'New-password-2026!'])->assertSessionHasErrors(['email', 'student_code']);
        $this->assertDatabaseCount('users', 2);
    }

    public function test_coordinator_cannot_deactivate_or_demote_their_own_account(): void
    {
        $actor = User::factory()->coordinator()->create();
        $this->actingAs($actor)->put('/admin/users/'.$actor->id, ['name' => $actor->name, 'email' => $actor->email, 'role' => 'teacher', 'active' => 0, 'reason' => 'Cambio de perfil'])->assertSessionHasErrors('role');
        $this->assertTrue($actor->fresh()->active);
        $this->assertSame('coordinator', $actor->fresh()->role->value);
    }

    public function test_user_with_academic_history_cannot_change_role(): void
    {
        $offering = Offering::factory()->create();
        $teacher = $offering->teacher;
        $this->actingAs(User::factory()->coordinator()->create())->put('/admin/users/'.$teacher->id, ['name' => $teacher->name, 'email' => $teacher->email, 'role' => 'student', 'student_code' => 'NEW001', 'active' => 1, 'reason' => 'Cambio de responsabilidades'])->assertSessionHasErrors('role');
        $this->assertSame('teacher', $teacher->fresh()->role->value);
    }

    public function test_account_deactivation_preserves_the_academic_history(): void
    {
        $enrollment = Enrollment::factory()->create();
        $student = $enrollment->student;
        $student->update(['student_code' => 'ACT001']);
        $this->actingAs(User::factory()->coordinator()->create())->put('/admin/users/'.$student->id, ['name' => $student->name, 'email' => $student->email, 'role' => 'student', 'student_code' => $student->student_code, 'active' => 0, 'reason' => 'Cuenta suspendida por coordinación'])->assertSessionHasNoErrors();
        $this->assertFalse($student->fresh()->active);
        $this->assertDatabaseHas('enrollments', ['id' => $enrollment->id]);
        $this->assertSame('user.updated', AuditLog::sole()->action);
    }

    public function test_password_reset_is_audited_without_the_password(): void
    {
        $user = User::factory()->create();
        $this->actingAs(User::factory()->coordinator()->create())->put('/admin/users/'.$user->id.'/password', ['password' => 'Reset-password-2026!', 'password_confirmation' => 'Reset-password-2026!', 'reason' => 'El titular perdió su acceso'])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('Reset-password-2026!', $user->fresh()->password));
        $this->assertStringNotContainsString('Reset-password', AuditLog::sole()->toJson());
        $this->assertArrayNotHasKey('password', AuditLog::sole()->after);
    }

    public function test_student_can_change_own_password_and_is_logged_out(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->put('/profile/password', ['current_password' => 'Test-password-2026!', 'password' => 'Different-password-2026!', 'password_confirmation' => 'Different-password-2026!'])->assertRedirect('/login');
        $this->assertGuest();
        $this->assertTrue(Hash::check('Different-password-2026!', $user->fresh()->password));
    }

    public function test_wrong_current_password_leaves_credentials_unchanged(): void
    {
        $user = User::factory()->create();
        $before = $user->password;
        $this->actingAs($user)->put('/profile/password', ['current_password' => 'Wrong-password-2026!', 'password' => 'Different-password-2026!', 'password_confirmation' => 'Different-password-2026!'])->assertSessionHasErrors('current_password');
        $this->assertSame($before, $user->fresh()->password);
    }

    public function test_session_with_old_password_hash_is_revoked(): void
    {
        $user = User::factory()->create();
        $oldHash = $user->password;
        $user->update(['password' => 'Changed-password-2026!']);
        $this->withSession(['password_hash_web' => $oldHash])->actingAs($user->fresh())->get('/profile')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_inactive_or_non_teacher_cannot_be_assigned_to_a_group(): void
    {
        $offering = Offering::factory()->create();
        $this->actingAs(User::factory()->coordinator()->create());
        foreach ([User::factory()->teacher()->create(['active' => false]), User::factory()->create()] as $invalid) {
            $this->put('/admin/offerings/'.$offering->id, [...$offering->only(['cycle_id', 'subject_id', 'group_code', 'capacity']), 'teacher_id' => $invalid->id, 'reason' => 'Reasignación docente'])->assertSessionHasErrors('teacher_id');
        }
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_period_dates_must_be_within_the_cycle_and_cannot_overlap(): void
    {
        $offering = Offering::factory()->create();
        AssessmentPeriod::factory()->create(['offering_id' => $offering->id, 'starts_on' => '2026-01-01', 'ends_on' => '2026-04-30']);
        $this->actingAs(User::factory()->coordinator()->create());
        $url = '/admin/offerings/'.$offering->id.'/periods';
        $this->post($url, ['number' => 2, 'starts_on' => '2026-04-30', 'ends_on' => '2026-08-31'])->assertSessionHasErrors('starts_on');
        $this->post($url, ['number' => 2, 'starts_on' => '2026-05-01', 'ends_on' => '2027-01-01'])->assertSessionHasErrors('starts_on');
        $this->post($url, ['number' => 2, 'starts_on' => '2026-05-01', 'ends_on' => '2026-08-31'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('assessment_periods', 2);
    }

    public function test_nested_period_and_enrollment_from_another_group_are_not_found(): void
    {
        $offering = Offering::factory()->create();
        $period = AssessmentPeriod::factory()->create();
        $enrollment = Enrollment::factory()->create();
        $this->actingAs(User::factory()->coordinator()->create());
        $this->get('/admin/offerings/'.$offering->id.'/periods/'.$period->id.'/edit')->assertNotFound();
        $this->put('/admin/offerings/'.$offering->id.'/periods/'.$period->id, ['number' => 2, 'starts_on' => '2026-05-01', 'ends_on' => '2026-08-31'])->assertNotFound();
        $this->get('/admin/offerings/'.$offering->id.'/enrollments/'.$enrollment->id.'/edit')->assertNotFound();
        $this->put('/admin/offerings/'.$offering->id.'/enrollments/'.$enrollment->id, ['active_from' => '2026-01-01', 'active_to' => '2026-10-01', 'reason' => 'Retiro solicitado'])->assertNotFound();
    }

    public function test_group_identity_cannot_change_after_enrollment(): void
    {
        $enrollment = Enrollment::factory()->create();
        $offering = $enrollment->offering;
        $this->actingAs(User::factory()->coordinator()->create())->put('/admin/offerings/'.$offering->id, [...$offering->only(['cycle_id', 'subject_id', 'teacher_id', 'capacity']), 'group_code' => 'OTHER', 'reason' => 'Cambio de grupo'])->assertSessionHasErrors('group_code');
        $this->assertSame('01', $offering->fresh()->group_code);
    }

    public function test_capacity_is_enforced_for_future_enrollments_and_capacity_edits(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 13));
        $offering = Offering::factory()->create(['capacity' => 2]);
        Enrollment::factory()->count(2)->create(['offering_id' => $offering->id, 'active_from' => '2026-11-01']);
        $student = User::factory()->create();
        $this->actingAs(User::factory()->coordinator()->create());
        $this->post('/admin/offerings/'.$offering->id.'/enrollments', ['student_id' => $student->id, 'active_from' => '2026-11-01'])->assertSessionHasErrors('student_id');
        $this->put('/admin/offerings/'.$offering->id, [...$offering->only(['cycle_id', 'subject_id', 'teacher_id', 'group_code']), 'capacity' => 1, 'reason' => 'Reducir cupo'])->assertSessionHasErrors('capacity');
        $this->assertDatabaseCount('enrollments', 2);
    }

    public function test_withdrawal_is_inclusive_and_releases_the_seat_the_next_day(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 13));
        $offering = Offering::factory()->create(['capacity' => 1]);
        $enrollment = Enrollment::factory()->create(['offering_id' => $offering->id]);
        $student = User::factory()->create();
        $this->actingAs(User::factory()->coordinator()->create());
        $this->put('/admin/offerings/'.$offering->id.'/enrollments/'.$enrollment->id, ['active_from' => '2026-01-01', 'active_to' => '2026-10-13', 'reason' => 'Retiro voluntario'])->assertSessionHasNoErrors();
        $this->post('/admin/offerings/'.$offering->id.'/enrollments', ['student_id' => $student->id, 'active_from' => '2026-10-14'])->assertSessionHasErrors('student_id');
        $this->travelTo(now()->setDate(2026, 10, 14));
        $this->post('/admin/offerings/'.$offering->id.'/enrollments', ['student_id' => $student->id, 'active_from' => '2026-10-14'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('enrollments', 2);
        $this->assertDatabaseHas('enrollments', ['id' => $enrollment->id]);
    }

    public function test_enrollment_dates_outside_cycle_and_duplicate_student_are_rejected(): void
    {
        $enrollment = Enrollment::factory()->create();
        $offering = $enrollment->offering;
        $student = User::factory()->create();
        $this->actingAs(User::factory()->coordinator()->create());
        $this->post('/admin/offerings/'.$offering->id.'/enrollments', ['student_id' => $student->id, 'active_from' => '2025-12-31'])->assertSessionHasErrors('active_from');
        $this->post('/admin/offerings/'.$offering->id.'/enrollments', ['student_id' => $enrollment->student_id, 'active_from' => '2026-01-01'])->assertSessionHasErrors('student_id');
        $this->assertDatabaseCount('enrollments', 1);
    }

    public function test_enrollment_edit_cannot_exclude_existing_attendance(): void
    {
        $session = ClassSession::factory()->create(['starts_at' => '2026-06-15 14:00:00+00', 'ends_at' => '2026-06-15 15:00:00+00']);
        $enrollment = Enrollment::factory()->create(['offering_id' => $session->offering_id]);
        Attendance::create(['offering_id' => $session->offering_id, 'enrollment_id' => $enrollment->id, 'session_id' => $session->id, 'status' => 'present', 'method' => 'manual', 'recorded_by' => $session->offering->teacher_id, 'recorded_at' => now()]);
        $this->actingAs(User::factory()->coordinator()->create())->put('/admin/offerings/'.$session->offering_id.'/enrollments/'.$enrollment->id, ['active_from' => '2026-01-01', 'active_to' => '2026-06-01', 'reason' => 'Retiro solicitado'])->assertSessionHasErrors('active_to');
        $this->assertNull($enrollment->fresh()->active_to);
    }

    public function test_period_edit_cannot_exclude_its_existing_sessions(): void
    {
        $session = ClassSession::factory()->create(['starts_at' => '2026-06-15 14:00:00+00', 'ends_at' => '2026-06-15 15:00:00+00']);
        $this->actingAs(User::factory()->coordinator()->create())->put('/admin/offerings/'.$session->offering_id.'/periods/'.$session->period_id, ['number' => 1, 'starts_on' => '2026-01-01', 'ends_on' => '2026-05-31', 'reason' => 'Ajuste de fechas'])->assertSessionHasErrors('starts_on');
        $this->assertSame('2026-12-31', $session->period->fresh()->ends_on->toDateString());
    }

    public function test_period_must_contain_both_dates_of_an_overnight_session(): void
    {
        $session = ClassSession::factory()->create(['starts_at' => '2026-06-16 05:00:00+00', 'ends_at' => '2026-06-16 07:00:00+00']);
        $this->actingAs(User::factory()->coordinator()->create())->put('/admin/offerings/'.$session->offering_id.'/periods/'.$session->period_id, ['number' => 1, 'starts_on' => '2026-01-01', 'ends_on' => '2026-06-15', 'reason' => 'Ajuste del límite'])->assertSessionHasErrors('starts_on');
    }

    public function test_unchanged_inactive_subject_does_not_require_a_new_reason(): void
    {
        $subject = Subject::factory()->create(['active' => false]);
        $this->actingAs(User::factory()->coordinator()->create())->put('/admin/subjects/'.$subject->id, ['code' => $subject->code, 'name' => $subject->name, 'active' => 0])->assertRedirect('/admin/subjects')->assertSessionHasNoErrors();
    }

    public function test_closed_cycle_and_period_are_read_only(): void
    {
        $offering = Offering::factory()->create();
        $period = AssessmentPeriod::factory()->create(['offering_id' => $offering->id, 'state' => 'closed']);
        $this->actingAs(User::factory()->coordinator()->create());
        $this->put('/admin/offerings/'.$offering->id.'/periods/'.$period->id, ['number' => 1, 'starts_on' => '2026-01-01', 'ends_on' => '2026-11-30', 'reason' => 'Intento de ajuste'])->assertSessionHasErrors('number');
        $offering->cycle->update(['status' => 'closed']);
        $this->put('/admin/cycles/'.$offering->cycle_id, ['code' => $offering->cycle->code, 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31', 'status' => 'active', 'reason' => 'Intento de reapertura'])->assertSessionHasErrors('cycle_id');
        $this->post('/admin/offerings/'.$offering->id.'/periods', ['number' => 2, 'starts_on' => '2026-01-01', 'ends_on' => '2026-01-31'])->assertSessionHasErrors('cycle_id');
    }

    public function test_cycle_dates_cannot_exclude_academic_records_and_open_periods_prevent_closure(): void
    {
        $period = AssessmentPeriod::factory()->create();
        $cycle = $period->offering->cycle;
        $this->actingAs(User::factory()->coordinator()->create());
        $this->put('/admin/cycles/'.$cycle->id, ['code' => $cycle->code, 'starts_on' => '2026-02-01', 'ends_on' => '2026-12-31', 'status' => 'active', 'reason' => 'Ajuste de fecha'])->assertSessionHasErrors('starts_on');
        $this->put('/admin/cycles/'.$cycle->id, ['code' => $cycle->code, 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31', 'status' => 'closed', 'reason' => 'Cerrar ciclo'])->assertSessionHasErrors('status');
    }
}
