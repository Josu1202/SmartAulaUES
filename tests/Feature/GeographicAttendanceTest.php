<?php

namespace Tests\Feature;

use App\Actions\Attendance\PortalManager;
use App\Actions\Attendance\RegisterAttendance;
use App\Actions\Attendance\SessionWorkflow;
use App\Exceptions\RegistrationRejected;
use App\Models\AttendanceEvent;
use App\Models\AttendancePortal;
use App\Models\AuditLog;
use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\User;
use App\Queries\AttendanceSummary;
use App\Services\Attendance\GeographicAccess;
use Illuminate\Support\Str;
use Tests\TestCase;

class GeographicAttendanceTest extends TestCase
{
    private function loginAs(User $user): static
    {
        return $this->withSession(['password_hash_web' => $user->password])->actingAs($user);
    }

    private function location(array $changes = []): array
    {
        return [...['latitude' => 13.718254, 'longitude' => -89.204321, 'accuracy' => 1, 'timestamp' => now()->getTimestampMs()], ...$changes];
    }

    private function scenario(string $modality = 'in_person'): array
    {
        $this->travelTo(now()->setDateTime(2026, 10, 21, 14, 10, 0));
        $session = ClassSession::factory()->create(['starts_at' => now()->subMinutes(10), 'ends_at' => now()->addMinutes(50), 'state' => 'in_progress', 'modality' => $modality, 'event_attendance_mode' => $modality === 'event' ? 'online' : null]);
        $enrollment = Enrollment::factory()->create(['offering_id' => $session->offering_id]);
        $portal = app(PortalManager::class)->open($session->offering->teacher, $session, ['qr', 'link', 'manual', 'nfc'], 15, $this->location());

        return [$session, $enrollment, $portal];
    }

    private function packet(array $portal, string $method = 'qr', array $changes = []): array
    {
        return [...['event_id' => (string) Str::uuid(), 'method' => $method, 'portal_token' => $portal['token'], 'location' => $this->location(['portal_id' => $portal['portal']->id])], ...$changes];
    }

    public function test_diagnostics_are_available_to_teacher_and_student_without_an_open_portal(): void
    {
        [$session, $enrollment, $portal] = $this->scenario();
        app(PortalManager::class)->close($session->offering->teacher, $portal['portal']);
        $this->loginAs($session->offering->teacher)->get('/sessions/'.$session->id)->assertOk()->assertSee('Medir precisión sin registrar')->assertSee('Cancelar adquisición')->assertSee('10,00 m')->assertSee('30 m')->assertSee('no acredita presencia dentro del aula')->assertDontSee('13.718254');
        $this->loginAs($enrollment->student)->get('/sessions/'.$session->id)->assertOk()->assertSee('data-location-measurement', false)->assertSee('Medir precisión sin registrar')->assertSee('Esta medición no registra asistencia')->assertDontSee('13.718254');
        $this->assertDatabaseCount('attendance_portals', 1);
        $this->assertDatabaseCount('attendance_events', 0);
        $this->assertDatabaseCount('attendances', 0);
        $outsider = User::factory()->create();
        $this->loginAs($outsider)->get('/sessions/'.$session->id)->assertForbidden();
    }

    public function test_checkin_diagnostics_require_authentication_and_presential_modality(): void
    {
        [$session, $enrollment, $portal] = $this->scenario();
        $this->get('/check-in/'.$portal['portal']->id)->assertOk()->assertDontSee('data-measure-location', false);
        $this->loginAs($enrollment->student)->get('/check-in/'.$portal['portal']->id)->assertOk()->assertSee('data-measure-location', false)->assertSee('data-cancel-location', false)->assertSee('10,00 m')->assertSee('30 m')->assertSee('no acredita presencia dentro del aula');
        $session->update(['modality' => 'online']);
        $this->get('/check-in/'.$portal['portal']->id)->assertOk()->assertDontSee('data-measure-location', false);
        $this->get('/sessions/'.$session->id)->assertOk()->assertDontSee('data-location-measurement', false);
        $this->assertDatabaseCount('attendances', 0);
        $this->assertDatabaseCount('attendance_events', 0);
    }

    public function test_both_presential_methods_require_location_even_with_client_bypass_flags(): void
    {
        [$session, $enrollment, $portal] = $this->scenario();
        foreach (['qr', 'link'] as $method) {
            $this->loginAs($enrollment->student)->postJson('/check-in/'.$portal['portal']->id, $this->packet($portal, $method, ['location' => null, 'modality' => 'online', 'radius' => 10000, 'geo_required' => false]))
                ->assertUnprocessable()->assertJsonPath('code', 'geo_unavailable');
        }
        $this->assertDatabaseCount('attendances', 0);
        $this->assertDatabaseCount('absence_requests', 0);
        $this->assertDatabaseCount('audit_logs', 1);
        $this->assertNull(AttendanceEvent::first()->geo_validation);
    }

    public function test_online_and_event_keep_location_free_registration_including_legacy_portals(): void
    {
        foreach (['online', 'event'] as $modality) {
            [$session, $enrollment, $portal] = $this->scenario($modality);
            $this->assertNull($portal['portal']->geo_reference);
            $portal['portal']->update(['access_modality' => null]);
            $this->loginAs($enrollment->student)->get('/check-in/'.$portal['portal']->id)->assertOk()->assertSee('data-geo-required="false"', false)->assertDontSee('Esta clase es presencial');
            foreach (['qr', 'link'] as $method) {
                $this->postJson('/check-in/'.$portal['portal']->id, $this->packet($portal, $method, ['location' => null]))->assertSuccessful();
            }
        }
        $this->assertDatabaseCount('attendances', 2);
    }

    public function test_failed_open_keeps_current_portal_and_manual_nfc_can_open_without_location(): void
    {
        [$session, $enrollment, $portal] = $this->scenario();
        $this->loginAs($session->offering->teacher);
        foreach ([null, $this->location(['accuracy' => 10.01]), $this->location(['timestamp' => now()->subMinute()->getTimestampMs()])] as $location) {
            $this->postJson('/sessions/'.$session->id.'/portals', ['methods' => ['qr', 'link'], 'minutes' => 15, 'location' => $location])->assertUnprocessable()->assertJsonValidationErrors('location');
            $this->assertNull($portal['portal']->fresh()->closed_at);
        }
        $this->postJson('/sessions/'.$session->id.'/portals', ['methods' => ['manual', 'nfc'], 'minutes' => 15])->assertCreated();
        $this->assertNotNull($portal['portal']->fresh()->closed_at);
        $this->assertNull(AttendancePortal::latest('id')->first()->geo_reference);
    }

    public function test_reference_is_fixed_reopening_replaces_it_and_evidence_cannot_cross_portals(): void
    {
        [$session, $enrollment, $first] = $this->scenario();
        $reference = $first['portal']->geo_reference;
        $this->loginAs($enrollment->student)->postJson('/check-in/'.$first['portal']->id, $this->packet($first))->assertCreated();
        $this->assertEquals($reference, $first['portal']->fresh()->geo_reference);
        $second = app(PortalManager::class)->open($session->offering->teacher, $session, ['qr', 'link'], 15, $this->location(['latitude' => 13.8]));
        $this->assertNotNull($first['portal']->fresh()->closed_at);
        $this->assertNotEquals($reference, $second['portal']->geo_reference);
        $this->postJson('/check-in/'.$second['portal']->id, $this->packet($second, changes: ['location' => $this->location(['portal_id' => $first['portal']->id])]))->assertUnprocessable()->assertJsonPath('code', 'geo_invalid');
        $this->postJson('/check-in/'.$second['portal']->id, $this->packet($first))->assertUnprocessable();
        $this->assertDatabaseCount('attendances', 1);
    }

    public function test_legacy_presential_portal_requires_reopening_but_manual_remains_available(): void
    {
        [$session, $enrollment, $portal] = $this->scenario();
        $portal['portal']->update(['access_modality' => null, 'geo_reference' => null]);
        $this->loginAs($enrollment->student)->postJson('/check-in/'.$portal['portal']->id, $this->packet($portal))->assertConflict()->assertJsonPath('code', 'geo_reference');
        $this->loginAs($session->offering->teacher)->get('/sessions/'.$session->id)->assertOk()->assertSee('Este portal no tiene referencia');
        $this->postJson('/sessions/'.$session->id.'/manual-attendance', ['event_id' => (string) Str::uuid(), 'portal_id' => $portal['portal']->id, 'student_id' => $enrollment->student_id, 'reason' => 'Verificación presencial supervisada'])->assertCreated();
    }

    public function test_modality_changes_cannot_disable_the_open_portal_policy(): void
    {
        [$session, $enrollment, $portal] = $this->scenario();
        $this->loginAs($session->offering->teacher)->put('/offerings/'.$session->offering_id.'/sessions/'.$session->id, ['period_id' => $session->period_id, 'starts_at' => '2026-10-21T08:00', 'ends_at' => '2026-10-21T09:00', 'modality' => 'online', 'reason' => 'Cambio de modalidad'])->assertSessionHasErrors();
        $this->assertSame('in_person', $session->fresh()->modality);
        // Defensive check against writes outside SessionWorkflow.
        $session->update(['modality' => 'online']);
        $this->loginAs($enrollment->student)->postJson('/check-in/'.$portal['portal']->id, $this->packet($portal, changes: ['location' => null]))->assertConflict()->assertJsonPath('code', 'geo_configuration');
    }

    public function test_unknown_or_missing_modality_fails_clearly(): void
    {
        $service = app(GeographicAccess::class);
        foreach ([null, '', 'unknown'] as $value) {
            try {
                $service->requiresLocation(new ClassSession(['modality' => $value]));
                $this->fail('Una modalidad desconocida no debe omitir validación.');
            } catch (RegistrationRejected $exception) {
                $this->assertSame('geo_configuration', $exception->reasonCode);
            }
        }
        try {
            $service->requiresLocation(new ClassSession(['modality' => 'event']));
            $this->fail('Evento sin ubicación no es una modalidad no presencial.');
        } catch (RegistrationRejected $exception) {
            $this->assertSame('geo_configuration', $exception->reasonCode);
        }
    }

    public function test_event_requires_explicit_location_and_presential_events_use_the_same_rule(): void
    {
        [$session, $enrollment, $portal] = $this->scenario('event');
        $session->update(['event_attendance_mode' => null]);
        $this->loginAs($enrollment->student)->postJson('/check-in/'.$portal['portal']->id, $this->packet($portal, changes: ['location' => null]))->assertUnprocessable()->assertJsonPath('code', 'geo_configuration');
        $this->loginAs($session->offering->teacher)->postJson('/sessions/'.$session->id.'/portals', ['methods' => ['qr'], 'minutes' => 15])->assertUnprocessable()->assertJsonValidationErrors('location');
        $this->postJson('/sessions/'.$session->id.'/portals', ['methods' => ['manual', 'nfc'], 'minutes' => 15])->assertCreated();
        $session->update(['event_attendance_mode' => 'in_person']);
        $portal = app(PortalManager::class)->open($session->offering->teacher, $session->fresh(), ['qr', 'link'], 15, $this->location());
        $this->loginAs($enrollment->student)->get('/check-in/'.$portal['portal']->id)->assertOk()->assertSee('data-geo-required="true"', false);
        $this->postJson('/check-in/'.$portal['portal']->id, $this->packet($portal, changes: ['location' => null]))->assertUnprocessable()->assertJsonPath('code', 'geo_unavailable');
        $this->postJson('/check-in/'.$portal['portal']->id, $this->packet($portal))->assertCreated();
    }

    public function test_scheduling_an_event_requires_a_deliberate_location_choice(): void
    {
        [$session] = $this->scenario();
        $data = ['period_id' => $session->period_id, 'starts_at' => '2026-10-22T08:00', 'ends_at' => '2026-10-22T09:00', 'modality' => 'event'];
        $this->loginAs($session->offering->teacher)->post('/offerings/'.$session->offering_id.'/sessions', $data)->assertSessionHasErrors('event_attendance_mode');
        $this->post('/offerings/'.$session->offering_id.'/sessions', [...$data, 'event_attendance_mode' => 'in_person'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('class_sessions', ['modality' => 'event', 'event_attendance_mode' => 'in_person']);
    }

    public function test_distance_and_combined_uncertainty_include_the_exact_thirty_metre_limit(): void
    {
        [$session, $enrollment, $portal] = $this->scenario();
        $portal['portal']->update(['geo_reference' => $this->location(['latitude' => 0, 'longitude' => 0, 'accuracy' => 10])]);
        // 10m between centres + 10m + 10m uncertainty: exactly 30, without rounding before validation.
        $this->loginAs($enrollment->student)->postJson('/check-in/'.$portal['portal']->id, $this->packet($portal, changes: ['location' => $this->location(['latitude' => rad2deg(10 / 6371008.8), 'longitude' => 0, 'accuracy' => 10, 'portal_id' => $portal['portal']->id])]))->assertCreated();
        $this->assertSame(10.0, (float) AttendanceEvent::sole()->geo_validation['distance_m']);
        $this->assertSame(10, AttendanceEvent::sole()->geo_validation['reference_accuracy_m']);
        $this->assertSame(10, AttendanceEvent::sole()->geo_validation['accuracy_m']);
        $service = app(GeographicAccess::class);
        $this->assertEqualsWithDelta(30, $service->distance(['latitude' => 0, 'longitude' => 0], ['latitude' => rad2deg(30 / 6371008.8), 'longitude' => 0]), 0.000000001);
        foreach ([[10.000001, 'geo_accuracy'], [29, 'geo_accuracy'], [51, 'geo_outside']] as [$distance, $code]) {
            $this->postJson('/check-in/'.$portal['portal']->id, $this->packet($portal, changes: ['location' => $this->location(['latitude' => rad2deg($distance / 6371008.8), 'longitude' => 0, 'accuracy' => 10, 'portal_id' => $portal['portal']->id])]))->assertUnprocessable()->assertJsonPath('code', $code)->assertSee('30 m');
        }
        $this->assertDatabaseCount('attendances', 1);
    }

    public function test_ten_metre_accuracy_is_inclusive_for_teacher_and_student_with_both_methods(): void
    {
        [$session, $enrollment] = $this->scenario();
        $this->assertSame(30.0, config('geolocation.radius_m'));
        $this->assertSame(10.0, config('geolocation.max_accuracy_m'));
        $portal = app(PortalManager::class)->open($session->offering->teacher, $session, ['qr', 'link'], 15, $this->location(['accuracy' => 10]));
        $this->loginAs($enrollment->student);
        foreach (['qr', 'link'] as $method) {
            $this->postJson('/check-in/'.$portal['portal']->id, $this->packet($portal, $method, ['location' => $this->location(['accuracy' => 10, 'portal_id' => $portal['portal']->id])]))->assertSuccessful();
            $this->postJson('/check-in/'.$portal['portal']->id, $this->packet($portal, $method, ['radius_m' => 10000, 'max_accuracy_m' => 10000, 'location' => $this->location(['accuracy' => 10.0001, 'portal_id' => $portal['portal']->id])]))
                ->assertUnprocessable()->assertJsonPath('code', 'geo_accuracy')->assertSee('10,01 m')->assertSee('10,00 m');
        }
        $this->loginAs($session->offering->teacher)->postJson('/sessions/'.$session->id.'/portals', ['methods' => ['qr', 'link'], 'minutes' => 15, 'max_accuracy_m' => 10000, 'location' => $this->location(['accuracy' => 10.0001])])
            ->assertUnprocessable()->assertSee('10,01 m')->assertSee('10,00 m');
        $this->assertNull($portal['portal']->fresh()->closed_at);
        $this->assertDatabaseCount('attendances', 1);
    }

    public function test_existing_reference_above_ten_requires_reopening_without_rewriting_historical_evidence(): void
    {
        config(['geolocation.radius_m' => 50.0, 'geolocation.max_accuracy_m' => 15.0]);
        [$session, $enrollment] = $this->scenario();
        $old = app(PortalManager::class)->open($session->offering->teacher, $session, ['qr', 'link', 'manual', 'nfc'], 15, $this->location(['accuracy' => 12]));
        $packet = $this->packet($old, changes: ['location' => $this->location(['accuracy' => 12, 'portal_id' => $old['portal']->id])]);
        $response = $this->loginAs($enrollment->student)->postJson('/check-in/'.$old['portal']->id, $packet)->assertCreated();
        $event = AttendanceEvent::sole();
        $evidence = $event->getRawOriginal('geo_validation');
        $reference = $old['portal']->fresh()->getRawOriginal('geo_reference');

        config(['geolocation.radius_m' => 30.0, 'geolocation.max_accuracy_m' => 10.0]);
        $this->postJson('/check-in/'.$old['portal']->id, $packet)->assertOk()->assertJsonPath('replayed', true)->assertJsonPath('attendance_id', $response->json('attendance_id'));
        foreach (['qr', 'link'] as $method) {
            $this->postJson('/check-in/'.$old['portal']->id, $this->packet($old, $method))->assertConflict()->assertJsonPath('code', 'geo_reference')->assertSee('cerrar y reabrir');
        }
        $this->assertSame($evidence, $event->fresh()->getRawOriginal('geo_validation'));
        $this->assertSame($reference, $old['portal']->fresh()->getRawOriginal('geo_reference'));
        $this->assertNull($old['portal']->fresh()->closed_at);
        $this->loginAs($session->offering->teacher)->postJson('/sessions/'.$session->id.'/manual-attendance', ['event_id' => (string) Str::uuid(), 'portal_id' => $old['portal']->id, 'student_id' => $enrollment->student_id, 'reason' => 'Verificación supervisada de prueba'])->assertSuccessful();

        $new = app(PortalManager::class)->open($session->offering->teacher, $session, ['qr', 'link'], 15, $this->location(['accuracy' => 10]));
        $this->assertNotNull($old['portal']->fresh()->closed_at);
        $this->loginAs($enrollment->student)->postJson('/check-in/'.$new['portal']->id, $this->packet($new, 'link', ['location' => $this->location(['accuracy' => 10, 'portal_id' => $new['portal']->id])]))->assertSuccessful();
        $this->assertSame($evidence, $event->fresh()->getRawOriginal('geo_validation'));
        $this->assertDatabaseCount('attendances', 1);
    }

    public function test_existing_compatible_reference_uses_current_radius_for_new_attempts(): void
    {
        config(['geolocation.radius_m' => 50.0, 'geolocation.max_accuracy_m' => 15.0]);
        [$session, $enrollment] = $this->scenario();
        $portal = app(PortalManager::class)->open($session->offering->teacher, $session, ['qr', 'link'], 15, $this->location(['latitude' => 0, 'longitude' => 0, 'accuracy' => 8]));
        $reference = $portal['portal']->fresh()->getRawOriginal('geo_reference');
        config(['geolocation.radius_m' => 30.0, 'geolocation.max_accuracy_m' => 10.0]);
        $this->loginAs($enrollment->student);
        foreach (['qr', 'link'] as $method) {
            // 15 + 8 + 8 = 31: previously within 50, now rejected under 30.
            $this->postJson('/check-in/'.$portal['portal']->id, $this->packet($portal, $method, ['radius_m' => 50, 'location' => $this->location(['latitude' => rad2deg(15 / 6371008.8), 'longitude' => 0, 'accuracy' => 8, 'portal_id' => $portal['portal']->id])]))
                ->assertUnprocessable()->assertJsonPath('code', 'geo_accuracy')->assertSee('30 m');
            $this->postJson('/check-in/'.$portal['portal']->id, $this->packet($portal, $method, ['location' => $this->location(['latitude' => 0, 'longitude' => 0, 'accuracy' => 8, 'portal_id' => $portal['portal']->id])]))->assertSuccessful();
        }
        $this->assertSame($reference, $portal['portal']->fresh()->getRawOriginal('geo_reference'));
        $this->assertDatabaseCount('attendances', 1);
    }

    public function test_invalid_missing_nonfinite_inaccurate_and_stale_student_readings_are_rejected(): void
    {
        [$session, $enrollment, $portal] = $this->scenario();
        $this->loginAs($enrollment->student);
        $cases = [
            ['latitude' => 91], ['longitude' => -181], ['latitude' => 'NaN'], ['latitude' => null], ['accuracy' => -1], ['accuracy' => 0], ['accuracy' => 10.01],
            ['timestamp' => now()->subSeconds(31)->getTimestampMs()], ['timestamp' => now()->addSeconds(6)->getTimestampMs()], ['latitude' => []], ['timestamp' => null],
        ];
        foreach ($cases as $changes) {
            $this->postJson('/check-in/'.$portal['portal']->id, $this->packet($portal, changes: ['location' => $this->location(['portal_id' => $portal['portal']->id, ...$changes])]))->assertUnprocessable();
        }
        foreach ([INF, -INF, NAN] as $value) {
            $result = app(RegisterAttendance::class)->record($enrollment->student, $session, 'qr', [...$this->packet($portal, changes: ['location' => $this->location(['portal_id' => $portal['portal']->id, 'latitude' => $value])]), 'portal_id' => $portal['portal']->id]);
            $this->assertSame('geo_invalid', $result['body']['code']);
        }
        $this->assertDatabaseCount('attendances', 0);
        $this->assertSame(0, AttendanceEvent::whereNotNull('geo_validation')->count());
    }

    public function test_reference_is_not_reaged_during_portal_but_corrupt_reference_fails(): void
    {
        [$session, $enrollment, $portal] = $this->scenario();
        $this->travel(2)->minutes();
        $this->loginAs($enrollment->student)->postJson('/check-in/'.$portal['portal']->id, $this->packet($portal))->assertCreated();
        $portal['portal']->update(['geo_reference' => $this->location(['latitude' => 200])]);
        $this->postJson('/check-in/'.$portal['portal']->id, $this->packet($portal))->assertConflict()->assertJsonPath('code', 'geo_reference');
    }

    public function test_rejected_event_replays_new_evidence_needs_new_uuid_and_completed_event_survives_expiry(): void
    {
        [$session, $enrollment, $portal] = $this->scenario();
        $bad = $this->packet($portal, changes: ['location' => null]);
        $this->loginAs($enrollment->student)->postJson('/check-in/'.$portal['portal']->id, $bad)->assertUnprocessable();
        $this->postJson('/check-in/'.$portal['portal']->id, $bad)->assertUnprocessable()->assertJsonPath('replayed', true);
        $good = $this->packet($portal, changes: ['event_id' => $bad['event_id']]);
        $this->postJson('/check-in/'.$portal['portal']->id, $good)->assertConflict();
        $good['event_id'] = (string) Str::uuid();
        $this->postJson('/check-in/'.$portal['portal']->id, $good)->assertCreated();
        $this->postJson('/check-in/'.$portal['portal']->id, $this->packet($portal, 'link'))->assertOk()->assertJsonPath('status', 'already_recorded');
        app(PortalManager::class)->close($session->offering->teacher, $portal['portal']);
        $this->travel(16)->minutes();
        $this->postJson('/check-in/'.$portal['portal']->id, $good)->assertOk()->assertJsonPath('replayed', true);
        $this->postJson('/check-in/'.$portal['portal']->id, $this->packet($portal))->assertConflict();
        $this->assertDatabaseCount('attendances', 1);
    }

    public function test_expired_portal_rejects_even_fresh_valid_location(): void
    {
        [$session, $enrollment, $portal] = $this->scenario();
        $this->travel(16)->minutes();
        $this->loginAs($enrollment->student)->postJson('/check-in/'.$portal['portal']->id, $this->packet($portal))->assertConflict();
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_roles_membership_active_account_and_privacy_are_preserved(): void
    {
        [$session, $enrollment, $portal] = $this->scenario();
        $other = User::factory()->teacher()->create();
        $this->loginAs($other)->postJson('/sessions/'.$session->id.'/portals', ['methods' => ['qr'], 'minutes' => 15, 'location' => $this->location()])->assertForbidden();
        $this->loginAs($enrollment->student)->postJson('/sessions/'.$session->id.'/portals', ['methods' => ['qr'], 'minutes' => 15, 'location' => $this->location()])->assertForbidden();
        $outsider = User::factory()->create();
        $this->loginAs($outsider)->postJson('/check-in/'.$portal['portal']->id, $this->packet($portal))->assertForbidden();
        $enrollment->student->update(['active' => false]);
        $this->loginAs($enrollment->student)->postJson('/check-in/'.$portal['portal']->id, $this->packet($portal))->assertForbidden();
        $this->assertDatabaseCount('attendances', 0);
        $enrollment->student->update(['active' => true]);
        $this->loginAs($enrollment->student)->get('/check-in/'.$portal['portal']->id)->assertOk()->assertDontSee('13.718254')->assertDontSee('-89.204321')->assertSee('data-geo-required="true"', false)
            ->assertHeader('Permissions-Policy', 'nfc=(self), camera=(), microphone=(), geolocation=(self)');
        $this->postJson('/check-in/'.$portal['portal']->id, $this->packet($portal))->assertCreated()->assertJsonMissingPath('geo_validation')->assertJsonMissingPath('latitude');
        $event = AttendanceEvent::sole();
        $this->assertSame('inside', $event->geo_validation['result']);
        foreach ([$event->getRawOriginal('geo_validation'), $event->getRawOriginal('response'), AuditLog::all()->toJson(), $portal['portal']->toJson()] as $stored) {
            $this->assertStringNotContainsString('13.718254', $stored);
            $this->assertStringNotContainsString('latitude', $stored);
        }
    }

    public function test_coordinator_can_open_with_their_own_reference_without_expanding_permissions(): void
    {
        [$session] = $this->scenario();
        $coordinator = User::factory()->coordinator()->create();
        $location = $this->location(['latitude' => 14]);
        $response = $this->loginAs($coordinator)->postJson('/sessions/'.$session->id.'/portals', ['methods' => ['qr', 'link'], 'minutes' => 15, 'location' => $location])->assertCreated();
        $this->assertSame(14.0, (float) AttendancePortal::findOrFail($response->json('portal_id'))->geo_reference['latitude']);
        $this->assertSame($coordinator->id, AuditLog::latest('id')->first()->actor_id);
        $response->assertJsonMissingPath('geo_reference')->assertJsonMissingPath('latitude');
    }

    public function test_geographic_failures_leave_a01_unchanged_and_only_finishing_creates_absence(): void
    {
        [$session, $enrollment, $portal] = $this->scenario();
        $summary = app(AttendanceSummary::class);
        $before = $summary->forEnrollment($enrollment->student, $enrollment, $session->period);
        $this->loginAs($enrollment->student)->postJson('/check-in/'.$portal['portal']->id, $this->packet($portal, changes: ['location' => null]))->assertUnprocessable();
        $after = $summary->forEnrollment($enrollment->student, $enrollment, $session->period);
        $this->assertSame($before['fraction'], $after['fraction']);
        $this->assertSame($before['counts'], $after['counts']);
        $this->assertDatabaseCount('attendances', 0);
        app(SessionWorkflow::class)->transition($session->offering->teacher, $session, 'finish', 'Fin supervisado de la clase');
        $final = $summary->forEnrollment($enrollment->student, $enrollment, $session->period);
        $this->assertSame(1, $final['counts']['absent']);
        $this->assertSame('0.00', $final['percentage']);
    }

    public function test_location_retention_keeps_recent_portals_attendance_and_idempotent_responses(): void
    {
        [$session, $enrollment, $portal] = $this->scenario();
        $packet = $this->packet($portal);
        $this->loginAs($enrollment->student)->postJson('/check-in/'.$portal['portal']->id, $packet)->assertCreated();
        $this->artisan('attendance:prune-location')->assertSuccessful();
        $this->assertNotNull($portal['portal']->fresh()->geo_reference);
        $this->assertNotNull(AttendanceEvent::sole()->geo_validation);
        $this->travel(31)->days();
        $this->artisan('attendance:prune-location')->assertSuccessful();
        $this->assertNull($portal['portal']->fresh()->geo_reference);
        $this->assertNull(AttendanceEvent::sole()->geo_validation);
        $this->postJson('/check-in/'.$portal['portal']->id, $packet)->assertOk()->assertJsonPath('replayed', true);
        $this->assertDatabaseCount('attendances', 1);
        $this->assertDatabaseCount('attendance_events', 1);
    }

    public function test_time_boundaries_and_membership_are_enforced_independently_of_location(): void
    {
        [$session, $enrollment, $portal] = $this->scenario();
        $this->travel(1)->minutes();
        $this->loginAs($enrollment->student);
        foreach ([-30000, 5000] as $offset) {
            $this->postJson('/check-in/'.$portal['portal']->id, $this->packet($portal, changes: ['location' => $this->location(['portal_id' => $portal['portal']->id, 'timestamp' => now()->getTimestampMs() + $offset])]))->assertSuccessful();
        }
        $enrollment->update(['active_to' => '2026-10-20']);
        $this->postJson('/check-in/'.$portal['portal']->id, $this->packet($portal))->assertUnprocessable();
        $this->assertDatabaseCount('attendances', 1);
    }

    public function test_opening_does_not_flash_location_into_session_on_validation_failure(): void
    {
        [$session] = $this->scenario();
        $this->loginAs($session->offering->teacher)->from('/sessions/'.$session->id)->post('/sessions/'.$session->id.'/portals', ['methods' => ['qr'], 'minutes' => 15, 'location' => $this->location(['accuracy' => 10.01])])
            ->assertSessionHasErrors('location')->assertSessionMissing('_old_input.location');
    }
}
