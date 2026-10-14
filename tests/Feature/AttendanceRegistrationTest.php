<?php

namespace Tests\Feature;

use App\Actions\Attendance\CredentialManager;
use App\Actions\Attendance\PortalManager;
use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\AttendanceEvent;
use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Support\Str;
use Tests\TestCase;

class AttendanceRegistrationTest extends TestCase
{
    private function scenario(): array
    {
        $this->travelTo(now()->setDateTime(2026, 10, 13, 14, 10, 0));
        $session = ClassSession::factory()->create(['starts_at' => '2026-10-13T14:00:00Z', 'ends_at' => '2026-10-13T15:00:00Z', 'state' => 'in_progress']);
        $enrollment = Enrollment::factory()->create(['offering_id' => $session->offering_id, 'student_id' => User::factory()->create(['student_code' => 'NFC001'])->id]);
        $portal = app(PortalManager::class)->open($session->offering->teacher, $session, ['nfc', 'qr', 'link', 'manual'], 15);

        return [$session, $enrollment, $portal];
    }

    private function credential(User $student): array
    {
        $actor = User::factory()->coordinator()->create();
        $result = app(CredentialManager::class)->issue($actor, $student);
        app(CredentialManager::class)->confirm($actor, $result['credential'], $result['token']);

        return $result;
    }

    public function test_manual_registration_creates_one_row_and_replays_the_same_event(): void
    {
        [$session,$enrollment,$portal] = $this->scenario();
        $data = ['event_id' => (string) Str::uuid(), 'portal_id' => $portal['portal']->id, 'student_id' => $enrollment->student_id, 'reason' => 'Registro supervisado por el docente'];
        $this->actingAs($session->offering->teacher)->postJson('/sessions/'.$session->id.'/manual-attendance', $data)->assertCreated()->assertJsonPath('status', 'recorded');
        $this->postJson('/sessions/'.$session->id.'/manual-attendance', $data)->assertOk()->assertJsonPath('replayed', true);
        $this->postJson('/sessions/'.$session->id.'/manual-attendance', [...$data, 'event_id' => (string) Str::uuid()])->assertOk()->assertJsonPath('status', 'already_recorded');
        $this->assertDatabaseCount('attendances', 1);
        $this->assertDatabaseCount('attendance_events', 2);
    }

    public function test_event_id_cannot_be_reused_with_changed_payload(): void
    {
        [$session,$enrollment,$portal] = $this->scenario();
        $data = ['event_id' => (string) Str::uuid(), 'portal_id' => $portal['portal']->id, 'student_id' => $enrollment->student_id, 'reason' => 'Registro supervisado'];
        $this->actingAs($session->offering->teacher)->postJson('/sessions/'.$session->id.'/manual-attendance', $data)->assertCreated();
        $this->postJson('/sessions/'.$session->id.'/manual-attendance', [...$data, 'reason' => 'Otro motivo diferente'])->assertConflict();
        $this->assertDatabaseCount('attendances', 1);
    }

    public function test_uuid_case_and_numeric_form_values_keep_the_same_retry_identity(): void
    {
        [$session, $enrollment, $portal] = $this->scenario();
        $event = 'ABCDEFAB-1234-4ABC-8ABC-ABCDEFABCDEF';
        $data = ['event_id' => $event, 'portal_id' => (string) $portal['portal']->id, 'student_id' => (string) $enrollment->student_id, 'reason' => 'Registro supervisado'];
        $this->actingAs($session->offering->teacher)->postJson('/sessions/'.$session->id.'/manual-attendance', $data)->assertCreated();
        $this->postJson('/sessions/'.$session->id.'/manual-attendance', [...$data, 'event_id' => strtolower($event), 'portal_id' => $portal['portal']->id, 'student_id' => $enrollment->student_id])->assertOk()->assertJsonPath('replayed', true);
        $this->assertDatabaseCount('attendance_events', 1);
        $this->assertDatabaseCount('attendances', 1);
    }

    public function test_nfc_uses_the_active_credential_and_owned_reader(): void
    {
        [$session,$enrollment,$portal] = $this->scenario();
        $credential = $this->credential($enrollment->student);
        $reader = app(PortalManager::class)->reader($session->offering->teacher, $portal['portal'], 'Teléfono de prueba');
        $data = ['event_id' => (string) Str::uuid(), 'reader_session_id' => $reader->id, 'credential_token' => $credential['token']];
        $this->actingAs($session->offering->teacher)->postJson('/teacher/nfc-readings', $data)->assertCreated()->assertJsonPath('student_code', 'NFC001');
        $this->assertSame('nfc', Attendance::sole()->method->value);
        $this->assertStringNotContainsString($credential['token'], AttendanceEvent::sole()->toJson());
        $other = User::factory()->teacher()->create();
        $this->withSession(['password_hash_web' => $other->password])->actingAs($other)->postJson('/teacher/nfc-readings', [...$data, 'event_id' => (string) Str::uuid()])->assertForbidden();
    }

    public function test_revoked_credential_is_rejected_without_creating_attendance(): void
    {
        [$session,$enrollment,$portal] = $this->scenario();
        $credential = $this->credential($enrollment->student);
        $credential['credential']->update(['state' => 'revoked']);
        $reader = app(PortalManager::class)->reader($session->offering->teacher, $portal['portal'], 'Test');
        $this->actingAs($session->offering->teacher)->postJson('/teacher/nfc-readings', ['event_id' => (string) Str::uuid(), 'reader_session_id' => $reader->id, 'credential_token' => $credential['token']])->assertUnprocessable()->assertJsonPath('status', 'rejected');
        $this->assertDatabaseCount('attendances', 0);
        $this->assertDatabaseHas('attendance_events', ['outcome' => 'rejected']);
    }

    public function test_student_qr_and_link_only_register_the_authenticated_student(): void
    {
        [$session,$enrollment,$portal] = $this->scenario();
        $other = Enrollment::factory()->create(['offering_id' => $session->offering_id]);
        $this->actingAs($enrollment->student)->postJson('/check-in/'.$portal['portal']->id, ['event_id' => (string) Str::uuid(), 'portal_token' => $portal['token'], 'method' => 'qr', 'student_id' => $other->student_id])->assertCreated();
        $this->assertDatabaseHas('attendances', ['enrollment_id' => $enrollment->id, 'method' => 'qr']);
        $this->assertDatabaseMissing('attendances', ['enrollment_id' => $other->id]);
    }

    public function test_missing_membership_expired_portal_and_disabled_method_are_rejected(): void
    {
        [$session,$enrollment,$portal] = $this->scenario();
        $this->actingAs($session->offering->teacher);
        $other = User::factory()->create();
        $data = ['event_id' => (string) Str::uuid(), 'portal_id' => $portal['portal']->id, 'student_id' => $other->id, 'reason' => 'Revisión de lista'];
        $this->postJson('/sessions/'.$session->id.'/manual-attendance', $data)->assertUnprocessable();
        $portal['portal']->update(['enabled_methods' => ['nfc']]);
        $this->postJson('/sessions/'.$session->id.'/manual-attendance', [...$data, 'event_id' => (string) Str::uuid(), 'student_id' => $enrollment->student_id])->assertConflict();
        $portal['portal']->update(['enabled_methods' => ['manual'], 'expires_at' => now()->subSecond(), 'opens_at' => now()->subMinutes(15)]);
        $this->postJson('/sessions/'.$session->id.'/manual-attendance', [...$data, 'event_id' => (string) Str::uuid(), 'student_id' => $enrollment->student_id])->assertConflict();
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_withdrawn_inactive_or_future_student_cannot_register(): void
    {
        [$session,$enrollment,$portal] = $this->scenario();
        $this->actingAs($session->offering->teacher);
        $data = ['portal_id' => $portal['portal']->id, 'student_id' => $enrollment->student_id, 'reason' => 'Revisión de lista'];
        foreach ([['active_from' => '2026-10-14', 'active_to' => null], ['active_from' => '2026-01-01', 'active_to' => '2026-10-12']] as $dates) {
            $enrollment->update($dates);
            $this->postJson('/sessions/'.$session->id.'/manual-attendance', [...$data, 'event_id' => (string) Str::uuid()])->assertUnprocessable();
        }
        $enrollment->update(['active_from' => '2026-01-01', 'active_to' => null]);
        $enrollment->student->update(['active' => false]);
        $this->postJson('/sessions/'.$session->id.'/manual-attendance', [...$data, 'event_id' => (string) Str::uuid()])->assertUnprocessable();
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_closed_portal_and_finished_class_reject_new_events_but_allow_replay(): void
    {
        [$session,$enrollment,$portal] = $this->scenario();
        $this->actingAs($session->offering->teacher);
        $data = ['event_id' => (string) Str::uuid(), 'portal_id' => $portal['portal']->id, 'student_id' => $enrollment->student_id, 'reason' => 'Registro supervisado'];
        $this->postJson('/sessions/'.$session->id.'/manual-attendance', $data)->assertCreated();
        $portal['portal']->update(['closed_at' => now()]);
        $this->postJson('/sessions/'.$session->id.'/manual-attendance', [...$data, 'event_id' => (string) Str::uuid()])->assertConflict();
        $session->update(['state' => 'completed']);
        $this->postJson('/sessions/'.$session->id.'/manual-attendance', $data)->assertOk()->assertJsonPath('replayed', true);
        $this->postJson('/sessions/'.$session->id.'/manual-attendance', [...$data, 'event_id' => (string) Str::uuid()])->assertConflict();
        $this->assertDatabaseCount('attendances', 1);
    }

    public function test_portal_open_generates_qr_without_storing_plain_token(): void
    {
        [$session,$enrollment,$portal] = $this->scenario();
        $response = $this->actingAs($session->offering->teacher)->postJson('/sessions/'.$session->id.'/portals', ['minutes' => 15, 'methods' => ['qr', 'link']])->assertCreated();
        $this->assertStringStartsWith('data:image/png;base64,', $response->json('qr'));
        $this->assertStringContainsString('#t=', $response->json('url'));
        $this->assertNotNull($portal['portal']->fresh()->closed_at);
    }

    public function test_manual_correction_requires_reason_and_cannot_target_another_session(): void
    {
        [$session,$enrollment,$portal] = $this->scenario();
        $session->update(['state' => 'completed']);
        $attendance = Attendance::create(['offering_id' => $session->offering_id, 'enrollment_id' => $enrollment->id, 'session_id' => $session->id, 'status' => 'absent', 'recorded_by' => $session->offering->teacher_id, 'recorded_at' => now()]);
        $this->actingAs($session->offering->teacher)->post('/sessions/'.$session->id.'/attendances/'.$attendance->id.'/correct', ['status' => 'present'])->assertSessionHasErrors('reason');
        $this->post('/sessions/'.$session->id.'/attendances/'.$attendance->id.'/correct', ['status' => 'present', 'reason' => 'Revisión de lista firmada'])->assertSessionHasNoErrors();
        $this->assertSame(AttendanceStatus::Present, $attendance->fresh()->status);
        $other = ClassSession::factory()->create();
        $this->post('/sessions/'.$other->id.'/attendances/'.$attendance->id.'/correct', ['status' => 'absent', 'reason' => 'Intento ajeno al recurso'])->assertNotFound();
    }
}
