<?php

namespace Tests\Feature;

use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Offering;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    public function test_student_and_teacher_cannot_open_coordinator_pages(): void
    {
        foreach ([User::factory()->create(), User::factory()->teacher()->create()] as $user) {
            $this->withSession(['password_hash_web' => $user->password])->actingAs($user)->get('/admin/users')->assertForbidden();
            $this->actingAs($user)->get('/admin/cycles')->assertForbidden();
        }
    }

    public function test_teacher_can_only_open_and_list_their_groups(): void
    {
        $teacher = User::factory()->teacher()->create();
        $own = Offering::factory()->create(['teacher_id' => $teacher->id]);
        $other = Offering::factory()->create();
        $this->actingAs($teacher)->get('/offerings')->assertOk()->assertSee($own->subject->name)->assertDontSee($other->subject->name);
        $this->get('/offerings/'.$own->id)->assertOk();
        $this->get('/offerings/'.$other->id)->assertForbidden();
    }

    public function test_student_sees_only_their_enrollment_in_a_shared_group(): void
    {
        $student = User::factory()->create();
        $other = User::factory()->create(['name' => 'PERSONA_AJENA_SECRETA']);
        $offering = Offering::factory()->create();
        Enrollment::factory()->create(['offering_id' => $offering->id, 'student_id' => $student->id]);
        Enrollment::factory()->create(['offering_id' => $offering->id, 'student_id' => $other->id]);
        $this->actingAs($student)->get('/offerings/'.$offering->id)->assertOk()->assertSee($student->name)->assertDontSee('PERSONA_AJENA_SECRETA')->assertDontSee($other->email);
    }

    public function test_unenrolled_student_cannot_open_a_group(): void
    {
        $offering = Offering::factory()->create();
        $this->actingAs(User::factory()->create())->get('/offerings/'.$offering->id)->assertForbidden();
    }

    public function test_coordinator_can_open_all_groups_and_users(): void
    {
        $offering = Offering::factory()->create();
        $this->actingAs(User::factory()->coordinator()->create())->get('/offerings/'.$offering->id)->assertOk();
        $this->get('/admin/users')->assertOk();
        $this->get('/admin/cycles')->assertOk();
    }

    public function test_pending_modules_still_enforce_role_authorization(): void
    {
        $this->actingAs(User::factory()->create())->get('/modules/credentials')->assertForbidden();
        $this->get('/modules/imports')->assertForbidden();
        $this->get('/modules/reports')->assertForbidden();
        $this->get('/modules/attendance')->assertRedirect('/sessions');
        $this->get('/modules/unknown')->assertNotFound();
    }

    public function test_mobile_reader_page_is_restricted_to_teachers_and_coordinator(): void
    {
        $this->actingAs(User::factory()->create())->get('/teacher/terminal')->assertForbidden();
        $teacher = User::factory()->teacher()->create();
        $this->withSession(['password_hash_web' => $teacher->password])->actingAs($teacher)->get('/teacher/terminal')->assertOk()->assertSee('no registra asistencias ni asigna etiquetas');
    }

    public function test_session_policy_denies_an_unassigned_teacher(): void
    {
        $session = ClassSession::factory()->create();
        $teacher = User::factory()->teacher()->create();
        $this->assertFalse(Gate::forUser($teacher)->allows('view', $session));
        $this->assertTrue(Gate::forUser($session->offering->teacher)->allows('view', $session));
    }
}
