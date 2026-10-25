<?php

namespace Tests\Feature;

use App\Actions\Academic\AcademicRegistry;
use App\Actions\Academic\CsvImporter;
use App\Actions\Academic\UserManager;
use App\Actions\Attendance\ClosureWorkflow;
use App\Actions\Attendance\CredentialManager;
use App\Actions\Attendance\PortalManager;
use App\Actions\Attendance\RegisterAttendance;
use App\Actions\Attendance\SessionWorkflow;
use App\Models\AssessmentPeriod;
use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Offering;
use App\Models\User;
use App\Queries\AttendanceSummary;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class A05IntegrationTest extends TestCase
{
    private function scenario(): array
    {
        $this->travelTo(now('UTC')->setDateTime(2026, 10, 24, 16, 30));
        $period = AssessmentPeriod::factory()->create();
        $enrollment = Enrollment::factory()->create(['offering_id' => $period->offering_id]);
        $session = ClassSession::factory()->create(['offering_id' => $period->offering_id, 'period_id' => $period->id,
            'starts_at' => now()->subMinutes(30), 'ends_at' => now()->addMinutes(30), 'modality' => 'online']);

        return [$period, $enrollment, $session, User::factory()->coordinator()->create()];
    }

    private function csv(string $kind, string $rows): string
    {
        $this->post('/admin/imports/preview', ['kind' => $kind, 'file' => UploadedFile::fake()->createWithContent('a05.csv', $rows),
            'initial_password' => 'A05-provisional-password!', 'initial_password_confirmation' => 'A05-provisional-password!'])->assertSessionHasNoErrors();

        return session('academic_import_id');
    }

    public function test_two_tabs_confirm_their_own_reviews_and_retries_do_not_duplicate_accounts(): void
    {
        $this->actingAs(User::factory()->coordinator()->create());
        $a = $this->csv('teachers', "name,email\nA05 Uno,a05-one@example.test\n");
        $b = $this->csv('teachers', "name,email\nA05 Dos,a05-two@example.test\n");
        $this->assertNotSame($a, $b);
        $this->get('/admin/imports?preview='.$a)->assertSee('a05-one@example.test')->assertDontSee('a05-two@example.test');
        $this->get('/admin/imports?preview='.$b)->assertSee('a05-two@example.test')->assertDontSee('a05-one@example.test');
        $this->post('/admin/imports/confirm', ['nonce' => $a])->assertSessionHasNoErrors();
        $this->post('/admin/imports/confirm', ['nonce' => $a])->assertSessionHasErrors('file');
        $this->post('/admin/imports/confirm', ['nonce' => $b])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('users', 3);
        foreach (User::where('role', 'teacher')->get() as $user) {
            $this->assertTrue($user->must_change_password);
            $this->assertTrue(Hash::check('A05-provisional-password!', $user->password));
        }
        $this->assertStringNotContainsString('A05-provisional-password!', json_encode(AuditLog::all()));
    }

    public function test_review_cannot_be_confirmed_by_another_owner_or_at_its_expiry(): void
    {
        $owner = User::factory()->coordinator()->create();
        $this->actingAs($owner);
        $nonce = $this->csv('teachers', "name,email\nA05 Uno,a05-owner@example.test\n");
        $other = User::factory()->coordinator()->create();
        $this->withSession(['password_hash_web' => $other->password])->actingAs($other)->post('/admin/imports/confirm', ['nonce' => $nonce])->assertSessionHasErrors('file');
        $this->withSession(['password_hash_web' => $owner->password])->actingAs($owner);
        $this->travel(15)->minutes();
        $this->post('/admin/imports/confirm', ['nonce' => $nonce])->assertSessionHasErrors('file');
        $this->assertDatabaseMissing('users', ['email' => 'a05-owner@example.test']);
    }

    public function test_preview_query_is_typed_and_expired_identity_has_a_clear_message(): void
    {
        $this->actingAs(User::factory()->coordinator()->create());
        $this->getJson('/admin/imports?preview[]=invalid')->assertUnprocessable()->assertJsonValidationErrors('preview');
        $this->get('/admin/imports?preview='.str_repeat('x', 40))->assertOk()->assertSee('La revisión expiró');
    }

    public function test_study_level_rejects_numeric_coercions_and_boolean_values_in_csv_and_filters(): void
    {
        $this->actingAs(User::factory()->coordinator()->create());
        foreach (['/admin/students/create', '/admin/students/batch'] as $form) {
            $this->get($form)->assertOk()->assertSee('value="egresado"', escape: false)->assertDontSee('value="6"', escape: false);
        }
        foreach (['1.0', '01', '1e0', '6'] as $value) {
            $this->getJson('/admin/students?study_year='.$value)->assertUnprocessable()->assertJsonValidationErrors('study_year');
            $review = app(CsvImporter::class)->review('students', [['line' => 2, 'fields' => [
                'name' => 'A05 Tipos', 'email' => 'a05-types@example.test', 'student_code' => 'A05T', 'entry_year' => '2026', 'study_year' => $value,
            ]]]);
            $this->assertFalse($review['valid']);
        }
        $this->postJson('/admin/students/batch', ['entry_year' => 2026, 'study_year' => true])->assertUnprocessable()->assertJsonValidationErrors('study_year');
        $this->assertDatabaseCount('users', 1);
    }

    public function test_year_review_detects_a_concurrent_edit_and_rolls_back_the_entire_batch(): void
    {
        $a = User::factory()->create(['student_code' => 'A05A', 'study_year' => 1]);
        $b = User::factory()->create(['student_code' => 'A05B', 'study_year' => 2]);
        $this->actingAs(User::factory()->coordinator()->create());
        $nonce = $this->csv('student_years', "student_code,entry_year,study_year\nA05A,2026,3\nA05B,2025,4\n");
        $b->update(['study_year' => 5]);
        $this->post('/admin/imports/confirm', ['nonce' => $nonce])->assertSessionHasErrors('file');
        $this->assertSame(1, $a->fresh()->study_year);
        $this->assertSame(5, $b->fresh()->study_year);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_imported_account_is_blocked_until_a_different_password_is_set(): void
    {
        $user = User::factory()->create(['must_change_password' => true]);
        $this->actingAs($user)->get('/dashboard')->assertRedirect('/profile');
        $this->postJson('/notifications/unknown/read', [])->assertForbidden()->assertJsonPath('code', 'password_change_required');
        $this->get('/profile')->assertOk();
        $this->put('/profile/password', ['current_password' => 'Test-password-2026!', 'password' => 'A05-new-private-password!', 'password_confirmation' => 'A05-new-private-password!'])->assertRedirect('/login');
        $this->assertGuest();
        $this->assertFalse($user->fresh()->must_change_password);
        $this->post('/login', ['email' => $user->email, 'password' => 'A05-new-private-password!'])->assertRedirect('/dashboard');
        $this->get('/dashboard')->assertOk();
    }

    public function test_graduation_is_explicit_filterable_and_preserves_existing_history(): void
    {
        [$period, $enrollment, , $admin] = $this->scenario();
        $user = $enrollment->student;
        app(UserManager::class)->setStudentYears($admin, $user, 2021, 'egresado');
        $this->assertTrue($user->fresh()->graduated);
        $this->assertNull($user->fresh()->study_year);
        $this->actingAs($admin)->get('/admin/students?study_year=egresado')->assertSee($user->name);
        $this->get('/admin/nfc-credentials?study_year=egresado')->assertOk()->assertSee($user->student_code);
        $this->withSession(['password_hash_web' => $user->password])->actingAs($user->fresh())->get('/offerings/'.$period->offering_id)->assertOk();
        $this->assertDatabaseHas('enrollments', ['id' => $enrollment->id]);
        $other = Offering::factory()->create();
        try {
            app(AcademicRegistry::class)->saveEnrollment($admin, $other, ['student_id' => $user->id, 'active_from' => '2026-01-01']);
            $this->fail('Egresado no debe recibir una matrícula nueva.');
        } catch (ValidationException) {
            $this->assertDatabaseMissing('enrollments', ['offering_id' => $other->id, 'student_id' => $user->id]);
        }
    }

    public function test_provisional_account_cannot_bypass_password_change_through_an_attendance_invitation(): void
    {
        [$period, $enrollment, $session] = $this->scenario();
        app(SessionWorkflow::class)->transition($period->offering->teacher, $session, 'start');
        $portal = app(PortalManager::class)->open($period->offering->teacher, $session, ['link', 'qr', 'nfc_tag'], 5);
        $student = $enrollment->student;
        $student->update(['must_change_password' => true]);
        $this->actingAs($student)->get('/check-in/'.$portal['portal']->id)->assertOk()->assertSee('Cambiar contraseña')->assertDontSee('data-confirm-checkin', escape: false);
        $this->postJson('/check-in/'.$portal['portal']->id, ['event_id' => (string) Str::uuid(), 'portal_token' => $portal['token'], 'method' => 'link'])
            ->assertForbidden()->assertJsonPath('code', 'password_change_required');
        $this->put('/profile/password', ['current_password' => 'Test-password-2026!', 'password' => 'A05-private-invitation-password!', 'password_confirmation' => 'A05-private-invitation-password!'])->assertRedirect('/login');
        $this->travel(5)->minutes();
        $this->post('/login', ['email' => $student->email, 'password' => 'A05-private-invitation-password!'])->assertRedirect('/dashboard');
        $this->postJson('/check-in/'.$portal['portal']->id, ['event_id' => (string) Str::uuid(), 'portal_token' => $portal['token'], 'method' => 'link'])->assertConflict();
        $this->assertDatabaseCount('attendances', 0);
        $this->assertDatabaseCount('report_versions', 0);
    }

    public function test_csv_supports_egresado_and_rejects_sixth_year_without_partial_accounts(): void
    {
        $this->actingAs(User::factory()->coordinator()->create());
        $nonce = $this->csv('students', "\xEF\xBB\xBFname,email,student_code,entry_year,study_year\nEgresado,a05-grad@example.test,A05G,2021,egresado\n");
        $this->post('/admin/imports/confirm', ['nonce' => $nonce])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['student_code' => 'A05G', 'graduated' => true, 'study_year' => null]);
        $nonce = $this->csv('students', "name,email,student_code,entry_year,study_year\nVálido,a05-valid@example.test,A05V,2026,1\nInválido,a05-six@example.test,A056,2021,6\n");
        $this->post('/admin/imports/confirm', ['nonce' => $nonce])->assertSessionHasErrors('file');
        $this->assertDatabaseMissing('users', ['student_code' => 'A05V']);
    }

    public function test_expiring_portal_does_not_finish_class_and_scheduler_ends_it_exactly_at_ends_at(): void
    {
        [$period, $enrollment, $session] = $this->scenario();
        app(SessionWorkflow::class)->transition($period->offering->teacher, $session, 'start');
        $portal = app(PortalManager::class)->open($period->offering->teacher, $session, ['link'], 1);
        $this->travel(1)->minutes();
        $this->artisan('attendance:process-closures')->assertSuccessful();
        $this->assertSame('in_progress', $session->fresh()->state->value);
        $data = ['event_id' => (string) Str::uuid(), 'portal_id' => $portal['portal']->id, 'portal_token' => $portal['token']];
        $this->assertSame(409, app(RegisterAttendance::class)->record($enrollment->student, $session, 'link', $data)['http']);
        $this->travelTo($session->ends_at->subSecond());
        $this->assertFalse(app(SessionWorkflow::class)->finishOverdue($session));
        $this->travelTo($session->ends_at);
        $this->artisan('attendance:process-closures')->assertSuccessful();
        $this->assertSame('completed', $session->fresh()->state->value);
        $this->assertNotNull($portal['portal']->fresh()->closed_at);
        $this->assertDatabaseHas('attendances', ['enrollment_id' => $enrollment->id, 'status' => 'absent']);
        $this->artisan('attendance:process-closures')->assertSuccessful();
        $this->assertSame(1, AuditLog::where('action', 'session.auto_finished')->count());
        $this->artisan('attendance:scheduler-status')->assertSuccessful();
        $this->travel(181)->seconds();
        $this->artisan('attendance:scheduler-status')->assertFailed();
    }

    public function test_scheduler_delay_blocks_new_writes_but_replays_completed_event(): void
    {
        [$period, $enrollment, $session] = $this->scenario();
        app(SessionWorkflow::class)->transition($period->offering->teacher, $session, 'start');
        $portal = app(PortalManager::class)->open($period->offering->teacher, $session, ['link'], 60);
        $data = ['event_id' => (string) Str::uuid(), 'portal_id' => $portal['portal']->id, 'portal_token' => $portal['token']];
        $this->assertSame(201, app(RegisterAttendance::class)->record($enrollment->student, $session, 'link', $data)['http']);
        $this->travelTo($session->ends_at);
        $this->assertTrue(app(RegisterAttendance::class)->record($enrollment->student, $session, 'link', $data)['body']['replayed']);
        $this->assertSame(409, app(RegisterAttendance::class)->record($enrollment->student, $session, 'link', [...$data, 'event_id' => (string) Str::uuid()])['http']);
        $this->assertSame('in_progress', $session->fresh()->state->value);
        $this->assertDatabaseCount('attendances', 1);
    }

    public function test_nfc_confirmation_retries_preserve_one_activation_and_never_reactivate_revoked(): void
    {
        [, $enrollment, , $admin] = $this->scenario();
        $manager = app(CredentialManager::class);
        $a = $manager->issue($admin, $enrollment->student);
        $manager->confirm($admin, $a['credential'], $a['token']);
        $manager->confirm($admin, $a['credential'], $a['token']);
        $this->assertSame(1, AuditLog::where('action', 'credential.activated')->count());
        $b = $manager->issue($admin, $enrollment->student);
        $this->assertSame('active', $a['credential']->fresh()->state);
        $manager->confirm($admin, $b['credential'], $b['token']);
        try {
            $manager->confirm($admin, $a['credential'], $a['token']);
            $this->fail('No se reactiva una credencial revocada.');
        } catch (ValidationException) {
            $this->assertSame('revoked', $a['credential']->fresh()->state);
            $this->assertSame('active', $b['credential']->fresh()->state);
        }
    }

    public function test_shared_tag_is_self_registration_and_uses_a02_location_without_persisting_coordinates(): void
    {
        [$period, $enrollment, $session] = $this->scenario();
        $session->update(['modality' => 'in_person']);
        app(SessionWorkflow::class)->transition($period->offering->teacher, $session, 'start');
        $location = ['latitude' => 13.71823, 'longitude' => -89.20235, 'accuracy' => 2, 'timestamp' => now()->getTimestampMs()];
        $portal = app(PortalManager::class)->open($period->offering->teacher, $session, ['nfc_tag'], 5, $location);
        $data = ['event_id' => (string) Str::uuid(), 'portal_token' => $portal['token'], 'method' => 'nfc_tag'];
        $this->actingAs($enrollment->student)->postJson('/check-in/'.$portal['portal']->id, $data)->assertUnprocessable()->assertJsonPath('code', 'geo_unavailable');
        $this->postJson('/check-in/'.$portal['portal']->id, [...$data, 'event_id' => (string) Str::uuid(), 'student_id' => 999,
            'location' => [...$location, 'portal_id' => $portal['portal']->id]])->assertCreated()->assertJsonPath('student_id', $enrollment->student_id);
        $this->assertDatabaseHas('attendances', ['method' => 'nfc_tag', 'enrollment_id' => $enrollment->id]);
        $this->assertStringNotContainsString('13.71823', json_encode(DB::table('attendance_events')->get()));
        $this->assertStringNotContainsString('13.71823', json_encode(AuditLog::all()));
    }

    public function test_teacher_reassignment_rejects_future_conflicts_and_running_class_then_transfers_permissions(): void
    {
        [$period, $enrollment, $session, $admin] = $this->scenario();
        $old = $period->offering->teacher;
        $other = Offering::factory()->create();
        $otherPeriod = AssessmentPeriod::factory()->create(['offering_id' => $other->id]);
        $conflict = ClassSession::factory()->create(['offering_id' => $other->id, 'period_id' => $otherPeriod->id, 'starts_at' => $session->starts_at, 'ends_at' => $session->ends_at]);
        $data = [...$period->offering->only(['cycle_id', 'subject_id', 'group_code', 'capacity']), 'teacher_id' => $other->teacher_id, 'reason' => 'Reasignación A05 verificada'];
        try {
            app(AcademicRegistry::class)->saveOffering($admin, $data, $period->offering);
            $this->fail('Debe detectar conflicto docente.');
        } catch (ValidationException) {
            $this->assertSame($old->id, $period->offering->fresh()->teacher_id);
        }
        $conflict->update(['state' => 'cancelled', 'change_reason' => 'Cancelación sintética A05']);
        app(SessionWorkflow::class)->transition($old, $session, 'start');
        try {
            app(AcademicRegistry::class)->saveOffering($admin, $data, $period->offering);
            $this->fail('No se reasigna una clase en curso.');
        } catch (ValidationException) {
            $this->assertSame($old->id, $period->offering->fresh()->teacher_id);
        }
        $portal = app(PortalManager::class)->open($old, $session, ['manual'], 10);
        app(SessionWorkflow::class)->transition($old, $session, 'finish', 'Fin anticipado para revisión');
        app(AcademicRegistry::class)->saveOffering($admin, $data, $period->offering);
        $this->withSession(['password_hash_web' => $old->password])->actingAs($old)->get('/sessions/'.$session->id)->assertForbidden();
        $this->withSession(['password_hash_web' => $other->teacher->password])->actingAs($other->teacher)->get('/sessions/'.$session->id)->assertOk();
        $this->assertNotNull($portal['portal']->fresh()->closed_at);
        $this->assertDatabaseHas('audit_logs', ['entity_id' => (string) $session->id, 'action' => 'session.start', 'actor_id' => $old->id]);
    }

    public function test_only_submitted_form_receives_old_reason_and_field_errors(): void
    {
        [, , , $admin] = $this->scenario();
        $user = User::factory()->create();
        $url = route('admin.users.password', $user);
        $scope = hash('sha256', 'admin/users/form.blade.php:2|'.$url);
        $this->actingAs($admin)->from('/admin/users/'.$user->id.'/edit')->put('/admin/users/'.$user->id.'/password', [
            '_form' => $scope, 'reason' => 'MOTIVO_A05_SOLO_RESET', 'password' => 'short', 'password_confirmation' => 'short',
        ])->assertSessionHasErrors('password');
        $html = $this->get('/admin/users/'.$user->id.'/edit')->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, 'value="MOTIVO_A05_SOLO_RESET"'));
        $this->assertStringNotContainsString('value="short"', $html);
    }

    public function test_batch_review_lookup_cost_does_not_grow_per_row(): void
    {
        $importer = app(CsvImporter::class);
        $rows = [];
        for ($i = 0; $i < 500; $i++) {
            $rows[] = ['line' => $i + 2, 'fields' => ['name' => 'A05 '.$i, 'email' => 'a05-'.$i.'@example.test']];
        }
        DB::enableQueryLog();
        $small = $importer->review('teachers', array_slice($rows, 0, 1));
        $one = count(DB::getQueryLog());
        DB::flushQueryLog();
        $large = $importer->review('teachers', $rows);
        $many = count(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertTrue($small['valid'] && $large['valid']);
        $this->assertSame($one, $many);
        $this->assertLessThanOrEqual(2, $many);
    }

    public function test_eight_completed_and_two_never_started_produce_one_hundred_percent_and_frozen_snapshot(): void
    {
        [$period, $enrollment, $session, $admin] = $this->scenario();
        $period->update(['ends_on' => '2026-10-24']);
        for ($i = 0; $i < 8; $i++) {
            $s = ClassSession::factory()->create(['period_id' => $period->id, 'offering_id' => $period->offering_id,
                'starts_at' => now()->subDays($i + 1)->subHour(), 'ends_at' => now()->subDays($i + 1), 'state' => 'completed', 'started_at' => now()->subDays($i + 1)->subHour(), 'finalized_at' => now()->subDays($i + 1)]);
            Attendance::create(['session_id' => $s->id, 'offering_id' => $period->offering_id, 'enrollment_id' => $enrollment->id, 'status' => 'present', 'recorded_by' => $period->offering->teacher_id, 'recorded_at' => $s->starts_at]);
        }
        ClassSession::factory()->create(['period_id' => $period->id, 'offering_id' => $period->offering_id, 'starts_at' => $session->starts_at, 'ends_at' => $session->ends_at]);
        $this->travelTo(now()->addDay()->startOfDay()->addHours(6));
        app(ClosureWorkflow::class)->closePeriod($admin, $period);
        $this->assertSame('closed', $period->fresh()->state);
        $this->assertSame(2, $period->sessions()->where('state', 'unstarted')->count());
        $result = app(AttendanceSummary::class)->forEnrollment($enrollment->student, $enrollment, $period);
        $this->assertEquals(100, $result['percentage']);
        $this->assertSame(['numerator' => 8, 'denominator' => 8], $result['fraction']);
        $this->assertSame('snapshot', $result['source']);
        $hash = $period->fresh()->reports()->sole()->snapshot_hash;
        app(UserManager::class)->setStudentYears($admin, $enrollment->student, 2021, 'egresado');
        $this->assertSame($hash, $period->fresh()->reports()->sole()->snapshot_hash);
    }
}
