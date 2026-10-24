<?php

namespace Tests\Feature;

use App\Actions\Academic\AcademicRegistry;
use App\Actions\Academic\CsvImporter;
use App\Actions\Academic\CycleFormatManager;
use App\Actions\Academic\OfferingLifecycle;
use App\Actions\Academic\UserManager;
use App\Actions\Attendance\AbsenceWorkflow;
use App\Actions\Attendance\ClosureWorkflow;
use App\Actions\Attendance\CorrectAttendance;
use App\Actions\Attendance\PortalManager;
use App\Actions\Attendance\RegisterAttendance;
use App\Actions\Attendance\ReviewSession;
use App\Actions\Attendance\SessionWorkflow;
use App\Enums\SessionState;
use App\Models\AbsenceRequest;
use App\Models\AcademicCycle;
use App\Models\AssessmentPeriod;
use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Offering;
use App\Models\ReportVersion;
use App\Models\User;
use App\Queries\AttendanceSummary;
use App\Queries\DashboardStatistics;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OfferingManagementTest extends TestCase
{
    private function scenario(): array
    {
        $this->travelTo(now()->setDateTime(2026, 10, 23, 16, 0));
        $period = AssessmentPeriod::factory()->create();
        $group = $period->offering;
        $admin = User::factory()->coordinator()->create();
        $enrollment = Enrollment::factory()->create(['offering_id' => $group->id]);

        return [$group, $period, $admin, $enrollment];
    }

    private function makeSession(AssessmentPeriod $period, array $data = []): ClassSession
    {
        return ClassSession::factory()->create(['offering_id' => $period->offering_id, 'period_id' => $period->id,
            'starts_at' => now()->subMinutes(10), 'ends_at' => now()->addMinutes(50), 'modality' => 'online', ...$data]);
    }

    private function permission(ClassSession $session, Enrollment $enrollment, string $status = 'pending'): AbsenceRequest
    {
        return AbsenceRequest::create(['session_id' => $session->id, 'offering_id' => $session->offering_id,
            'enrollment_id' => $enrollment->id, 'reason' => 'Motivo privado ficticio A04', 'status' => $status]);
    }

    private function blocked(callable $operation, string $contains = 'desactivado'): void
    {
        try {
            $operation();
            $this->fail('Debía rechazar la operación.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString($contains, json_encode($exception->errors(), JSON_UNESCAPED_UNICODE));
        }
    }

    public function test_incremental_migration_preserves_existing_group_data_and_enables_existing_groups(): void
    {
        $group = Offering::factory()->create();
        $before = (array) DB::table('offerings')->where('id', $group->id)->first();
        unset($before['active']);
        $migration = require database_path('migrations/2026_10_23_000100_add_offering_administrative_state.php');
        $migration->down();
        $this->assertFalse(Schema::hasColumn('offerings', 'active'));
        $migration->up();
        $after = (array) DB::table('offerings')->where('id', $group->id)->first();
        $this->assertTrue($after['active']);
        unset($after['active']);
        $this->assertSame($before, $after);
    }

    public function test_schema_rollback_cannot_erase_an_administrative_state_already_used(): void
    {
        [$group, , $admin] = $this->scenario();
        app(OfferingLifecycle::class)->setActive($admin, $group, false, 'Uso del estado antes de intentar rollback');
        app(OfferingLifecycle::class)->setActive($admin, $group, true, 'Estado habilitado con historia conservada');
        $migration = require database_path('migrations/2026_10_23_000100_add_offering_administrative_state.php');
        try {
            $migration->down();
            $this->fail('No debe retirar la columna con historia de uso.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Conserva su historia', $exception->getMessage());
        }
        $this->assertTrue($group->fresh()->active);
        $this->assertDatabaseHas('audit_logs', ['action' => 'offering.deactivated']);
    }

    public function test_dashboard_current_expired_open_disabled_and_closed_are_classified_by_cycle(): void
    {
        [$group, , $admin] = $this->scenario();
        $expired = Offering::factory()->create(['cycle_id' => AcademicCycle::factory()->create(['ends_on' => '2026-10-01'])->id]);
        $closed = Offering::factory()->create(['cycle_id' => AcademicCycle::factory()->create(['status' => 'closed'])->id]);
        $disabled = Offering::factory()->create(['active' => false]);
        $this->actingAs($admin)->get('/dashboard')->assertOk()->assertSee($group->subject->name)->assertSee($expired->subject->name)
            ->assertDontSee($closed->subject->name)->assertDontSee($disabled->subject->name)->assertViewHas('offerings', fn ($rows) => $rows->count() === 2);
        $this->get('/offerings')->assertOk()->assertSee($expired->subject->name)->assertDontSee($closed->subject->name)->assertDontSee($disabled->subject->name);
        $this->get('/offerings?view=history')->assertOk()->assertSee($closed->subject->name)->assertSee('Ciclo cerrado')->assertDontSee($group->subject->name);
        $this->get('/offerings?availability=inactive')->assertOk()->assertSee($disabled->subject->name)->assertSee('Grupo desactivado')->assertDontSee($closed->subject->name);
    }

    public function test_filters_pagination_empty_state_and_navigation_keep_context(): void
    {
        [$group, , $admin] = $this->scenario();
        Offering::factory()->count(13)->create(['cycle_id' => $group->cycle_id]);
        $url = '/offerings?view=current&availability=all&cycle_id='.$group->cycle_id;
        $this->actingAs($admin)->get($url)->assertOk()->assertViewHas('offerings', fn ($rows) => $rows->total() === 14 && $rows->count() === 12)
            ->assertSee('cycle_id='.$group->cycle_id, false)->assertSee('availability=all', false);
        $this->get($url.'&page=2')->assertOk()->assertViewHas('offerings', fn ($rows) => $rows->count() === 2);
        $this->get('/offerings/'.$group->id.'?view=current&availability=all&cycle_id='.$group->cycle_id.'&catalog_page=2')->assertOk()->assertSee('page=2', false);
        $this->get('/offerings?view=history&cycle_id='.$group->cycle_id)->assertOk()->assertSee('No hay grupos en esta vista');
        $this->getJson('/offerings?view=invalid')->assertUnprocessable();
    }

    public function test_teacher_student_historical_enrollments_and_direct_urls_preserve_scope(): void
    {
        [$group, , , $enrollment] = $this->scenario();
        $enrollment->update(['active_to' => '2026-02-01']);
        $group->cycle->update(['status' => 'closed']);
        $group->forceFill(['active' => false])->save();
        $other = Offering::factory()->create();
        $private = Enrollment::factory()->create(['offering_id' => $group->id]);
        $private->student->update(['name' => 'OTRA_PERSONA_PRIVADA_A04']);
        foreach ([$group->teacher, $enrollment->student] as $actor) {
            $this->flushSession();
            $this->actingAs($actor)->get('/offerings?view=history')->assertOk()->assertSee($group->subject->name)->assertSee('Grupo desactivado')->assertDontSee($other->subject->name);
            $response = $this->get('/offerings/'.$group->id)->assertOk()->assertSee('operación académica está suspendida')->assertDontSee('deactivate-reason', false);
            if ($actor->id === $enrollment->student_id) {
                $response->assertDontSee('OTRA_PERSONA_PRIVADA_A04')->assertSee($enrollment->student->name);
            }
            $this->get('/offerings/'.$other->id)->assertForbidden();
            $this->post('/admin/offerings/'.$group->id.'/state/reactivate', ['reason' => 'Intento sin autorización'])->assertForbidden();
            $this->delete('/admin/offerings/'.$group->id, ['reason' => 'Intento sin autorización', 'confirmation' => '01'])->assertForbidden();
        }
    }

    public function test_dashboard_and_historical_operational_statistics_use_the_same_group_selection(): void
    {
        [$group, $period, $admin, $enrollment] = $this->scenario();
        $session = $this->makeSession($period, ['state' => 'completed', 'starts_at' => now()->subHours(2), 'ends_at' => now()->subHour()]);
        Attendance::create(['session_id' => $session->id, 'offering_id' => $group->id, 'enrollment_id' => $enrollment->id, 'status' => 'present', 'method' => 'nfc', 'recorded_by' => $admin->id, 'recorded_at' => now()]);
        $past = AssessmentPeriod::factory()->create();
        $past->offering->cycle->update(['status' => 'closed']);
        $this->makeSession($past, ['state' => 'completed']);
        $hidden = AssessmentPeriod::factory()->create();
        $hidden->offering->forceFill(['active' => false])->save();
        $this->makeSession($hidden, ['state' => 'in_progress']);
        $stats = app(DashboardStatistics::class)->forUser($admin);
        $this->assertSame(1, $stats['sessions']['Finalizadas']);
        $this->assertSame(0, $stats['sessions']['En curso']);
        $this->assertSame(1, $stats['results']['Presentes']);
        $this->assertSame(1, $stats['methods']['NFC']);
        $foreignTeacher = User::factory()->teacher()->create();
        $this->assertSame(0, array_sum(app(DashboardStatistics::class)->forUser($foreignTeacher, Offering::query())['sessions']));
        $this->actingAs($admin)->get('/offerings?view=history&cycle_id='.$past->offering->cycle_id)->assertOk()
            ->assertViewHas('statistics', fn ($data) => $data['sessions']['Finalizadas'] === 1 && $data['results']['Presentes'] === 0)
            ->assertSee('Consulta los resultados de cierre en cada grupo')->assertViewHas('groupCount', 1);
        $this->get('/dashboard')->assertOk()->assertViewHas('studentCount', User::where('role', 'student')->where('active', true)->count());
    }

    public function test_administrative_transitions_require_reason_and_audit_only_real_changes(): void
    {
        [$group, $period, $admin] = $this->scenario();
        $future = $this->makeSession($period, ['starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addHour()]);
        $before = $future->fresh()->getAttributes();
        $url = '/admin/offerings/'.$group->id.'/state/';
        $this->actingAs($admin)->postJson($url.'deactivate', ['reason' => '   '])->assertUnprocessable();
        $this->post($url.'deactivate', ['reason' => 'Suspender grupo por ajuste administrativo'])->assertRedirect();
        $this->post($url.'deactivate', ['reason' => 'Reintento de la misma transición'])->assertRedirect();
        $this->assertFalse($group->fresh()->active);
        $this->assertSame($before, $future->fresh()->getAttributes());
        $this->assertSame('open', $period->fresh()->state);
        $log = AuditLog::where('action', 'offering.deactivated')->sole();
        $this->assertSame($admin->id, $log->actor_id);
        $this->assertTrue($log->before['active']);
        $this->assertFalse($log->after['active']);
        $this->assertSame('Suspender grupo por ajuste administrativo', $log->reason);
        $this->assertNotNull($log->created_at);
        $this->post($url.'reactivate', ['reason' => 'Reanudar operación autorizada'])->assertRedirect();
        $this->post($url.'reactivate', ['reason' => 'Reintento sin transición nueva'])->assertRedirect();
        $this->assertTrue($group->fresh()->active);
        $this->assertSame(1, AuditLog::where('action', 'offering.reactivated')->count());
    }

    public static function blockers(): array
    {
        return array_map(fn ($case) => [$case], ['running', 'portal', 'reader', 'pending', 'missing', 'justified', 'scheduled_evidence', 'invalid_snapshot', 'outside_dates']);
    }

    #[DataProvider('blockers')]
    public function test_deactivation_explains_real_blockers_without_changing_data(string $case): void
    {
        [$group, $period, $admin, $enrollment] = $this->scenario();
        $session = $this->makeSession($period, ['state' => $case === 'running' ? 'in_progress' : ($case === 'missing' || $case === 'justified' ? 'completed' : 'scheduled')]);
        $expected = match ($case) {
            'running' => 'en curso', 'portal' => 'Portal #', 'reader' => 'Lector', 'pending' => 'permisos pendientes',
            'missing' => 'falta el resultado', 'justified' => 'justificación sin permiso aprobado', 'scheduled_evidence' => 'datos contradictorios',
            'invalid_snapshot' => 'snapshot válido', default => 'fuera del cómputo',
        };
        if (in_array($case, ['portal', 'reader', 'scheduled_evidence'])) {
            $portal = $session->portals()->create(['opens_at' => now()->subMinutes(5), 'expires_at' => now()->addMinutes(10), 'enabled_methods' => ['nfc'], 'token_hash' => hash('sha256', $case)]);
            if ($case === 'reader') {
                $portal->update(['closed_at' => now()]);
                $portal->readerSessions()->create(['id' => (string) Str::uuid(), 'teacher_id' => $group->teacher_id, 'label' => 'Lector A04', 'expires_at' => now()->addMinutes(10), 'state' => 'active']);
            }
        }
        if ($case === 'pending') {
            $this->permission($session, $enrollment);
        }
        if ($case === 'justified') {
            Attendance::create(['session_id' => $session->id, 'offering_id' => $group->id, 'enrollment_id' => $enrollment->id, 'status' => 'justified', 'recorded_by' => $admin->id, 'recorded_at' => now()]);
        }
        if ($case === 'invalid_snapshot') {
            $period->update(['state' => 'closed']);
        }
        if ($case === 'outside_dates') {
            $period->update(['ends_on' => '2026-10-01']);
        }
        $this->blocked(fn () => app(OfferingLifecycle::class)->setActive($admin, $group, false, 'Suspensión debe quedar bloqueada'), $expected);
        $this->assertTrue($group->fresh()->active);
        $this->assertSame($session->state, $session->fresh()->state);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'offering.deactivated']);
    }

    public function test_expired_resources_are_closed_explicitly_without_closing_live_resources_or_rewriting_sessions(): void
    {
        [$group, $period, $admin] = $this->scenario();
        $session = $this->makeSession($period, ['state' => 'completed']);
        // No eligible enrollment for this class, so no missing result inconsistency.
        $group->enrollments()->update(['active_from' => '2026-11-01']);
        $portal = $session->portals()->create(['opens_at' => now()->subHour(), 'expires_at' => now()->subMinute(), 'enabled_methods' => ['nfc'], 'token_hash' => hash('sha256', 'expired-a04')]);
        $reader = $portal->readerSessions()->create(['id' => (string) Str::uuid(), 'teacher_id' => $group->teacher_id, 'label' => 'Vencido A04', 'expires_at' => $portal->expires_at, 'state' => 'active']);
        $this->blocked(fn () => app(OfferingLifecycle::class)->setActive($admin, $group, false, 'Intento previo al cierre de recursos'), 'Cerrar recursos vencidos');
        app(OfferingLifecycle::class)->closeExpiredResources($admin, $group, 'Cierre explícito de recursos ya vencidos');
        app(OfferingLifecycle::class)->closeExpiredResources($admin, $group, 'Repetición sin modificar recursos cerrados');
        $this->assertNotNull($portal->fresh()->closed_at);
        $this->assertSame('closed', $reader->fresh()->state);
        $this->assertSame(1, AuditLog::where('action', 'portal.expired_closed')->count());
        app(OfferingLifecycle::class)->setActive($admin, $group, false, 'Recursos cerrados y datos coherentes');
        $this->assertSame(SessionState::Completed, $session->fresh()->state);
        $this->assertDatabaseCount('attendances', 0);
        app(OfferingLifecycle::class)->setActive($admin, $group, true, 'Reanudar sin restaurar recursos vencidos');
        $this->assertNotNull($portal->fresh()->closed_at);
        $this->assertSame('closed', $reader->fresh()->state);
    }

    public static function writes(): array
    {
        return array_map(fn ($case) => [$case], ['schedule', 'start', 'cancel', 'finish', 'portal', 'reader', 'enroll', 'change_enrollment', 'period', 'offering', 'permission', 'resolve', 'review', 'reopen_decision', 'correct', 'csv']);
    }

    #[DataProvider('writes')]
    public function test_all_human_academic_writers_recheck_inactive_group_in_the_transaction(string $case): void
    {
        [$group, $period, $admin, $enrollment] = $this->scenario();
        $session = $this->makeSession($period, ['state' => 'in_progress']);
        $portal = $session->portals()->create(['opens_at' => now(), 'expires_at' => now()->addMinutes(10), 'enabled_methods' => ['manual'], 'token_hash' => hash('sha256', 'inactive-writes')]);
        $request = $this->permission($session, $enrollment);
        $attendance = Attendance::create(['session_id' => $session->id, 'offering_id' => $group->id, 'enrollment_id' => $enrollment->id, 'status' => 'absent', 'recorded_by' => $admin->id, 'recorded_at' => now()]);
        // Simulate a stale caller and an inconsistent imported record to exercise every write guard.
        Offering::whereKey($group->id)->update(['active' => false]);
        $this->blocked(fn () => match ($case) {
            'schedule' => app(SessionWorkflow::class)->save($admin, $group, ['period_id' => $period->id]),
            'start', 'cancel', 'finish' => app(SessionWorkflow::class)->transition($admin, $session, $case, 'Cambio debe quedar bloqueado'),
            'portal' => app(PortalManager::class)->open($admin, $session, ['manual'], 10),
            'reader' => app(PortalManager::class)->reader($admin, $portal, 'No abrir'),
            'enroll' => app(AcademicRegistry::class)->saveEnrollment($admin, $group, ['student_id' => User::factory()->create()->id]),
            'change_enrollment' => app(AcademicRegistry::class)->saveEnrollment($admin, $group, [], $enrollment),
            'period' => app(AcademicRegistry::class)->savePeriod($admin, $group, [], $period),
            'offering' => app(AcademicRegistry::class)->saveOffering($admin, ['cycle_id' => $group->cycle_id], $group),
            'permission' => app(AbsenceWorkflow::class)->submit($enrollment->student, $session, 'Solicitud operativa nueva', null),
            'resolve' => app(AbsenceWorkflow::class)->resolve($admin, $request, 'approved', 'No modificar grupo suspendido'),
            'review' => app(ReviewSession::class)->execute($admin, $session, 'reconcile', 'No corregir grupo suspendido'),
            'reopen_decision' => app(ReviewSession::class)->reopenDecision($admin, $request, 'No reabrir grupo suspendido'),
            'correct' => app(CorrectAttendance::class)->execute($admin, $attendance, 'present', 'No corregir grupo suspendido'),
            'csv' => app(CsvImporter::class)->confirm($admin, ['kind' => 'enrollments', 'offering_id' => $group->id, 'rows' => []]),
        });
        $this->assertSame('pending', $request->fresh()->status);
        $this->assertSame('absent', $attendance->fresh()->status->value);
    }

    public function test_registration_replay_survives_deactivation_but_every_new_method_is_blocked(): void
    {
        [$group, $period, $admin, $enrollment] = $this->scenario();
        $session = $this->makeSession($period, ['state' => 'in_progress']);
        $portal = app(PortalManager::class)->open($admin, $session, ['manual', 'nfc', 'qr', 'link'], 10);
        $data = ['event_id' => (string) Str::uuid(), 'student_id' => $enrollment->student_id, 'portal_id' => $portal['portal']->id, 'reason' => 'Registro antes de la suspensión'];
        $first = app(RegisterAttendance::class)->record($admin, $session, 'manual', $data);
        $this->assertSame(201, $first['http']);
        app(SessionWorkflow::class)->transition($admin, $session, 'finish', 'Finalización temprana para probar suspensión');
        app(OfferingLifecycle::class)->setActive($admin, $group, false, 'Grupo ya sin operación pendiente');
        $replay = app(RegisterAttendance::class)->record($admin, $session, 'manual', $data);
        $this->assertTrue($replay['body']['replayed']);
        $this->assertSame($first['body']['attendance_id'], $replay['body']['attendance_id']);
        foreach (['manual', 'nfc', 'qr', 'link'] as $method) {
            $actor = in_array($method, ['qr', 'link']) ? $enrollment->student : $admin;
            $this->blocked(fn () => app(RegisterAttendance::class)->record($actor, $session, $method, [...$data, 'event_id' => (string) Str::uuid()]));
        }
        $this->assertDatabaseCount('attendances', 1);
        $this->assertDatabaseCount('attendance_events', 1);
    }

    public function test_direct_http_writes_batch_and_forms_cannot_operate_on_disabled_groups(): void
    {
        [$group, $period, $admin, $enrollment] = $this->scenario();
        $session = $this->makeSession($period);
        app(OfferingLifecycle::class)->setActive($admin, $group, false, 'Suspensión para verificar rutas directas');
        $new = User::factory()->create();
        $this->actingAs($admin)->postJson('/sessions/'.$session->id.'/state/start')->assertUnprocessable()->assertJsonValidationErrors('offering_id');
        $this->postJson('/sessions/'.$session->id.'/portals', ['methods' => ['manual'], 'minutes' => 10])->assertUnprocessable()->assertJsonValidationErrors('offering_id');
        $this->postJson('/admin/offerings/'.$group->id.'/students', ['student_ids' => [$new->id], 'active_from' => '2026-01-01'])->assertUnprocessable()->assertJsonValidationErrors('offering_id');
        $this->get('/offerings/'.$group->id.'/sessions/create')->assertOk()->assertSee('fieldset disabled', false);
        $this->get('/admin/offerings/'.$group->id.'/students')->assertOk()->assertSee('fieldset disabled', false);
        $this->withSession(['password_hash_web' => $enrollment->student->password])->actingAs($enrollment->student)
            ->postJson('/sessions/'.$session->id.'/request', ['reason' => 'Permiso nuevo en grupo suspendido'])->assertUnprocessable()->assertJsonValidationErrors('offering_id');
        $this->get('/sessions/'.$session->id)->assertOk()->assertDontSee('Solicitar permiso')->assertSee('Grupo desactivado');
        $this->assertSame(1, $group->enrollments()->count());
        $this->assertDatabaseCount('absence_requests', 0);
    }

    public function test_independent_account_contact_changes_remain_available_and_period_reopening_requires_reactivation(): void
    {
        [$group, $period, $admin, $enrollment] = $this->scenario();
        $period->update(['ends_on' => '2026-10-22']);
        app(ClosureWorkflow::class)->closePeriod($admin, $period);
        app(OfferingLifecycle::class)->setActive($admin, $group, false, 'Suspensión con cómputo cerrado');
        $this->blocked(fn () => app(ClosureWorkflow::class)->reopenPeriod($admin, $period, 'Intento de revisión sin reactivación'));
        $student = $enrollment->student;
        app(UserManager::class)->save($admin, ['name' => $student->name, 'email' => 'contacto-actualizado@a04.invalid',
            'role' => 'student', 'student_code' => $student->student_code, 'active' => true], $student);
        $this->assertSame('contacto-actualizado@a04.invalid', $student->fresh()->email);
        $this->assertSame('closed', $period->fresh()->state);
        app(OfferingLifecycle::class)->setActive($admin, $group, true, 'Reactivar antes de revisar el cómputo');
        app(ClosureWorkflow::class)->reopenPeriod($admin, $period, 'Revisión explícita después de reactivar');
        $this->assertNotNull($period->fresh()->reopened_at);
        $this->assertSame(1, $period->reports()->count());
    }

    public function test_expiration_maintenance_never_closes_a_live_portal_or_reader(): void
    {
        [$group, $period, $admin] = $this->scenario();
        $session = $this->makeSession($period, ['state' => 'in_progress']);
        $portal = app(PortalManager::class)->open($admin, $session, ['nfc'], 10)['portal'];
        $reader = app(PortalManager::class)->reader($admin, $portal, 'Lector vigente A04');
        app(OfferingLifecycle::class)->closeExpiredResources($admin, $group, 'Solo cerrar recursos realmente vencidos');
        $this->assertNull($portal->fresh()->closed_at);
        $this->assertSame('active', $reader->fresh()->state);
        $this->assertSame(SessionState::InProgress, $session->fresh()->state);
    }

    public function test_future_session_with_approved_preventive_permission_is_preserved_and_does_not_block_deactivation(): void
    {
        [$group, $period, $admin, $enrollment] = $this->scenario();
        $session = $this->makeSession($period, ['starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addHour()]);
        $request = $this->permission($session, $enrollment, 'approved');
        app(OfferingLifecycle::class)->setActive($admin, $group, false, 'Suspender sin cancelar la planificación futura');
        $this->assertFalse($group->fresh()->active);
        $this->assertSame(SessionState::Scheduled, $session->fresh()->state);
        $this->assertSame('approved', $request->fresh()->status);
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_scheduler_processes_disabled_groups_preserving_unstarted_rules_and_snapshots(): void
    {
        [$group, $period, $admin, $enrollment] = $this->scenario();
        $period->update(['ends_on' => '2026-10-22']);
        $group->cycle->update(['ends_on' => '2026-10-22']);
        $session = $this->makeSession($period, ['starts_at' => '2026-10-22T16:00:00Z', 'ends_at' => '2026-10-22T17:00:00Z']);
        app(OfferingLifecycle::class)->setActive($admin, $group, false, 'Suspender conservando la sesión nunca iniciada');
        $this->artisan('attendance:process-closures')->assertSuccessful();
        $this->artisan('attendance:process-closures')->assertSuccessful();
        $this->assertSame(SessionState::Unstarted, $session->fresh()->state);
        $this->assertSame('closed', $group->cycle->fresh()->status);
        $this->assertSame(1, $period->reports()->count());
        $this->assertDatabaseCount('attendances', 0);
        $summary = app(AttendanceSummary::class)->forOffering($enrollment->student, $group);
        $this->assertSame('snapshot', $summary['source']);
        $this->assertNull($summary['rows']->first()['calculation']['percentage']);
        $this->assertNull($summary['rows']->first()['calculation']['conclusion']);
    }

    public function test_reactivation_of_closed_cycle_and_reopening_keep_independent_states_and_snapshot(): void
    {
        [$group, $period, $admin, $enrollment] = $this->scenario();
        $period->update(['ends_on' => '2026-10-22']);
        $group->cycle->update(['ends_on' => '2026-10-22']);
        app(ClosureWorkflow::class)->closePeriod($admin, $period);
        app(ClosureWorkflow::class)->closeCycle($admin, $group->cycle);
        $snapshot = $period->reports()->sole()->getAttributes();
        app(OfferingLifecycle::class)->setActive($admin, $group, false, 'Desactivar grupo con resultados cerrados');
        app(OfferingLifecycle::class)->setActive($admin, $group, true, 'Habilitar sin reabrir ciclo ni cómputo');
        $this->assertSame('closed', $group->cycle->fresh()->status);
        $this->assertFalse(Offering::current()->whereKey($group->id)->exists());
        $second = Offering::factory()->create(['cycle_id' => $group->cycle_id, 'active' => false]);
        app(ClosureWorkflow::class)->reopenCycle($admin, $group->cycle, 'Revisión administrativa del ciclo completo');
        $this->assertTrue(Offering::current()->whereKey($group->id)->exists());
        $this->assertFalse(Offering::current()->whereKey($second->id)->exists());
        $this->assertFalse($second->fresh()->active);
        $this->assertSame('closed', $period->fresh()->state);
        $this->assertSame($snapshot, $period->reports()->sole()->getAttributes());
        $this->actingAs($admin)->get('/offerings')->assertOk()->assertSee('Ciclo en revisión');
        $this->blocked(fn () => app(AcademicRegistry::class)->saveEnrollment($admin, $group, ['student_id' => User::factory()->create()->id, 'active_from' => '2026-01-01']), 'cómputo cerrado');
        $this->artisan('attendance:process-closures')->assertSuccessful();
        $this->assertNotNull($group->cycle->fresh()->reopened_at);
        $this->assertSame('active', $group->cycle->fresh()->status);
        $this->assertSame('snapshot', app(AttendanceSummary::class)->forOffering($enrollment->student, $group)['source']);
    }

    public function test_empty_deletion_requires_confirmation_preserves_audit_and_repeated_request_is_404(): void
    {
        $group = Offering::factory()->create();
        $admin = User::factory()->coordinator()->create();
        $this->actingAs($admin)->deleteJson('/admin/offerings/'.$group->id, ['reason' => 'Grupo creado por error', 'confirmation' => 'otro'])->assertUnprocessable();
        $this->delete('/admin/offerings/'.$group->id, ['reason' => 'Grupo duplicado creado por error', 'confirmation' => $group->group_code])->assertRedirect('/offerings');
        $this->assertModelMissing($group);
        $log = AuditLog::where('action', 'offering.deleted')->sole();
        $this->assertSame((string) $group->id, $log->entity_id);
        $this->assertSame($group->group_code, $log->before['group_code']);
        $this->assertSame($group->subject->name, $log->before['subject_name']);
        $this->assertNull($log->after);
        $this->delete('/admin/offerings/'.$group->id, ['reason' => 'Reintento sin otro borrado', 'confirmation' => $group->group_code])->assertNotFound();
        $this->assertSame(1, AuditLog::where('action', 'offering.deleted')->count());
    }

    public function test_only_untouched_generated_empty_periods_can_be_removed(): void
    {
        $admin = User::factory()->coordinator()->create();
        $cycle = AcademicCycle::factory()->create();
        $format = app(CycleFormatManager::class)->save($admin, ['cycle_id' => $cycle->id, 'name' => 'Calendario A04', 'active' => true, 'periods' => [['number' => 1, 'starts_on' => '2026-01-01', 'ends_on' => '2026-06-30']]]);
        $base = Offering::factory()->create(['cycle_id' => $cycle->id]);
        $group = app(AcademicRegistry::class)->saveOffering($admin, ['cycle_id' => $cycle->id, 'subject_id' => $base->subject_id, 'teacher_id' => $base->teacher_id, 'group_code' => 'A04', 'capacity' => 30, 'cycle_format_id' => $format->id]);
        $period = $group->periods()->sole();
        $this->assertSame([], app(OfferingLifecycle::class)->deletionIssues($group));
        app(OfferingLifecycle::class)->delete($admin, $group, 'A04', 'Grupo con calendario inicial creado por error');
        $this->assertModelMissing($period);
        $this->assertDatabaseHas('audit_logs', ['entity_type' => 'AssessmentPeriod', 'entity_id' => (string) $period->id, 'action' => 'period.empty_removed']);
    }

    public static function dependencies(): array
    {
        return array_map(fn ($case) => [$case], ['enrollment', 'session', 'attendance', 'permission', 'portal', 'event', 'period', 'report', 'reopened', 'closed_cycle', 'news']);
    }

    #[DataProvider('dependencies')]
    public function test_deletion_is_blocked_by_academic_and_historical_dependencies(string $case): void
    {
        $group = Offering::factory()->create();
        $admin = User::factory()->coordinator()->create();
        if ($case === 'closed_cycle') {
            $group->cycle->update(['status' => 'closed']);
        } elseif ($case === 'enrollment') {
            Enrollment::factory()->create(['offering_id' => $group->id]);
        } elseif ($case === 'news') {
            DB::table('news')->insert(['offering_id' => $group->id, 'author_id' => $admin->id, 'title' => 'Historia A04', 'content' => 'Conservar', 'created_at' => now(), 'updated_at' => now()]);
        } else {
            $period = AssessmentPeriod::factory()->create(['offering_id' => $group->id]);
            if ($case === 'reopened') {
                $period->update(['reopened_at' => now()]);
            } elseif ($case === 'report') {
                ReportVersion::create(['period_id' => $period->id, 'version' => 1, 'snapshot' => [], 'policy_version' => $period->policy_version, 'generated_by' => $admin->id]);
            } elseif ($case !== 'period') {
                $session = $this->makeSession($period);
                $enrollment = Enrollment::factory()->create(['offering_id' => $group->id]);
                if ($case === 'attendance') {
                    Attendance::create(['offering_id' => $group->id, 'session_id' => $session->id, 'enrollment_id' => $enrollment->id, 'status' => 'present', 'recorded_by' => $admin->id, 'recorded_at' => now()]);
                } elseif ($case === 'permission') {
                    $this->permission($session, $enrollment);
                } elseif ($case === 'portal') {
                    $session->portals()->create(['opens_at' => now(), 'expires_at' => now()->addMinute(), 'token_hash' => hash('sha256', 'dependency'), 'enabled_methods' => ['nfc']]);
                } elseif ($case === 'event') {
                    DB::table('attendance_events')->insert(['event_id' => (string) Str::uuid(), 'actor_id' => $admin->id, 'session_id' => $session->id, 'request_hash' => hash('sha256', 'event'), 'outcome' => 'rejected', 'received_at' => now()]);
                }
            }
        }
        $this->actingAs($admin)->deleteJson('/admin/offerings/'.$group->id, ['reason' => 'No borrar grupo con dependencia', 'confirmation' => $group->group_code])->assertUnprocessable()->assertJsonValidationErrors('offering');
        $this->assertModelExists($group);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'offering.deleted']);
    }
}
