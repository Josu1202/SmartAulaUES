<?php

namespace App\Actions\Attendance;

use App\Actions\Academic\AcademicRegistry;
use App\Actions\Academic\AuditRecorder;
use App\Enums\SessionState;
use App\Models\Attendance;
use App\Models\ClassSession;
use App\Models\Offering;
use App\Models\User;
use App\Notifications\AcademicNotice;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SessionWorkflow
{
    public function __construct(private AcademicRegistry $registry, private AcademicSessionLock $locks, private AuditRecorder $audit) {}

    public function save(User $actor, Offering $offering, array $data, ?ClassSession $session = null): ClassSession
    {
        return DB::transaction(function () use ($actor, $offering, $data, $session): ClassSession {
            $offering = $this->registry->lockOffering($offering);
            Gate::forUser($actor)->authorize('manage', $offering);
            $period = $offering->periods()->lockForUpdate()->findOrFail($data['period_id']);
            if ($period->state !== 'open') {
                $this->fail('period_id', 'El cómputo está cerrado o en cierre.');
            }
            $record = $session ? $offering->sessions()->lockForUpdate()->findOrFail($session->id) : new ClassSession(['offering_id' => $offering->id, 'state' => SessionState::Scheduled]);
            if ($record->exists && $record->period_id !== $period->id && $offering->periods()->whereKey($record->period_id)->where('state', '!=', 'open')->exists()) {
                $this->fail('period_id', 'El cómputo original está cerrado o en cierre.');
            }
            if ($record->state !== SessionState::Scheduled || ($record->exists && ($record->attendances()->exists() || $record->absenceRequests()->exists()))) {
                $this->fail('starts_at', 'Solo se reprograman sesiones programadas sin asistencias ni permisos.');
            }
            $start = CarbonImmutable::createFromFormat('Y-m-d\TH:i', $data['starts_at'], config('smartaulaues.display_timezone'))->startOfMinute();
            $end = CarbonImmutable::createFromFormat('Y-m-d\TH:i', $data['ends_at'], config('smartaulaues.display_timezone'))->startOfMinute();
            if ($end <= $start || $start->toDateString() < $period->starts_on->toDateString() || $end->toDateString() > $period->ends_on->toDateString()) {
                $this->fail('ends_at', 'El horario debe terminar después del inicio y quedar dentro de las fechas del cómputo.');
            }
            User::lockForUpdate()->findOrFail($offering->teacher_id);
            if (ClassSession::whereHas('offering', fn ($q) => $q->where('teacher_id', $offering->teacher_id))->whereNotIn('state', ['cancelled', 'unstarted'])->where('starts_at', '<', $end->utc())->where('ends_at', '>', $start->utc())->when($record->exists, fn ($q) => $q->whereKeyNot($record->id))->exists()) {
                $this->fail('starts_at', 'El docente ya tiene otra sesión en este horario.');
            }
            $before = $record->exists ? $this->audit->snapshot($record) : null;
            if ($record->exists && blank($data['reason'] ?? null)) {
                $this->fail('reason', 'Indica el motivo de la modificación.');
            }
            if ($data['modality'] === 'event' && ! in_array($data['event_attendance_mode'] ?? null, ['in_person', 'online'], true)) {
                $this->fail('event_attendance_mode', 'Indica si el evento es presencial o en línea.');
            }
            $record->fill(['period_id' => $period->id, 'starts_at' => $start->utc(), 'ends_at' => $end->utc(), 'modality' => $data['modality'], 'event_attendance_mode' => $data['modality'] === 'event' ? $data['event_attendance_mode'] : null, 'change_reason' => $data['reason'] ?? null])->save();
            $this->audit->record($actor, $record, $before ? 'session.updated' : 'session.created', $before, $data['reason'] ?? null);
            if ($before) {
                $this->notifyGroup($record, 'Sesión reprogramada', $data['reason']);
            }

            return $record;
        });
    }

    public function transition(User $actor, ClassSession $session, string $operation, ?string $reason = null): ClassSession
    {
        return DB::transaction(function () use ($actor, $session, $operation, $reason): ClassSession {
            $record = $this->locks->get($session);
            Gate::forUser($actor)->authorize('update', $record);
            $before = $this->audit->snapshot($record);
            if ($operation === 'start') {
                if ($record->state !== SessionState::Scheduled || $record->starts_at > now() || $record->ends_at <= now() || $record->offering->cycle->status !== 'active') {
                    $this->fail('session', 'La sesión debe estar programada, dentro de su horario y en un ciclo activo.');
                }
                $record->state = SessionState::InProgress;
                $record->started_at = now();
            } elseif ($operation === 'cancel') {
                if (! in_array($record->state, [SessionState::Scheduled, SessionState::InProgress]) || $record->attendances()->exists() || $record->absenceRequests()->exists()) {
                    $this->fail('session', 'No se cancela una sesión finalizada o que ya tiene asistencias/permisos. Conserva su historial.');
                }
                if (blank($reason)) {
                    $this->fail('reason', 'La cancelación requiere un motivo.');
                }
                $record->state = SessionState::Cancelled;
            } elseif ($operation === 'finish') {
                if ($record->state !== SessionState::InProgress) {
                    $this->fail('session', 'Solo se finalizan sesiones en curso.');
                }
                if ($record->ends_at > now() && blank($reason)) {
                    $this->fail('reason', 'Indica el motivo para finalizar antes de la hora programada.');
                }
                $this->finalize($actor, $record);
            } else {
                abort(404);
            }
            $record->change_reason = $reason ?? $record->change_reason;
            $record->save();
            if ($operation !== 'start') {
                $this->closePortals($record);
            }
            $this->audit->record($actor, $record, 'session.'.$operation, $before, $reason);
            if ($operation === 'cancel') {
                $this->notifyGroup($record, 'Sesión cancelada', $reason);
            }

            return $record;
        });
    }

    /** System entry: same finalization, with independent eligibility checks under academic locks. */
    public function finishOverdue(ClassSession $session): bool
    {
        return DB::transaction(function () use ($session): bool {
            $record = $this->locks->get($session, allowInactive: true);
            if ($record->state !== SessionState::InProgress || $record->started_at === null
                || $record->ends_at > now()) {
                return false;
            }
            $before = $this->audit->snapshot($record);
            $this->finalize(null, $record);
            $record->save();
            $this->closePortals($record);
            $this->audit->record(null, $record, 'session.auto_finished', $before, 'Finalización automática al alcanzar el fin programado (UTC).');

            return true;
        });
    }

    /** Internal finalizer: caller must hold cycle → offering → period → session locks. */
    public function finalize(?User $actor, ClassSession $record): void
    {
        if (DB::transactionLevel() === 0 || ! in_array($record->state, [SessionState::InProgress, SessionState::Completed], true)) {
            throw new \LogicException('La finalización requiere una transacción y una sesión iniciada.');
        }
        $date = $record->starts_at->timezone('America/El_Salvador')->toDateString();
        $approved = $record->absenceRequests()->where('status', 'approved')->pluck('enrollment_id')->all();
        foreach ($record->offering->enrollments()->where('active_from', '<=', $date)->where(fn ($q) => $q->whereNull('active_to')->orWhere('active_to', '>=', $date))->get() as $enrollment) {
            $attendance = Attendance::firstOrCreate(['session_id' => $record->id, 'enrollment_id' => $enrollment->id], ['offering_id' => $record->offering_id, 'status' => in_array($enrollment->id, $approved) ? 'justified' : 'absent', 'method' => null, 'recorded_by' => $actor?->id, 'recorded_at' => now()]);
            if ($attendance->wasRecentlyCreated) {
                $this->audit->record($actor, $attendance, 'attendance.finalized', reason: 'Resultado generado al finalizar la sesión.');
            }
        }
        $record->state = SessionState::Completed;
        $record->finalized_at ??= now();
    }

    public function closePortals(ClassSession $session): void
    {
        foreach ($session->portals()->get() as $portal) {
            if ($portal->closed_at === null) {
                $portal->update(['closed_at' => now()]);
            }
            $portal->readerSessions()->where('state', 'active')->update(['state' => 'closed']);
        }
    }

    private function notifyGroup(ClassSession $session, string $title, string $reason): void
    {
        $date = $session->starts_at->timezone(config('smartaulaues.display_timezone'))->toDateString();
        foreach ($session->offering->enrollments()->with('student')->where('active_from', '<=', $date)
            ->where(fn ($q) => $q->whereNull('active_to')->orWhere('active_to', '>=', $date))
            ->whereHas('student', fn ($q) => $q->where('active', true))->get() as $enrollment) {
            $enrollment->student->notify(new AcademicNotice($title, $session->offering->subject->name.': '.$reason, route('sessions.show', $session, absolute: false)));
        }
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
