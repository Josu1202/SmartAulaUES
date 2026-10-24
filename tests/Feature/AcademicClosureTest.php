<?php

namespace Tests\Feature;

use App\Actions\Academic\AcademicRegistry;
use App\Actions\Academic\UserManager;
use App\Actions\Attendance\AbsenceWorkflow;
use App\Actions\Attendance\ClosureWorkflow;
use App\Actions\Attendance\CorrectAttendance;
use App\Actions\Attendance\RegisterAttendance;
use App\Actions\Attendance\ReviewSession;
use App\Actions\Attendance\SessionWorkflow;
use App\Enums\SessionState;
use App\Models\AbsenceRequest;
use App\Models\AssessmentPeriod;
use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\NfcCredential;
use App\Models\Offering;
use App\Models\ReportVersion;
use App\Models\User;
use App\Queries\AttendanceSummary;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AcademicClosureTest extends TestCase
{
    private function scenario(): array
    {
        $this->travelTo(now()->setDateTime(2026, 10, 23, 6, 0, 0));
        $period = AssessmentPeriod::factory()->create(['starts_on' => '2026-10-01', 'ends_on' => '2026-10-22']);
        $period->offering->cycle->update(['ends_on' => '2026-10-22']);
        $enrollment = Enrollment::factory()->create(['offering_id' => $period->offering_id]);
        $admin = User::factory()->coordinator()->create();

        return [$period, $enrollment, $admin];
    }

    private function makeSession(AssessmentPeriod $period, array $attributes = []): ClassSession
    {
        return ClassSession::factory()->create(['offering_id' => $period->offering_id, 'period_id' => $period->id, 'starts_at' => '2026-10-22T16:00:00Z', 'ends_at' => '2026-10-22T17:00:00Z', ...$attributes]);
    }

    private function attendance(ClassSession $session, Enrollment $enrollment, string $status = 'present'): Attendance
    {
        return Attendance::create(['session_id' => $session->id, 'offering_id' => $session->offering_id, 'enrollment_id' => $enrollment->id, 'status' => $status, 'recorded_at' => now(), 'recorded_by' => $session->offering->teacher_id]);
    }

    private function permission(ClassSession $session, Enrollment $enrollment, string $status = 'pending'): AbsenceRequest
    {
        return AbsenceRequest::create(['session_id' => $session->id, 'offering_id' => $session->offering_id, 'enrollment_id' => $enrollment->id, 'status' => $status, 'reason' => 'MOTIVO_PRIVADO_A03', 'evidence_path' => 'absence-evidence/PRIVADO.pdf']);
    }

    public function test_actual_start_is_saved_and_backup_uses_local_midnight_instead_of_end_or_twenty_four_hours(): void
    {
        [$period, $enrollment] = $this->scenario();
        $session = $this->makeSession($period);
        $this->travelTo(now()->setDateTime(2026, 10, 22, 16, 7));
        app(SessionWorkflow::class)->transition($period->offering->teacher, $session, 'start');
        $this->assertSame('2026-10-22 16:07:00', $session->fresh()->started_at->format('Y-m-d H:i:s'));
        $this->travelTo(now()->setDateTime(2026, 10, 23, 5, 59, 59));
        $this->assertFalse(app(SessionWorkflow::class)->finishOverdue($session));
        $this->assertDatabaseCount('attendances', 0);
        $this->travelTo(now()->addSecond());
        $this->assertTrue(app(SessionWorkflow::class)->finishOverdue($session));
        $this->assertSame(SessionState::Completed, $session->fresh()->state);
        $this->assertSame('2026-10-23 06:00:00', $session->fresh()->finalized_at->format('Y-m-d H:i:s'));
        $this->assertDatabaseHas('attendances', ['enrollment_id' => $enrollment->id, 'status' => 'absent', 'recorded_by' => null]);
        $this->assertFalse(app(SessionWorkflow::class)->finishOverdue($session));
        $this->assertDatabaseHas('audit_logs', ['action' => 'session.auto_finished', 'actor_id' => null]);
    }

    public function test_daily_finalization_preserves_presence_approved_permissions_inclusive_withdrawal_and_closes_readers(): void
    {
        [$period, $present] = $this->scenario();
        $session = $this->makeSession($period, ['state' => 'in_progress', 'started_at' => '2026-10-22T16:10:00Z']);
        $withdrawn = Enrollment::factory()->create(['offering_id' => $period->offering_id, 'active_to' => '2026-10-22']);
        $outside = Enrollment::factory()->create(['offering_id' => $period->offering_id, 'active_from' => '2026-10-23']);
        $this->permission($session, $withdrawn, 'approved');
        $attendance = $this->attendance($session, $present);
        $before = $attendance->fresh()->getAttributes();
        $portal = $session->portals()->create(['opens_at' => '2026-10-22T16:10:00Z', 'expires_at' => '2026-10-22T16:30:00Z', 'enabled_methods' => ['manual'], 'token_hash' => hash('sha256', 'a03')]);
        $reader = $portal->readerSessions()->create(['id' => (string) Str::uuid(), 'teacher_id' => $session->offering->teacher_id, 'label' => 'A03', 'expires_at' => '2026-10-22T16:30:00Z', 'state' => 'active']);
        app(SessionWorkflow::class)->finishOverdue($session);
        $this->assertSame($before, $attendance->fresh()->getAttributes());
        $this->assertDatabaseHas('attendances', ['enrollment_id' => $withdrawn->id, 'status' => 'justified']);
        $this->assertDatabaseMissing('attendances', ['enrollment_id' => $outside->id]);
        $this->assertNotNull($portal->fresh()->closed_at);
        $this->assertSame('closed', $reader->fresh()->state);
    }

    public function test_still_in_progress_after_schedule_never_extends_attendance_window(): void
    {
        [$period, $enrollment] = $this->scenario();
        $session = $this->makeSession($period, ['state' => 'in_progress', 'started_at' => '2026-10-22T16:10:00Z']);
        $this->travelTo(now()->setDateTime(2026, 10, 22, 18, 0));
        $portal = $session->portals()->create(['opens_at' => '2026-10-22T16:00:00Z', 'expires_at' => '2026-10-22T19:00:00Z', 'enabled_methods' => ['qr', 'link', 'nfc', 'manual'], 'token_hash' => hash('sha256', 'expired-window')]);
        foreach (['qr', 'link', 'nfc', 'manual'] as $method) {
            $actor = in_array($method, ['qr', 'link']) ? $enrollment->student : $period->offering->teacher;
            $response = app(RegisterAttendance::class)->record($actor, $session, $method, ['event_id' => (string) Str::uuid(), 'student_id' => $enrollment->student_id, 'portal_id' => $portal->id, 'portal_token' => 'expired-window', 'reason' => 'Fuera del horario de clase']);
            $this->assertSame(409, $response['http']);
            $this->assertSame('La sesión ya no admite registros.', $response['body']['message']);
        }
        $this->assertDatabaseCount('attendances', 0);
        $this->assertFalse(app(SessionWorkflow::class)->finishOverdue($session));
        $this->actingAs($period->offering->teacher)->get('/sessions/'.$session->id)->assertOk()
            ->assertSee('El horario de registro terminó.')->assertSee('Finalizar clase')
            ->assertDontSee('data-open-portal', false)->assertDontSee('data-live-start', false)->assertDontSee('data-manual-attendance', false)
            ->assertSee('data-location-measurement', false);
        $this->travelTo(now()->setTime(16, 20));
        $this->get('/sessions/'.$session->id)->assertOk()->assertSee('data-open-portal', false)
            ->assertSee('data-live-start', false)->assertSee('data-manual-attendance', false);
        $this->travelTo(now()->setTime(17, 0));
        $this->get('/sessions/'.$session->id)->assertOk()->assertSee('El horario de registro terminó.')
            ->assertDontSee('data-open-portal', false)->assertDontSee('data-manual-attendance', false);
    }

    public function test_cross_midnight_schedule_is_not_truncated_and_legacy_start_is_not_invented(): void
    {
        [$period] = $this->scenario();
        $period->update(['ends_on' => '2026-10-23']);
        $crossing = $this->makeSession($period, ['starts_at' => '2026-10-23T05:30:00Z', 'ends_at' => '2026-10-23T07:00:00Z', 'started_at' => '2026-10-23T05:40:00Z', 'state' => 'in_progress']);
        $legacy = $this->makeSession($period, ['state' => 'in_progress']);
        $this->assertFalse(app(SessionWorkflow::class)->finishOverdue($crossing));
        $this->assertFalse(app(SessionWorkflow::class)->finishOverdue($legacy));
        $this->travelTo(now()->setTime(7, 0));
        $this->assertTrue(app(SessionWorkflow::class)->finishOverdue($crossing));
        $this->assertNull($legacy->fresh()->started_at);
        app(SessionWorkflow::class)->transition($period->offering->teacher, $legacy, 'finish');
        $this->assertSame(SessionState::Completed, $legacy->fresh()->state);
        $this->assertNull($legacy->fresh()->started_at);
    }

    public function test_explicit_start_recovery_requires_a_single_reliable_audit_transition(): void
    {
        [$period, , $admin] = $this->scenario();
        $session = $this->makeSession($period, ['state' => 'in_progress']);
        AuditLog::create(['entity_type' => 'ClassSession', 'entity_id' => (string) $session->id, 'action' => 'session.start', 'before' => ['state' => 'scheduled'], 'after' => ['state' => 'in_progress', 'started_at' => '2026-10-22T16:03:00Z'], 'created_at' => '2026-10-22T15:50:00Z']);
        app(ReviewSession::class)->execute($admin, $session, 'recover-start', 'Inicio recuperado de auditoría revisada');
        $this->assertSame('2026-10-22 16:03:00', $session->fresh()->started_at->format('Y-m-d H:i:s'));
        $other = $this->makeSession($period, ['state' => 'in_progress']);
        AuditLog::create(['entity_type' => 'ClassSession', 'entity_id' => (string) $other->id, 'action' => 'session.start', 'before' => ['state' => 'scheduled'], 'after' => ['state' => 'in_progress'], 'created_at' => '2026-10-22T16:04:00Z']);
        $this->assertNull(app(ReviewSession::class)->recoverableStart($other));
        $this->expectException(ValidationException::class);
        app(ReviewSession::class)->execute($admin, $other, 'recover-start', 'No hay evidencia suficiente');
    }

    public function test_period_remains_open_until_its_final_local_day_ends_and_programmed_sessions_are_classified_only_at_close(): void
    {
        [$period, $enrollment] = $this->scenario();
        $session = $this->makeSession($period);
        $this->travelTo(now()->setDateTime(2026, 10, 23, 5, 59, 59));
        $this->artisan('attendance:process-closures')->assertSuccessful();
        $this->assertSame('open', $period->fresh()->state);
        $this->assertSame(SessionState::Scheduled, $session->fresh()->state);
        $this->travelTo(now()->addSecond());
        $this->artisan('attendance:process-closures')->assertSuccessful();
        $this->assertSame('closed', $period->fresh()->state);
        $this->assertSame(SessionState::Unstarted, $session->fresh()->state);
        $this->assertDatabaseCount('attendances', 0);
        $result = app(AttendanceSummary::class)->forEnrollment($enrollment->student, $enrollment, $period);
        $this->assertSame('no_sessions', $result['state']);
        $this->assertSame(1, $result['counts']['unstarted']);
        $this->assertNull($result['percentage']);
        $this->assertNull($result['conclusion']);
        $this->assertSame(0, $result['counts']['overdue_unfinished']);
        $this->actingAs($enrollment->student)->get('/offerings/'.$period->offering_id)->assertOk()->assertSee('Sin sesiones evaluables')->assertSee('Sin inicio registrado')->assertDontSee('No alcanza');
    }

    public function test_pending_permission_blocks_close_but_not_backup_finalization_and_retries_without_duplicate_audit(): void
    {
        [$period, $enrollment, $admin] = $this->scenario();
        $session = $this->makeSession($period, ['state' => 'in_progress', 'started_at' => '2026-10-22T16:10:00Z']);
        $permission = $this->permission($session, $enrollment);
        $workflow = app(ClosureWorkflow::class);
        $workflow->autoClosePeriod($period);
        $this->assertSame(SessionState::Completed, $session->fresh()->state);
        $this->assertSame('open', $period->fresh()->state);
        $this->assertNotEmpty($period->fresh()->closure_issues);
        $private = app(AttendanceSummary::class)->forOffering($enrollment->student, $period->offering, $period->id);
        $this->assertSame([], $private['period']->closure_issues);
        $this->assertSame([], $private['periods']->first()->closure_issues);
        $count = AuditLog::where('action', 'period.closure_blocked')->count();
        $workflow->autoClosePeriod($period);
        $this->assertSame($count, AuditLog::where('action', 'period.closure_blocked')->count());
        app(AbsenceWorkflow::class)->resolve($admin, $permission, 'approved', 'Permiso verificado y resuelto');
        $workflow->autoClosePeriod($period);
        $this->assertSame('closed', $period->fresh()->state);
        $this->assertSame('100.00', ReportVersion::sole()->snapshot['rows'][0]['calculation']['percentage']);
    }

    public function test_scheduled_with_attendance_or_pending_permission_is_not_classified_and_administrative_reconciliation_preserves_history(): void
    {
        [$period, $enrollment, $admin] = $this->scenario();
        $session = $this->makeSession($period);
        $attendance = $this->attendance($session, $enrollment);
        app(ClosureWorkflow::class)->autoClosePeriod($period);
        $this->assertSame(SessionState::Scheduled, $session->fresh()->state);
        $this->assertSame('open', $period->fresh()->state);
        app(ReviewSession::class)->execute($admin, $session, 'reconcile', 'Presencia existente y evidencia de clase verificadas');
        $this->assertSame('present', $attendance->fresh()->status->value);
        $this->assertSame(SessionState::Completed, $session->fresh()->state);
        app(ClosureWorkflow::class)->closePeriod($admin, $period);
        $this->assertSame('closed', $period->fresh()->state);
    }

    public function test_preventive_approved_permission_is_resolved_administratively_without_inventing_a_class(): void
    {
        [$period, $enrollment, $admin] = $this->scenario();
        $session = $this->makeSession($period);
        $permission = $this->permission($session, $enrollment, 'approved');
        app(ClosureWorkflow::class)->autoClosePeriod($period);
        $this->assertSame(SessionState::Scheduled, $session->fresh()->state);
        $this->assertDatabaseCount('report_versions', 0);
        app(ReviewSession::class)->reopenDecision($admin, $permission, 'Permiso preventivo de sesión sin inicio; revisar decisión');
        app(AbsenceWorkflow::class)->resolve($admin, $permission, 'rejected', 'No existe evidencia de inicio registrado');
        app(ClosureWorkflow::class)->closePeriod($admin, $period);
        $this->assertSame(SessionState::Unstarted, $session->fresh()->state);
        $this->assertDatabaseCount('attendances', 0);
        $this->assertDatabaseHas('audit_logs', ['action' => 'request.reopened']);
    }

    public function test_missing_and_inconsistent_results_block_until_explicit_finalization_review(): void
    {
        [$period, $enrollment, $admin] = $this->scenario();
        $session = $this->makeSession($period, ['state' => 'completed']);
        app(ClosureWorkflow::class)->autoClosePeriod($period);
        $this->assertSame('open', $period->fresh()->state);
        $this->assertDatabaseCount('attendances', 0);
        app(ReviewSession::class)->execute($admin, $session, 'reconcile', 'Reparación explícita de finalización incompleta');
        $this->assertDatabaseHas('attendances', ['enrollment_id' => $enrollment->id, 'status' => 'absent']);
        $this->permission($session, $enrollment, 'approved');
        app(ClosureWorkflow::class)->autoClosePeriod($period);
        $this->assertSame('open', $period->fresh()->state);
        app(ReviewSession::class)->execute($admin, $session, 'reconcile', 'Conciliar ausencia con aprobación documentada');
        app(ClosureWorkflow::class)->closePeriod($admin, $period);
        $this->assertSame('closed', $period->fresh()->state);
    }

    public function test_missing_policy_blocks_even_without_enrollments_or_sessions(): void
    {
        [$period] = $this->scenario();
        $period->update(['policy_version' => 'desconocida']);
        app(ClosureWorkflow::class)->autoClosePeriod($period);
        $this->assertSame('open', $period->fresh()->state);
        $this->assertStringContainsString('Reglamento', implode(' ', $period->fresh()->closure_issues));
    }

    public function test_snapshot_covers_all_students_and_reads_frozen_values_with_private_historical_access(): void
    {
        [$period, $mine, $admin] = $this->scenario();
        $others = Enrollment::factory()->count(21)->create(['offering_id' => $period->offering_id]);
        $session = $this->makeSession($period, ['state' => 'in_progress', 'started_at' => '2026-10-22T16:10:00Z']);
        $attendance = $this->attendance($session, $mine);
        $others->first()->student->update(['name' => 'OTRO_ESTUDIANTE_PRIVADO_A03']);
        app(ClosureWorkflow::class)->closePeriod($admin, $period);
        $report = ReportVersion::sole();
        $this->assertCount(22, $report->snapshot['rows']);
        $this->assertStringNotContainsString('MOTIVO_PRIVADO', json_encode($report->snapshot));
        // Simulate unauthorized external corruption to prove closed reads never silently recalculate.
        DB::table('attendances')->where('id', $attendance->id)->update(['status' => 'absent']);
        $result = app(AttendanceSummary::class)->forEnrollment($mine->student, $mine, $period);
        $this->assertSame('100.00', $result['percentage']);
        $this->assertSame('snapshot', $result['source']);
        $batch = app(AttendanceSummary::class)->forOffering($admin, $period->offering, $period->id, page: 2);
        $this->assertSame(22, $batch['rows']->total());
        $this->assertCount(2, $batch['rows']);
        $this->actingAs($mine->student)->get('/offerings/'.$period->offering_id.'?period_id='.$period->id.'&report_version=1')->assertOk()->assertSee('100.00 %')->assertDontSee('OTRO_ESTUDIANTE_PRIVADO_A03');
        $private = app(AttendanceSummary::class)->forOffering($mine->student, $period->offering, $period->id, reportVersion: 1);
        $this->assertSame(1, $private['rows']->total());
        $this->assertStringNotContainsString('OTRO_ESTUDIANTE_PRIVADO_A03', json_encode($private));
    }

    public function test_snapshot_and_closure_rollback_together_when_generation_fails(): void
    {
        [$period, , $admin] = $this->scenario();
        $session = $this->makeSession($period);
        $event = 'eloquent.creating: '.ReportVersion::class;
        Event::listen($event, fn () => throw new \RuntimeException('Fallo de generación controlado'));
        try {
            app(ClosureWorkflow::class)->closePeriod($admin, $period);
            $this->fail('Debió fallar la generación.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Fallo de generación controlado', $exception->getMessage());
        } finally {
            Event::forget($event);
        }
        $this->assertSame('open', $period->fresh()->state);
        $this->assertSame(SessionState::Scheduled, $session->fresh()->state);
        $this->assertDatabaseCount('report_versions', 0);
        app(ClosureWorkflow::class)->closePeriod($admin, $period);
        $this->assertDatabaseCount('report_versions', 1);
    }

    public function test_closed_period_without_valid_snapshot_shows_inconsistency_and_no_live_percentage(): void
    {
        [$period, $enrollment, $admin] = $this->scenario();
        $session = $this->makeSession($period, ['state' => 'completed']);
        $this->attendance($session, $enrollment);
        $period->update(['state' => 'closed', 'closed_at' => now()]);
        $this->actingAs($enrollment->student)->get('/offerings/'.$period->offering_id)->assertOk()->assertSee('Cierre inconsistente')->assertDontSee('100.00 %');
        $this->withSession(['password_hash_web' => $admin->password])->actingAs($admin)->post('/admin/periods/'.$period->id.'/close')->assertSessionHasErrors('closure');
    }

    public function test_reopening_requires_admin_reason_and_manual_reclosure_creates_a_new_immutable_version(): void
    {
        [$period, $enrollment, $admin] = $this->scenario();
        $session = $this->makeSession($period, ['state' => 'completed']);
        $attendance = $this->attendance($session, $enrollment);
        $workflow = app(ClosureWorkflow::class);
        $workflow->closePeriod($admin, $period);
        $first = ReportVersion::sole()->getAttributes();
        $workflow->closePeriod($admin, $period);
        $this->assertDatabaseCount('report_versions', 1);
        $this->actingAs($period->offering->teacher)->post('/admin/periods/'.$period->id.'/reopen', ['reason' => 'Intento docente'])->assertForbidden();
        $this->withSession(['password_hash_web' => $admin->password])->actingAs($admin)->post('/admin/periods/'.$period->id.'/reopen', [])->assertSessionHasErrors('reason');
        $this->post('/admin/periods/'.$period->id.'/reopen', ['reason' => 'Corrección administrativa documentada'])->assertSessionHasNoErrors();
        $workflow->autoClosePeriod($period);
        $this->assertSame('open', $period->fresh()->state);
        $this->assertSame(SessionState::Completed, $session->fresh()->state);
        app(CorrectAttendance::class)->execute($admin, $attendance, 'absent', 'Corrección autorizada en revisión');
        $this->get('/offerings/'.$period->offering_id)->assertOk()->assertSee('En revisión')->assertSee('0.00 %');
        $workflow->closePeriod($admin, $period);
        $this->assertDatabaseCount('report_versions', 2);
        $this->assertSame($first, ReportVersion::orderBy('id')->first()->getAttributes());
        $this->assertSame('100.00', app(AttendanceSummary::class)->forEnrollment($enrollment->student, $enrollment, $period, reportVersion: 1)['percentage']);
        $this->assertSame('0.00', app(AttendanceSummary::class)->forEnrollment($enrollment->student, $enrollment, $period)['percentage']);
    }

    public function test_closed_period_blocks_attendance_permissions_session_changes_and_bulk_enrollment_eligibility(): void
    {
        [$period, $enrollment, $admin] = $this->scenario();
        $session = $this->makeSession($period, ['state' => 'completed']);
        $attendance = $this->attendance($session, $enrollment, 'absent');
        app(ClosureWorkflow::class)->closePeriod($admin, $period);
        $this->actingAs($admin)->post('/sessions/'.$session->id.'/attendances/'.$attendance->id.'/correct', ['status' => 'present', 'reason' => 'Cambio fuera de revisión'])->assertSessionHasErrors('session');
        $this->post('/offerings/'.$period->offering_id.'/sessions', ['period_id' => $period->id, 'starts_at' => '2026-10-22T12:00', 'ends_at' => '2026-10-22T13:00', 'modality' => 'online'])->assertSessionHasErrors('period_id');
        $this->withSession(['password_hash_web' => $enrollment->student->password])->actingAs($enrollment->student)->post('/sessions/'.$session->id.'/request', ['reason' => 'Permiso fuera de revisión'])->assertSessionHasErrors('session');
        $new = User::factory()->create();
        $this->withSession(['password_hash_web' => $admin->password])->actingAs($admin)->post('/admin/offerings/'.$period->offering_id.'/students', ['student_ids' => [$new->id], 'active_from' => '2026-10-01'])->assertSessionHasErrors('active_from');
        $this->assertDatabaseCount('enrollments', 1);
        $this->put('/admin/offerings/'.$period->offering_id.'/enrollments/'.$enrollment->id, ['active_from' => '2026-10-02', 'active_to' => null, 'reason' => 'Cambio de elegibilidad cerrado'])->assertSessionHasErrors('active_from');
    }

    public function test_cycle_order_and_reopened_cycle_require_manual_close_without_reopening_all_periods(): void
    {
        [$period, , $admin] = $this->scenario();
        $cycle = $period->offering->cycle;
        $workflow = app(ClosureWorkflow::class);
        $workflow->autoCloseCycle($cycle);
        $this->assertSame('active', $cycle->fresh()->status);
        $this->assertStringContainsString('cómputo 1', implode(' ', $cycle->fresh()->closure_issues));
        $workflow->closePeriod($admin, $period);
        $workflow->autoCloseCycle($cycle);
        $this->assertSame('closed', $cycle->fresh()->status);
        $this->actingAs($admin)->post('/admin/periods/'.$period->id.'/reopen', ['reason' => 'Primero debe reabrirse ciclo'])->assertSessionHasErrors('closure');
        $workflow->reopenCycle($admin, $cycle, 'Revisión extraordinaria del ciclo');
        $this->assertSame('closed', $period->fresh()->state);
        $workflow->reopenPeriod($admin, $period, 'Revisión del cómputo afectado');
        $this->artisan('attendance:process-closures')->assertSuccessful();
        $this->assertSame('active', $cycle->fresh()->status);
        $this->assertSame('open', $period->fresh()->state);
        $workflow->closePeriod($admin, $period);
        $workflow->autoCloseCycle($cycle);
        $this->assertSame('active', $cycle->fresh()->status);
        $workflow->closeCycle($admin, $cycle);
        $this->assertSame('closed', $cycle->fresh()->status);
    }

    public function test_scheduler_recovers_missed_runs_and_one_blocked_period_does_not_stop_another(): void
    {
        [$blocked] = $this->scenario();
        $this->makeSession($blocked, ['state' => 'in_progress']);
        $good = AssessmentPeriod::factory()->create(['offering_id' => $blocked->offering_id, 'number' => 2, 'starts_on' => '2026-10-01', 'ends_on' => '2026-10-22']);
        $this->makeSession($good, ['state' => 'cancelled', 'change_reason' => 'Cancelación previa documentada']);
        $this->travelTo(now()->addDays(3));
        $this->artisan('attendance:process-closures')->assertSuccessful();
        $this->assertSame('open', $blocked->fresh()->state);
        $this->assertSame('closed', $good->fresh()->state);
        $count = AuditLog::count();
        $this->artisan('attendance:process-closures')->assertSuccessful();
        $this->assertSame($count, AuditLog::count());
        $this->assertDatabaseCount('report_versions', 1);
    }

    public function test_pending_preventive_permission_keeps_scheduled_session_for_review_until_resolved(): void
    {
        [$period, $enrollment, $admin] = $this->scenario();
        $session = $this->makeSession($period);
        $request = $this->permission($session, $enrollment);
        app(ClosureWorkflow::class)->autoClosePeriod($period);
        $this->assertSame(SessionState::Scheduled, $session->fresh()->state);
        $this->assertDatabaseCount('attendances', 0);
        app(AbsenceWorkflow::class)->resolve($admin, $request, 'rejected', 'Solicitud preventiva sin evidencia de inicio');
        app(ClosureWorkflow::class)->autoClosePeriod($period);
        $this->assertSame('closed', $period->fresh()->state);
        $this->assertSame(SessionState::Unstarted, $session->fresh()->state);
    }

    public function test_snapshot_corruption_is_reported_and_the_model_rejects_rewriting_a_version(): void
    {
        [$period, $enrollment, $admin] = $this->scenario();
        app(ClosureWorkflow::class)->closePeriod($admin, $period);
        $report = ReportVersion::sole();
        DB::table('report_versions')->where('id', $report->id)->update(['snapshot_hash' => str_repeat('0', 64)]);
        $this->actingAs($enrollment->student)->get('/offerings/'.$period->offering_id)->assertOk()->assertSee('Cierre inconsistente');
        $this->expectException(\LogicException::class);
        $report->update(['snapshot' => ['alterado' => true]]);
    }

    public function test_administrative_endpoints_and_historical_queries_enforce_roles_and_resource_scope(): void
    {
        [$period, $enrollment, $admin] = $this->scenario();
        $session = $this->makeSession($period, ['state' => 'completed']);
        $this->attendance($session, $enrollment);
        app(ClosureWorkflow::class)->closePeriod($admin, $period);
        foreach ([$enrollment->student, $period->offering->teacher] as $actor) {
            $this->withSession(['password_hash_web' => $actor->password])->actingAs($actor);
            foreach (['/admin/periods/'.$period->id.'/close', '/admin/periods/'.$period->id.'/reopen', '/admin/cycles/'.$period->offering->cycle_id.'/close', '/admin/cycles/'.$period->offering->cycle_id.'/reopen', '/admin/sessions/'.$session->id.'/review/reconcile'] as $url) {
                $this->post($url, ['reason' => 'Acceso directo sin autorización administrativa'])->assertForbidden();
            }
        }
        $foreign = User::factory()->teacher()->create();
        $this->withSession(['password_hash_web' => $foreign->password])->actingAs($foreign)->get('/offerings/'.$period->offering_id.'?report_version=1')->assertForbidden();
        $this->withSession(['password_hash_web' => $admin->password])->actingAs($admin)->get('/offerings/'.$period->offering_id.'?report_version=99')->assertNotFound();
        $this->get('/offerings/'.$period->offering_id.'?period_id='.AssessmentPeriod::factory()->create()->id.'&report_version=1')->assertNotFound();
    }

    public function test_csv_confirmation_cannot_change_closed_eligibility_even_when_preview_precedes_closure(): void
    {
        [$period, , $admin] = $this->scenario();
        $student = User::factory()->create(['student_code' => 'A03CSV001']);
        $this->actingAs($admin)->post('/admin/imports/preview', ['kind' => 'enrollments', 'offering_id' => $period->offering_id,
            'file' => UploadedFile::fake()->createWithContent('inscripciones.csv', "student_code,active_from,active_to\n".$student->student_code.",2026-10-01,\n")])->assertSessionHasNoErrors();
        $preview = session('academic_import');
        app(ClosureWorkflow::class)->closePeriod($admin, $period);
        $this->post('/admin/imports/confirm', ['nonce' => $preview['nonce']])->assertSessionHasErrors('active_from');
        $this->assertDatabaseCount('enrollments', 1);
        $this->assertDatabaseCount('report_versions', 1);
    }

    public function test_independent_contact_and_enrollment_changes_outside_closed_period_remain_available(): void
    {
        [$period, $enrollment, $admin] = $this->scenario();
        $period->offering->cycle->update(['ends_on' => '2026-12-31']);
        app(ClosureWorkflow::class)->closePeriod($admin, $period);
        app(AcademicRegistry::class)->saveEnrollment($admin, $period->offering, ['active_from' => $enrollment->active_from->toDateString(), 'active_to' => '2026-10-23', 'reason' => 'Retiro posterior al cómputo cerrado'], $enrollment);
        $student = $enrollment->student;
        $student->update(['student_code' => 'A03CONTACT01']);
        app(UserManager::class)->save($admin, ['name' => $student->name, 'email' => 'contacto-actualizado@example.test', 'role' => 'student', 'active' => true, 'student_code' => $student->student_code], $student);
        $this->assertSame('2026-10-23', $enrollment->fresh()->active_to->toDateString());
        $this->assertSame('contacto-actualizado@example.test', $student->fresh()->email);
        $this->assertSame('closed', $period->fresh()->state);
    }

    public function test_operational_failure_rolls_back_only_affected_period_and_recovers_on_next_execution(): void
    {
        [$bad] = $this->scenario();
        $good = AssessmentPeriod::factory()->create(['ends_on' => '2026-10-22']);
        $this->makeSession($bad);
        $event = 'eloquent.creating: '.ReportVersion::class;
        Event::listen($event, function (ReportVersion $report) use ($bad): void {
            if ($report->period_id === $bad->id) {
                throw new \RuntimeException('Fallo operativo controlado A03');
            }
        });
        try {
            $this->artisan('attendance:process-closures')->assertFailed();
        } finally {
            Event::forget($event);
        }
        $this->assertSame('open', $bad->fresh()->state);
        $this->assertSame('closed', $good->fresh()->state);
        $this->assertSame(SessionState::Scheduled, $bad->sessions()->sole()->state);
        $this->artisan('attendance:process-closures')->assertSuccessful();
        $this->assertSame('closed', $bad->fresh()->state);
        $this->assertSame(1, $good->reports()->count());
    }

    public function test_empty_period_can_close_but_general_cycle_form_cannot_bypass_transitions(): void
    {
        [$period, $enrollment, $admin] = $this->scenario();
        $enrollment->delete();
        app(ClosureWorkflow::class)->closePeriod($admin, $period);
        $this->assertSame([], ReportVersion::sole()->snapshot['rows']);
        $cycle = $period->offering->cycle;
        $this->actingAs($admin)->put('/admin/cycles/'.$cycle->id, ['code' => $cycle->code, 'starts_on' => $cycle->starts_on->toDateString(), 'ends_on' => $cycle->ends_on->toDateString(), 'status' => 'closed', 'reason' => 'Intento de eludir cierre específico'])->assertSessionHasErrors('status');
        app(ClosureWorkflow::class)->closeCycle($admin, $cycle);
        app(ClosureWorkflow::class)->reopenCycle($admin, $cycle, 'Revisión administrativa del ciclo vacío');
        $this->put('/admin/cycles/'.$cycle->id, ['code' => $cycle->code, 'starts_on' => $cycle->starts_on->toDateString(), 'ends_on' => $cycle->ends_on->toDateString(), 'status' => 'planned', 'reason' => 'Intento de borrar revisión mediante estado genérico'])->assertSessionHasErrors('status');
    }

    public function test_ten_scheduled_eight_finished_two_unstarted_freeze_exact_a01_thresholds_and_safe_explanations(): void
    {
        [$period, $perfect, $admin] = $this->scenario();
        $below = Enrollment::factory()->create(['offering_id' => $period->offering_id]);
        $exact = Enrollment::factory()->create(['offering_id' => $period->offering_id]);
        $above = Enrollment::factory()->create(['offering_id' => $period->offering_id]);
        for ($i = 0; $i < 10; $i++) {
            $start = now()->setDateTime(2026, 10, $i + 1, 16, 0);
            $session = $this->makeSession($period, ['starts_at' => $start, 'ends_at' => $start->copy()->addHour()]);
            if ($i >= 8) {
                continue;
            }
            $session->update(['state' => 'in_progress', 'started_at' => $start->copy()->addMinute()]);
            $this->attendance($session, $perfect);
            foreach ([[$below, 5], [$exact, 5], [$above, 7]] as [$enrollment, $limit]) {
                if ($i < $limit) {
                    $this->attendance($session, $enrollment);
                }
            }
            if ($i === 5) {
                $this->permission($session, $exact, 'approved');
            }
        }
        $this->artisan('attendance:process-closures')->assertSuccessful();
        $this->assertSame(8, $period->sessions()->where('state', 'completed')->count());
        $this->assertSame(2, $period->sessions()->where('state', 'unstarted')->count());
        $this->assertSame(32, Attendance::count());
        $report = $period->reports()->sole();
        $rows = collect($report->snapshot['rows'])->keyBy('enrollment_id');
        foreach ([[$perfect, 8, '100.00', true], [$below, 5, '62.50', false], [$exact, 6, '75.00', true], [$above, 7, '87.50', true]] as [$enrollment, $numerator, $percentage, $meets]) {
            $calculation = $rows[$enrollment->id]['calculation'];
            $this->assertSame(['numerator' => $numerator, 'denominator' => 8], $calculation['fraction']);
            $this->assertSame($percentage, $calculation['percentage']);
            $this->assertSame($meets, $calculation['meets_minimum']);
            $this->assertSame(2, $calculation['counts']['unstarted']);
            $this->assertCount(10, $calculation['session_facts']);
        }
        $this->assertSame(1, $rows[$exact->id]['calculation']['counts']['justified']);
        $this->assertSame(2, $rows[$exact->id]['calculation']['counts']['absent']);
        $forbiddenKeys = ['latitude', 'longitude', 'geo_reference', 'geo_validation', 'password', 'token', 'token_hash', 'evidence_path', 'reason', 'resolution_note'];
        $foundKeys = [];
        $inspect = function (array $data) use (&$inspect, &$foundKeys): void {
            foreach ($data as $key => $value) {
                $foundKeys[] = $key;
                if (is_array($value)) {
                    $inspect($value);
                }
            }
        };
        $inspect($report->snapshot);
        $this->assertSame([], array_values(array_intersect($forbiddenKeys, $foundKeys)));
        $this->assertStringNotContainsString('PRIVADO', json_encode($report->snapshot));
        $counts = [Attendance::count(), ReportVersion::count(), AuditLog::count()];
        $this->artisan('attendance:process-closures')->assertSuccessful();
        $this->assertSame($counts, [Attendance::count(), ReportVersion::count(), AuditLog::count()]);
    }

    public function test_start_two_seconds_before_midnight_finalizes_without_waiting_twenty_four_hours_and_manual_finish_requires_reason_early(): void
    {
        [$period, $enrollment] = $this->scenario();
        $session = $this->makeSession($period, ['starts_at' => '2026-10-23T05:50:00Z', 'ends_at' => '2026-10-23T05:59:59Z']);
        $this->travelTo(now()->setDateTime(2026, 10, 23, 5, 59, 58));
        app(SessionWorkflow::class)->transition($period->offering->teacher, $session, 'start');
        $this->assertSame('2026-10-23 05:59:58', $session->fresh()->started_at->format('Y-m-d H:i:s'));
        $this->actingAs($period->offering->teacher)->post('/sessions/'.$session->id.'/state/finish')->assertSessionHasErrors('reason');
        $this->assertSame(SessionState::InProgress, $session->fresh()->state);
        $this->travelTo(now()->addSecond());
        $this->assertFalse(app(SessionWorkflow::class)->finishOverdue($session));
        $this->travelTo(now()->addSecond());
        $this->assertTrue(app(SessionWorkflow::class)->finishOverdue($session));
        $this->assertSame(1, $session->attendances()->count());
        $this->assertSame('absent', $session->attendances()->sole()->status->value);
        $early = $this->makeSession($period, ['state' => 'in_progress', 'started_at' => now(), 'starts_at' => now()->subMinute(), 'ends_at' => now()->addMinutes(10)]);
        $this->post('/sessions/'.$early->id.'/state/finish', ['reason' => 'Finalización anticipada autorizada'])->assertSessionHasNoErrors();
        $this->assertSame(SessionState::Completed, $early->fresh()->state);
    }

    public function test_reopening_never_reactivates_portals_or_readers_and_finished_cancelled_sessions_remain_unchanged(): void
    {
        [$period, $enrollment, $admin] = $this->scenario();
        $session = $this->makeSession($period, ['state' => 'in_progress', 'started_at' => '2026-10-22T16:10:00Z']);
        $portal = $session->portals()->create(['opens_at' => $session->starts_at, 'expires_at' => $session->ends_at, 'enabled_methods' => ['qr', 'link', 'nfc', 'manual'], 'token_hash' => hash('sha256', 'final-review')]);
        $reader = $portal->readerSessions()->create(['id' => (string) Str::uuid(), 'teacher_id' => $session->offering->teacher_id, 'label' => 'Revisión final', 'expires_at' => $session->ends_at, 'state' => 'active']);
        $cancelled = $this->makeSession($period, ['state' => 'cancelled', 'change_reason' => 'Cancelación documentada']);
        app(ClosureWorkflow::class)->closePeriod($admin, $period);
        $before = [$session->fresh()->getAttributes(), $cancelled->fresh()->getAttributes(), $portal->fresh()->getAttributes(), $reader->fresh()->getAttributes()];
        app(ClosureWorkflow::class)->reopenPeriod($admin, $period, 'Comprobar revisión sin reactivar recursos');
        $this->artisan('attendance:process-closures')->assertSuccessful();
        $this->assertFalse(app(SessionWorkflow::class)->finishOverdue($session));
        $this->assertFalse(app(SessionWorkflow::class)->finishOverdue($cancelled));
        $this->assertSame($before, [$session->fresh()->getAttributes(), $cancelled->fresh()->getAttributes(), $portal->fresh()->getAttributes(), $reader->fresh()->getAttributes()]);
        $this->assertNotNull($portal->fresh()->closed_at);
        $this->assertSame('closed', $reader->fresh()->state);
        $this->assertSame('open', $period->fresh()->state);
        $this->assertSame(1, $period->reports()->count());
    }

    public function test_closed_period_blocks_permission_resolution_session_operations_and_calendar_edits_via_direct_routes(): void
    {
        [$period, $enrollment, $admin] = $this->scenario();
        $period->offering->cycle->update(['ends_on' => '2026-12-31']);
        $open = AssessmentPeriod::factory()->create(['offering_id' => $period->offering_id, 'number' => 2, 'starts_on' => '2026-10-23', 'ends_on' => '2026-10-31']);
        $session = $this->makeSession($period, ['state' => 'completed']);
        $this->attendance($session, $enrollment, 'absent');
        $request = $this->permission($session, $enrollment, 'rejected');
        $token = str_repeat('A', 43);
        $portal = $session->portals()->create(['opens_at' => $session->starts_at, 'expires_at' => $session->ends_at, 'closed_at' => $session->ends_at, 'enabled_methods' => ['qr', 'link', 'nfc', 'manual'], 'token_hash' => hash('sha256', $token)]);
        $reader = $portal->readerSessions()->create(['id' => (string) Str::uuid(), 'teacher_id' => $session->offering->teacher_id, 'label' => 'Lector histórico', 'expires_at' => $session->ends_at, 'state' => 'closed']);
        NfcCredential::create(['user_id' => $enrollment->student_id, 'token_hash' => hash('sha256', 'SIAA:C1:'.$token), 'state' => 'active', 'activated_at' => now()->subDay()]);
        app(ClosureWorkflow::class)->closePeriod($admin, $period);
        $this->actingAs($admin);
        foreach (['start', 'finish', 'cancel'] as $operation) {
            $this->post('/sessions/'.$session->id.'/state/'.$operation, ['reason' => 'Intento de cambio cerrado'])->assertSessionHasErrors('session');
        }
        $this->post('/requests/'.$request->id.'/resolve', ['decision' => 'approved', 'note' => 'Intento de resolver fuera de revisión'])->assertSessionHasErrors('session');
        $this->post('/admin/requests/'.$request->id.'/reopen', ['reason' => 'Intento de revisar permiso cerrado'])->assertSessionHasErrors('session');
        $this->post('/admin/sessions/'.$session->id.'/review/reconcile', ['reason' => 'Intento de reparar fuera de revisión'])->assertSessionHasErrors('session');
        $this->put('/admin/offerings/'.$period->offering_id.'/periods/'.$period->id, ['number' => $period->number, 'starts_on' => '2026-10-02', 'ends_on' => '2026-10-22', 'reason' => 'Intento de modificar calendario cerrado'])->assertSessionHasErrors('number');
        $this->put('/offerings/'.$period->offering_id.'/sessions/'.$session->id, ['period_id' => $period->id, 'starts_at' => '2026-10-22T12:00', 'ends_at' => '2026-10-22T13:00', 'modality' => 'online', 'reason' => 'Intento de cambiar fecha cerrada'])->assertSessionHasErrors('period_id');
        $this->put('/offerings/'.$period->offering_id.'/sessions/'.$session->id, ['period_id' => $open->id, 'starts_at' => '2026-10-23T12:00', 'ends_at' => '2026-10-23T13:00', 'modality' => 'online', 'reason' => 'Intento de mover sesión fuera de cómputo cerrado'])->assertSessionHasErrors('period_id');
        $this->postJson('/sessions/'.$session->id.'/portals', ['methods' => ['manual'], 'minutes' => 5])->assertUnprocessable()->assertJsonValidationErrors('session');
        $this->postJson('/sessions/'.$session->id.'/manual-attendance', ['event_id' => (string) Str::uuid(), 'portal_id' => $portal->id, 'student_id' => $enrollment->student_id, 'reason' => 'Registro nuevo después del cierre'])->assertUnprocessable()->assertJsonValidationErrors('session');
        $this->withSession(['password_hash_web' => $period->offering->teacher->password])->actingAs($period->offering->teacher);
        $this->postJson('/teacher/nfc-readings', ['event_id' => (string) Str::uuid(), 'reader_session_id' => $reader->id, 'credential_token' => 'SIAA:C1:'.$token])->assertUnprocessable()->assertJsonValidationErrors('session');
        $this->withSession(['password_hash_web' => $enrollment->student->password])->actingAs($enrollment->student);
        foreach (['qr', 'link'] as $method) {
            $this->postJson('/check-in/'.$portal->id, ['event_id' => (string) Str::uuid(), 'portal_token' => $token, 'method' => $method])->assertUnprocessable()->assertJsonValidationErrors('session');
        }
        $this->assertSame('rejected', $request->fresh()->status);
        $this->assertSame('2026-10-01', $period->fresh()->starts_on->toDateString());
        $this->assertSame('0.00', $period->reports()->sole()->snapshot['rows'][0]['calculation']['percentage']);
        $this->assertDatabaseCount('attendance_events', 0);
    }

    public function test_all_administrative_routes_reject_anonymous_inactive_and_foreign_users_with_no_mutations(): void
    {
        [$period, $enrollment, $admin] = $this->scenario();
        $session = $this->makeSession($period);
        $request = $this->permission($session, $enrollment, 'rejected');
        $urls = ['/admin/periods/'.$period->id.'/close', '/admin/periods/'.$period->id.'/reopen', '/admin/cycles/'.$period->offering->cycle_id.'/close', '/admin/cycles/'.$period->offering->cycle_id.'/reopen', '/admin/sessions/'.$session->id.'/review/reconcile', '/admin/sessions/'.$session->id.'/review/recover-start', '/admin/requests/'.$request->id.'/reopen'];
        foreach ($urls as $url) {
            $this->post($url, ['reason' => 'Acceso sin autenticar'])->assertRedirect('/login');
        }
        foreach ([$enrollment->student, $period->offering->teacher, User::factory()->teacher()->create()] as $actor) {
            $this->withSession(['password_hash_web' => $actor->password])->actingAs($actor);
            foreach ($urls as $url) {
                $this->post($url, ['reason' => 'Acceso directo sin autorización'])->assertForbidden();
            }
        }
        $inactive = User::factory()->coordinator()->create(['active' => false]);
        foreach ($urls as $url) {
            $this->withSession(['password_hash_web' => $inactive->password])->actingAs($inactive)->postJson($url, ['reason' => 'Cuenta administrativa inactiva'])->assertForbidden();
        }
        $this->assertSame('open', $period->fresh()->state);
        $this->assertSame(SessionState::Scheduled, $session->fresh()->state);
        $this->assertDatabaseCount('report_versions', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_cycle_deadline_and_other_groups_block_closure_without_archiving_or_reopening_them(): void
    {
        [$period, , $admin] = $this->scenario();
        $cycle = $period->offering->cycle;
        $other = AssessmentPeriod::factory()->create(['offering_id' => Offering::factory()->create(['cycle_id' => $cycle->id])->id, 'starts_on' => '2026-10-01', 'ends_on' => '2026-10-22']);
        app(ClosureWorkflow::class)->closePeriod($admin, $period);
        app(ClosureWorkflow::class)->closeCycle($admin, $cycle);
        $this->assertSame('active', $cycle->fresh()->status);
        $this->assertStringContainsString('(#'.$other->offering_id.'), cómputo '.$other->number, implode(' ', $cycle->fresh()->closure_issues));
        app(ClosureWorkflow::class)->closePeriod($admin, $other);
        $this->travelTo(now()->setDateTime(2026, 10, 23, 5, 59, 59));
        $this->actingAs($admin)->post('/admin/cycles/'.$cycle->id.'/close')->assertSessionHasErrors('closure');
        $this->assertSame('active', $cycle->fresh()->status);
        $this->travelTo(now()->addSecond());
        app(ClosureWorkflow::class)->closeCycle($admin, $cycle);
        $this->assertSame('closed', $cycle->fresh()->status);
        $this->assertSame(2, $cycle->offerings()->count());
        app(ClosureWorkflow::class)->reopenCycle($admin, $cycle, 'Revisar únicamente el primer grupo');
        app(ClosureWorkflow::class)->reopenPeriod($admin, $period, 'Revisión del primer cómputo');
        $this->assertSame('closed', $other->fresh()->state);
        $this->assertSame(1, $other->reports()->count());
        $this->assertSame(2, $cycle->offerings()->count());
    }

    public function test_failure_saving_closed_state_rolls_back_the_already_created_snapshot_and_generated_results(): void
    {
        [$period, , $admin] = $this->scenario();
        $session = $this->makeSession($period, ['state' => 'in_progress', 'started_at' => '2026-10-22T16:10:00Z']);
        $event = 'eloquent.updating: '.AssessmentPeriod::class;
        Event::listen($event, function (AssessmentPeriod $record): void {
            if ($record->state === 'closed') {
                $this->assertDatabaseCount('report_versions', 1);
                $this->assertDatabaseCount('attendances', 1);
                throw new \RuntimeException('Fallo de guardado posterior al snapshot');
            }
        });
        try {
            app(ClosureWorkflow::class)->closePeriod($admin, $period);
            $this->fail('Debió fallar el guardado del cierre.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Fallo de guardado posterior al snapshot', $exception->getMessage());
        } finally {
            Event::forget($event);
        }
        $this->assertSame('open', $period->fresh()->state);
        $this->assertNull($period->fresh()->current_report_id);
        $this->assertSame(SessionState::InProgress, $session->fresh()->state);
        $this->assertDatabaseCount('report_versions', 0);
        $this->assertDatabaseCount('attendances', 0);
        $this->assertDatabaseCount('audit_logs', 0);
        app(ClosureWorkflow::class)->closePeriod($admin, $period);
        $this->assertSame('closed', $period->fresh()->state);
        $this->assertSame(1, $period->reports()->count());
        $this->assertSame(1, $session->attendances()->count());
    }

    public function test_reopening_both_resources_rejects_blank_reasons_and_foreign_current_and_historical_reads(): void
    {
        [$period, , $admin] = $this->scenario();
        $workflow = app(ClosureWorkflow::class);
        $workflow->closePeriod($admin, $period);
        $workflow->closeCycle($admin, $period->offering->cycle);
        $this->actingAs($admin)->post('/admin/cycles/'.$period->offering->cycle_id.'/reopen', ['reason' => '     '])->assertSessionHasErrors('reason');
        $this->assertSame('closed', $period->offering->cycle->fresh()->status);
        $workflow->reopenCycle($admin, $period->offering->cycle, 'Revisión autorizada del ciclo');
        $this->post('/admin/periods/'.$period->id.'/reopen', ['reason' => '     '])->assertSessionHasErrors('reason');
        $this->assertSame('closed', $period->fresh()->state);
        foreach ([User::factory()->create(), User::factory()->teacher()->create()] as $foreign) {
            $this->withSession(['password_hash_web' => $foreign->password])->actingAs($foreign);
            foreach (['', '&report_version=1'] as $suffix) {
                $this->get('/offerings/'.$period->offering_id.'?period_id='.$period->id.$suffix)->assertForbidden();
            }
        }
        $this->assertDatabaseCount('report_versions', 1);
    }
}
