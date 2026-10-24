<?php

namespace App\Actions\Attendance;

use App\Actions\Academic\AcademicRegistry;
use App\Actions\Academic\AuditRecorder;
use App\Enums\Role;
use App\Enums\SessionState;
use App\Models\AbsenceRequest;
use App\Models\AcademicCycle;
use App\Models\AssessmentPeriod;
use App\Models\AuditLog;
use App\Models\ClassSession;
use App\Models\Offering;
use App\Models\ReportVersion;
use App\Models\User;
use App\Queries\AttendanceSummary;
use App\Services\Attendance\AttendancePolicy;
use App\Services\Attendance\ClosureSnapshot;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ClosureWorkflow
{
    public function __construct(private SessionWorkflow $sessions, private AuditRecorder $audit, private AttendanceSummary $summary, private ClosureSnapshot $snapshots) {}

    public function closePeriod(User $actor, AssessmentPeriod $period): AssessmentPeriod
    {
        $this->administrator($actor);

        return $this->processPeriod($period, $actor);
    }

    public function autoClosePeriod(AssessmentPeriod $period): AssessmentPeriod
    {
        return $this->processPeriod($period, null);
    }

    private function processPeriod(AssessmentPeriod $period, ?User $actor): AssessmentPeriod
    {
        return DB::transaction(function () use ($period, $actor): AssessmentPeriod {
            $period = $this->lockPeriod($period);
            if ($period->state === 'closed') {
                if (! $this->snapshots->valid($period->reports()->find($period->current_report_id), $period)) {
                    $this->fail('El cómputo figura cerrado sin snapshot válido. Coordinación debe reabrirlo y revisar sus datos; no se reconstruye un cierre histórico.');
                }

                return $period; // Repeated calls never create another version.
            }
            if ($period->offering->cycle->status === 'closed') {
                $this->fail('Reabre el ciclo antes de intervenir en sus cómputos.');
            }
            if ($actor === null && $period->reopened_at !== null) {
                return $period;
            }
            if ($period->ends_on->toDateString() >= now('America/El_Salvador')->toDateString()) {
                if ($actor !== null) {
                    $this->fail('El cómputo permanece abierto durante toda su fecha final local.');
                }

                return $period;
            }
            // Each period is independent. All work below commits together, or rolls back together.
            foreach ($period->sessions()->where('state', 'in_progress')->orderBy('id')->get() as $session) {
                $this->sessions->finishOverdue($session);
            }
            foreach ($period->sessions()->where('state', 'scheduled')->orderBy('id')->lockForUpdate()->get() as $session) {
                if (! $this->hasStartEvidence($session) && ! $session->absenceRequests()->whereIn('status', ['pending', 'approved'])->exists()) {
                    $before = $this->audit->snapshot($session);
                    $session->update(['state' => SessionState::Unstarted]);
                    $this->sessions->closePortals($session);
                    $this->audit->record($actor, $session, 'session.unstarted', $before, 'Clasificada al procesar el cierre del cómputo; no genera resultados.');
                }
            }
            $rows = $this->summary->captureForClosure($period);
            $issues = $this->periodIssues($period, $rows);
            if ($issues !== []) {
                $this->rememberIssues($period, $issues, $actor, 'period.closure_blocked');

                return $period;
            }
            $before = $this->audit->snapshot($period);
            $version = (int) $period->reports()->max('version') + 1;
            foreach ($rows as &$row) {
                $row['calculation']['period_state'] = 'closed';
            }
            unset($row);
            $snapshot = [
                'schema_version' => 1, 'version' => $version, 'offering_id' => $period->offering_id,
                'period' => ['id' => $period->id, 'number' => $period->number, 'starts_on' => $period->starts_on->toDateString(), 'ends_on' => $period->ends_on->toDateString()],
                'policy_version' => $period->policy_version, 'policy' => AttendancePolicy::find($period->policy_version),
                'generated_at' => now('UTC')->toISOString(), 'origin' => $actor ? 'manual' : 'automatic', 'actor_id' => $actor?->id,
                'rows' => $rows,
                'sessions' => $period->sessions()->orderBy('id')->get(['id', 'starts_at', 'ends_at', 'started_at', 'finalized_at', 'state'])->toArray(),
            ];
            $report = ReportVersion::create([
                'period_id' => $period->id, 'version' => $version, 'snapshot' => $snapshot, 'policy_version' => $period->policy_version,
                'generated_by' => $actor?->id, 'generated_at' => now(), 'origin' => $snapshot['origin'], 'snapshot_hash' => ClosureSnapshot::hash($snapshot),
            ]);
            if (! $this->snapshots->valid($report, $period)) {
                throw new \RuntimeException('No se pudo validar el snapshot generado.');
            }
            $period->update(['state' => 'closed', 'closed_at' => now(), 'current_report_id' => $report->id, 'closure_issues' => [], 'closure_checked_at' => now()]);
            $this->audit->record($actor, $period, 'period.closed', $before, 'Cierre '.$snapshot['origin'].'; versión '.$version.'.');

            return $period;
        });
    }

    /** Conservative evidence test: preventive resolved requests alone do not prove a real start. */
    public function hasStartEvidence(ClassSession $session): bool
    {
        return $session->started_at !== null || $session->finalized_at !== null || $session->attendances()->exists()
            || $session->portals()->exists()
            || AuditLog::where('entity_type', 'ClassSession')->where('entity_id', (string) $session->id)->whereIn('action', ['session.start', 'session.finish', 'session.auto_finished', 'session.start_recovered', 'session.reconciled'])->exists();
    }

    /** Shared integrity checks. A04 does not require closing clean scheduled sessions. */
    public function periodIssues(AssessmentPeriod $period, array $rows, bool $forDeactivation = false): array
    {
        $issues = [];
        if (AttendancePolicy::find($period->policy_version) === null) {
            $issues[] = 'Reglamento no disponible para cálculo.';
        }
        foreach ($period->sessions()->with(['attendances.enrollment', 'absenceRequests.enrollment'])->orderBy('id')->get() as $session) {
            $date = $session->starts_at->timezone('America/El_Salvador')->toDateString();
            $end = $session->ends_at->timezone('America/El_Salvador')->toDateString();
            if ($date < $period->starts_on->toDateString() || $end > $period->ends_on->toDateString()) {
                $issues[] = 'Sesión #'.$session->id.': horario fuera del cómputo.';
            }
            if ($session->state === SessionState::Scheduled && (! $forDeactivation || $this->hasStartEvidence($session)
                || ($session->ends_at <= now() && $session->absenceRequests->contains('status', 'approved')))) {
                $issues[] = 'Sesión #'.$session->id.': programada con datos contradictorios; requiere revisión administrativa.';
            }
            if ($session->state === SessionState::InProgress) {
                $issues[] = 'Sesión #'.$session->id.': '.($session->started_at === null ? 'sin inicio real fiable; finalízala manualmente o recupera su auditoría.' : ($session->ends_at > now() ? 'horario válido aún vigente, posiblemente cruza medianoche; no se trunca automáticamente.' : 'todavía no terminó el día local de su inicio real.'));
            }
            if (in_array($session->state, [SessionState::Cancelled, SessionState::Unstarted], true)
                && ($session->attendances->isNotEmpty() || $session->absenceRequests->contains('status', 'approved') || $session->absenceRequests->contains('status', 'pending') || ($session->state === SessionState::Unstarted && $this->hasStartEvidence($session)))) {
                $issues[] = 'Sesión #'.$session->id.': estado incompatible con sus resultados, permisos o evidencia de inicio.';
            }
            foreach ($session->attendances->concat($session->absenceRequests) as $resource) {
                $enrollment = $resource->enrollment;
                if ($date < $enrollment->active_from->toDateString() || ($enrollment->active_to && $date > $enrollment->active_to->toDateString())) {
                    $issues[] = 'Sesión #'.$session->id.', inscripción #'.$enrollment->id.': datos fuera de su vigencia.';
                }
            }
            if ($session->absenceRequests->contains('status', 'pending')) {
                $issues[] = 'Sesión #'.$session->id.': permisos pendientes de resolución.';
            }
        }
        foreach ($rows as $row) {
            foreach ($row['calculation']['incidents'] as $incident) {
                $issues[] = 'Inscripción #'.$row['enrollment_id'].', sesión #'.$incident['session_id'].': '.match ($incident['code']) {
                    'missing_result' => 'falta el resultado de una sesión finalizada; requiere revisar su finalización.',
                    'justified_without_approval' => 'justificación sin permiso aprobado.',
                    'approved_request_with_absent_result' => 'ausencia incompatible con un permiso aprobado.',
                    'completed_before_start' => 'sesión finalizada antes de su inicio programado.',
                    default => 'resultado de asistencia inválido.',
                };
            }
        }

        return array_values(array_unique($issues));
    }

    public function reopenPeriod(User $actor, AssessmentPeriod $period, string $reason): AssessmentPeriod
    {
        $this->administrator($actor, $reason);

        return DB::transaction(function () use ($actor, $period, $reason): AssessmentPeriod {
            $period = $this->lockPeriod($period);
            app(AcademicRegistry::class)->requireActiveOffering($period->offering);
            if ($period->offering->cycle->status === 'closed') {
                $this->fail('Reabre primero el ciclo.');
            }
            if ($period->state !== 'closed') {
                $this->fail('Solo se reabre un cómputo cerrado.');
            }
            $before = $this->audit->snapshot($period);
            $period->update(['state' => 'open', 'closed_at' => null, 'reopened_at' => now(), 'closure_issues' => []]);
            $this->audit->record($actor, $period, 'period.reopened', $before, $reason);

            return $period;
        });
    }

    public function closeCycle(User $actor, AcademicCycle $cycle): AcademicCycle
    {
        $this->administrator($actor);

        return $this->processCycle($cycle, $actor);
    }

    public function autoCloseCycle(AcademicCycle $cycle): AcademicCycle
    {
        return $this->processCycle($cycle, null);
    }

    private function processCycle(AcademicCycle $cycle, ?User $actor): AcademicCycle
    {
        return DB::transaction(function () use ($cycle, $actor): AcademicCycle {
            $cycle = AcademicCycle::lockForUpdate()->findOrFail($cycle->id);
            if ($cycle->status === 'closed' || ($actor === null && $cycle->reopened_at !== null)) {
                return $cycle;
            }
            if ($cycle->ends_on->toDateString() >= now('America/El_Salvador')->toDateString()) {
                if ($actor !== null) {
                    $this->fail('El ciclo permanece abierto durante toda su fecha final local.');
                }

                return $cycle;
            }
            $issues = [];
            foreach ($cycle->offerings()->orderBy('id')->lockForUpdate()->get() as $offering) {
                $periods = $offering->periods()->orderBy('id')->lockForUpdate()->get();
                if ($periods->isEmpty()) {
                    $issues[] = 'Grupo '.$offering->group_code.' (#'.$offering->id.'): sin cómputos definidos.';
                }
                foreach ($periods as $period) {
                    if ($period->state !== 'closed' || ! $this->snapshots->valid($period->reports()->find($period->current_report_id), $period)) {
                        $issues[] = 'Grupo '.$offering->group_code.' (#'.$offering->id.'), cómputo '.$period->number.': sin cierre válido.';
                    }
                }
                if ($offering->sessions()->whereNotIn('state', ['completed', 'cancelled', 'unstarted'])->exists()
                    || AbsenceRequest::where('offering_id', $offering->id)->where('status', 'pending')->exists()) {
                    $issues[] = 'Grupo '.$offering->group_code.' (#'.$offering->id.'): sesiones o permisos pendientes.';
                }
            }
            if ($issues !== []) {
                $this->rememberIssues($cycle, $issues, $actor, 'cycle.closure_blocked');

                return $cycle;
            }
            $before = $this->audit->snapshot($cycle);
            $cycle->update(['status' => 'closed', 'closed_at' => now(), 'closure_issues' => [], 'closure_checked_at' => now()]);
            $this->audit->record($actor, $cycle, 'cycle.closed', $before, 'Cierre '.($actor ? 'manual' : 'automático').'.');

            return $cycle;
        });
    }

    public function reopenCycle(User $actor, AcademicCycle $cycle, string $reason): AcademicCycle
    {
        $this->administrator($actor, $reason);

        return DB::transaction(function () use ($actor, $cycle, $reason): AcademicCycle {
            $cycle = AcademicCycle::lockForUpdate()->findOrFail($cycle->id);
            if ($cycle->status !== 'closed') {
                $this->fail('Solo se reabre un ciclo cerrado.');
            }
            $before = $this->audit->snapshot($cycle);
            $cycle->update(['status' => 'active', 'closed_at' => null, 'reopened_at' => now(), 'closure_issues' => []]);
            $this->audit->record($actor, $cycle, 'cycle.reopened', $before, $reason);

            return $cycle;
        });
    }

    private function lockPeriod(AssessmentPeriod $period): AssessmentPeriod
    {
        $offering = Offering::findOrFail($period->offering_id);
        $cycle = AcademicCycle::lockForUpdate()->findOrFail($offering->cycle_id);
        $offering = Offering::lockForUpdate()->findOrFail($offering->id);
        $record = AssessmentPeriod::lockForUpdate()->findOrFail($period->id);
        if ($record->offering_id !== $offering->id || $offering->cycle_id !== $cycle->id) {
            $this->fail('El recurso cambió. Actualiza la página.');
        }

        return $record->setRelation('offering', $offering->setRelation('cycle', $cycle));
    }

    private function rememberIssues(Model $record, array $issues, ?User $actor, string $action): void
    {
        if ($record->closure_issues !== $issues) {
            $before = $this->audit->snapshot($record);
            $record->update(['closure_issues' => $issues, 'closure_checked_at' => now()]);
            $this->audit->record($actor, $record, $action, $before, 'Cierre bloqueado; resolver las causas y volver a intentar.');
        }
    }

    private function administrator(User $actor, ?string $reason = null): void
    {
        $actor = User::findOrFail($actor->id);
        abort_unless($actor->active && $actor->role === Role::Coordinator, 403);
        if ($reason !== null && (mb_strlen(trim($reason)) < 5 || mb_strlen($reason) > 1000)) {
            $this->fail('Indica un motivo de entre 5 y 1000 caracteres.');
        }
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['closure' => $message]);
    }
}
