<?php

namespace App\Actions\Attendance;

use App\Actions\Academic\AuditRecorder;
use App\Enums\Role;
use App\Enums\SessionState;
use App\Models\AbsenceRequest;
use App\Models\AuditLog;
use App\Models\ClassSession;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Exceptional, explicit administrative repairs; never registers a retrospective presence. */
final class ReviewSession
{
    public function __construct(private AcademicSessionLock $locks, private SessionWorkflow $workflow, private AuditRecorder $audit) {}

    public function recoverableStart(ClassSession $session): ?CarbonImmutable
    {
        $events = AuditLog::where('entity_type', 'ClassSession')->where('entity_id', (string) $session->id)->where('action', 'session.start')->get();
        if ($events->count() !== 1) {
            return null;
        }
        $event = $events->first();
        // Legacy created_at used PostgreSQL transaction_timestamp(), not the actual transition time.
        $stored = $event->after['started_at'] ?? null;
        if (! is_string($stored) || ! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+-]\d{2}:\d{2})$/D', $stored)) {
            return null;
        }
        try {
            $time = CarbonImmutable::parse($stored, 'UTC');
        } catch (\Throwable) {
            return null;
        }
        if (($event->before['state'] ?? null) !== 'scheduled' || ($event->after['state'] ?? null) !== 'in_progress'
            || $time < $session->starts_at || $time >= $session->ends_at || $time > now()) {
            return null;
        }

        return $time;
    }

    public function execute(User $actor, ClassSession $session, string $operation, string $reason): void
    {
        $this->authorize($actor, $reason);
        DB::transaction(function () use ($actor, $session, $operation, $reason): void {
            $session = $this->locks->get($session);
            $before = $this->audit->snapshot($session);
            if ($operation === 'recover-start') {
                $time = $this->recoverableStart($session);
                if ($session->state !== SessionState::InProgress || $session->started_at !== null || $time === null) {
                    $this->fail('No existe una única transición de inicio fiable en la auditoría. Finaliza manualmente la sesión en curso.');
                }
                $session->update(['started_at' => $time]);
                $this->audit->record($actor, $session, 'session.start_recovered', $before, $reason);

                return;
            }
            if ($operation !== 'reconcile' || ! in_array($session->state, [SessionState::Scheduled, SessionState::Completed], true)
                || $session->starts_at > now() || $session->ends_at > now()) {
                $this->fail('La revisión requiere una sesión vencida, programada con evidencia o finalizada.');
            }
            if ($session->state === SessionState::Scheduled) {
                if (! app(ClosureWorkflow::class)->hasStartEvidence($session)) {
                    $this->fail('No hay evidencia de inicio. Resuelve sus permisos; no se inventa una sesión impartida.');
                }
                $session->state = SessionState::InProgress;
            }
            // Only finalization creates missing absent/justified rows. Existing presences are immutable here.
            $this->workflow->finalize($actor, $session);
            $approved = $session->absenceRequests()->where('status', 'approved')->pluck('enrollment_id')->all();
            foreach ($session->attendances()->whereIn('status', ['absent', 'justified'])->lockForUpdate()->get() as $attendance) {
                $status = in_array($attendance->enrollment_id, $approved) ? 'justified' : 'absent';
                if ($attendance->status->value !== $status) {
                    $previous = $this->audit->snapshot($attendance);
                    $attendance->update(['status' => $status, 'recorded_by' => $actor->id, 'recorded_at' => now()]);
                    $this->audit->record($actor, $attendance, 'attendance.reconciled', $previous, $reason);
                }
            }
            $session->save();
            $this->workflow->closePortals($session);
            $this->audit->record($actor, $session, 'session.reconciled', $before, $reason);
        });
    }

    public function reopenDecision(User $actor, AbsenceRequest $request, string $reason): void
    {
        $this->authorize($actor, $reason);
        DB::transaction(function () use ($actor, $request, $reason): void {
            $this->locks->get($request->session);
            $request = AbsenceRequest::lockForUpdate()->findOrFail($request->id);
            if ($request->status === 'pending') {
                $this->fail('El permiso ya está pendiente.');
            }
            $before = $this->audit->snapshot($request);
            $request->update(['status' => 'pending', 'resolved_by' => null, 'resolved_at' => null, 'resolution_note' => null]);
            $this->audit->record($actor, $request, 'request.reopened', $before, $reason);
        });
    }

    private function authorize(User $actor, string $reason): void
    {
        $actor = User::findOrFail($actor->id);
        abort_unless($actor->active && $actor->role === Role::Coordinator, 403);
        if (mb_strlen(trim($reason)) < 5 || mb_strlen($reason) > 1000) {
            $this->fail('Indica un motivo de entre 5 y 1000 caracteres.');
        }
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['review' => $message]);
    }
}
