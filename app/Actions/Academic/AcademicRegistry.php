<?php

namespace App\Actions\Academic;

use App\Enums\Role;
use App\Models\AcademicCycle;
use App\Models\AssessmentPeriod;
use App\Models\CycleFormat;
use App\Models\Enrollment;
use App\Models\Offering;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AcademicRegistry
{
    public function __construct(private AuditRecorder $audit) {}

    public function saveCycle(User $actor, array $data, ?AcademicCycle $cycle = null): AcademicCycle
    {
        Gate::forUser($actor)->authorize($cycle ? 'update' : 'create', $cycle ?? AcademicCycle::class);

        return DB::transaction(function () use ($actor, $data, $cycle): AcademicCycle {
            $record = $cycle ? AcademicCycle::lockForUpdate()->findOrFail($cycle->id) : new AcademicCycle;
            if ($data['status'] === 'closed' || ($record->exists && $record->reopened_at !== null && $data['status'] !== $record->status)) {
                $this->fail('status', 'Usa las acciones específicas de cierre y reapertura del ciclo.');
            }
            $before = $record->exists ? $this->audit->snapshot($record) : null;
            if ($record->exists) {
                $this->openCycle($record);
                foreach ($record->formats()->with('periods')->get() as $format) {
                    foreach ($format->periods as $p) {
                        $this->withinDates($data['starts_on'], $data['ends_on'], $p->starts_on->toDateString(), $p->ends_on->toDateString(), 'starts_on');
                    }
                }
                foreach ($record->offerings()->with(['periods', 'enrollments', 'sessions'])->get() as $offering) {
                    foreach ($offering->periods as $period) {
                        $this->withinDates($data['starts_on'], $data['ends_on'], $period->starts_on->toDateString(), $period->ends_on->toDateString(), 'starts_on');
                    }
                    foreach ($offering->enrollments as $enrollment) {
                        $this->withinDates($data['starts_on'], $data['ends_on'], $enrollment->active_from->toDateString(), $enrollment->active_to?->toDateString(), 'starts_on');
                    }
                    foreach ($offering->sessions as $session) {
                        $this->withinDates($data['starts_on'], $data['ends_on'], $this->sessionDate($session), $this->sessionDate($session, 'ends_at'), 'starts_on');
                    }
                }
                $this->reasonForChanges($record, $data, ['starts_on', 'ends_on', 'status']);
            }
            $record->fill(collect($data)->only(['code', 'starts_on', 'ends_on', 'status'])->all())->save();
            $this->audit->record($actor, $record, $before ? 'cycle.updated' : 'cycle.created', $before, $data['reason'] ?? null);

            return $record;
        });
    }

    public function saveSubject(User $actor, array $data, ?Subject $subject = null): Subject
    {
        Gate::forUser($actor)->authorize($subject ? 'update' : 'create', $subject ?? Subject::class);

        return DB::transaction(function () use ($actor, $data, $subject): Subject {
            $record = $subject ? Subject::lockForUpdate()->findOrFail($subject->id) : new Subject;
            $before = $record->exists ? $this->audit->snapshot($record) : null;
            if ($record->exists) {
                $this->reasonForChanges($record, $data, ['active']);
            }
            $record->fill(collect($data)->only(['code', 'name', 'active'])->all())->save();
            $this->audit->record($actor, $record, $before ? 'subject.updated' : 'subject.created', $before, $data['reason'] ?? null);

            return $record;
        });
    }

    public function saveOffering(User $actor, array $data, ?Offering $offering = null): Offering
    {
        Gate::forUser($actor)->authorize($offering ? 'update' : 'create', $offering ?? Offering::class);

        return DB::transaction(function () use ($actor, $data, $offering): Offering {
            // Parent-first locking is shared by period/enrollment writes and cycle edits.
            $cycleIds = array_unique(array_filter([$offering?->cycle_id, (int) $data['cycle_id']]));
            $cycles = AcademicCycle::whereIn('id', $cycleIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            foreach ($cycles as $cycle) {
                $this->openCycle($cycle);
            }
            $record = $offering ? Offering::lockForUpdate()->findOrFail($offering->id) : new Offering;
            if ($record->exists && ! $cycles->has($record->cycle_id)) {
                $this->fail('cycle_id', 'El grupo cambió de ciclo. Actualiza la página e inténtalo nuevamente.');
            }
            $before = $record->exists ? $this->audit->snapshot($record) : null;
            $hasHistory = $record->exists && ($record->enrollments()->exists() || $record->periods()->exists() || $record->sessions()->exists());
            if ($hasHistory && ((int) $data['cycle_id'] !== $record->cycle_id || (int) $data['subject_id'] !== $record->subject_id || $data['group_code'] !== $record->group_code)) {
                $this->fail('group_code', 'El grupo tiene registros académicos. Conserva su ciclo, materia y código.');
            }
            $teacher = User::lockForUpdate()->findOrFail($data['teacher_id']);
            if (! $teacher->active || $teacher->role !== Role::Teacher) {
                $this->fail('teacher_id', 'Selecciona un docente activo.');
            }
            $subject = Subject::lockForUpdate()->findOrFail($data['subject_id']);
            if (! $subject->active && (! $record->exists || $record->subject_id !== $subject->id)) {
                $this->fail('subject_id', 'Selecciona una materia activa.');
            }
            if ($record->exists && (int) $data['capacity'] < $this->reservedSeats($record)) {
                $this->fail('capacity', 'El cupo no puede ser menor que las plazas reservadas por las inscripciones.');
            }
            if ($record->exists) {
                $this->reasonForChanges($record, $data, ['teacher_id', 'capacity']);
            }
            $record->fill(collect($data)->only(['cycle_id', 'subject_id', 'teacher_id', 'group_code', 'capacity'])->all())->save();
            if (! $before && ! empty($data['cycle_format_id'])) {
                $format = CycleFormat::lockForUpdate()->with('periods')->findOrFail($data['cycle_format_id']);
                if (! $format->active || $format->cycle_id !== $record->cycle_id || $format->periods->isEmpty()) {
                    $this->fail('cycle_format_id', 'Selecciona un formato activo del mismo ciclo, con cómputos definidos.');
                }
                $record->update(['cycle_format_id' => $format->id]);
                foreach ($format->periods as $p) {
                    $period = $record->periods()->create(['number' => $p->number, 'starts_on' => $p->starts_on, 'ends_on' => $p->ends_on, 'format_period_id' => $p->id, 'state' => 'open', 'policy_version' => config('attendance.policy_version')]);
                    $this->audit->record($actor, $period, 'period.created', reason: 'Calendario heredado del formato '.$format->name);
                }
            }
            $this->audit->record($actor, $record, $before ? 'offering.updated' : 'offering.created', $before, $data['reason'] ?? null);

            return $record;
        });
    }

    public function savePeriod(User $actor, Offering $offering, array $data, ?AssessmentPeriod $period = null): AssessmentPeriod
    {
        Gate::forUser($actor)->authorize($period ? 'update' : 'create', $period ?? AssessmentPeriod::class);

        return DB::transaction(function () use ($actor, $offering, $data, $period): AssessmentPeriod {
            $offering = $this->lockOffering($offering);
            if ($offering->cycle_format_id !== null) {
                $this->fail('number', 'Este grupo utiliza un formato de ciclo. Su calendario se conserva; crea otro formato para nuevos grupos.');
            }
            $record = $period ? $offering->periods()->lockForUpdate()->findOrFail($period->id) : new AssessmentPeriod(['offering_id' => $offering->id, 'state' => 'open', 'policy_version' => config('attendance.policy_version')]);
            if ($record->state !== 'open') {
                $this->fail('number', 'Un cómputo en cierre o cerrado es de solo lectura.');
            }
            $this->withinDates($offering->cycle->starts_on->toDateString(), $offering->cycle->ends_on->toDateString(), $data['starts_on'], $data['ends_on'], 'starts_on');
            if ($offering->periods()->when($record->exists, fn ($q) => $q->whereKeyNot($record->id))->where('starts_on', '<=', $data['ends_on'])->where('ends_on', '>=', $data['starts_on'])->exists()) {
                $this->fail('starts_on', 'Las fechas se superponen con otro cómputo de este grupo.');
            }
            $before = $record->exists ? $this->audit->snapshot($record) : null;
            if ($record->exists) {
                if ((int) $data['number'] !== $record->number && $record->sessions()->exists()) {
                    $this->fail('number', 'No cambies el número de un cómputo con sesiones.');
                }
                foreach ($record->sessions()->get() as $session) {
                    $this->withinDates($data['starts_on'], $data['ends_on'], $this->sessionDate($session), $this->sessionDate($session, 'ends_at'), 'starts_on');
                }
                $this->reasonForChanges($record, $data, ['number', 'starts_on', 'ends_on']);
            }
            $record->fill(collect($data)->only(['number', 'starts_on', 'ends_on'])->all())->save();
            $this->audit->record($actor, $record, $before ? 'period.updated' : 'period.created', $before, $data['reason'] ?? null);

            return $record;
        });
    }

    public function saveEnrollment(User $actor, Offering $offering, array $data, ?Enrollment $enrollment = null): Enrollment
    {
        Gate::forUser($actor)->authorize($enrollment ? 'update' : 'create', $enrollment ?? Enrollment::class);

        return DB::transaction(function () use ($actor, $offering, $data, $enrollment): Enrollment {
            $offering = $this->lockOffering($offering);
            $closedPeriods = $offering->periods()->whereIn('state', ['closing', 'closed'])->orderBy('id')->lockForUpdate()->get();
            $record = $enrollment ? $offering->enrollments()->lockForUpdate()->findOrFail($enrollment->id) : new Enrollment(['offering_id' => $offering->id, 'student_id' => $data['student_id']]);
            // Protect the eligible date set of every closed period, including a new enrollment.
            $newEnd = $data['active_to'] ?? '9999-12-31';
            foreach ($closedPeriods as $closed) {
                $start = $closed->starts_on->toDateString();
                $end = $closed->ends_on->toDateString();
                $newRange = [max($start, $data['active_from']), min($end, $newEnd)];
                $oldRange = $record->exists ? [max($start, $record->active_from->toDateString()), min($end, $record->active_to?->toDateString() ?? '9999-12-31')] : null;
                $newRange = $newRange[0] <= $newRange[1] ? $newRange : null;
                $oldRange = $oldRange !== null && $oldRange[0] <= $oldRange[1] ? $oldRange : null;
                if ($newRange !== $oldRange) {
                    $this->fail('active_from', 'La vigencia afecta al cómputo cerrado '.$closed->number.'. Coordinación debe reabrirlo antes de modificar su elegibilidad.');
                }
            }
            $student = User::lockForUpdate()->findOrFail($record->student_id);
            if (! $record->exists && (! $student->active || $student->role !== Role::Student)) {
                $this->fail('student_id', 'Selecciona un estudiante activo.');
            }
            if (! $record->exists && $offering->enrollments()->where('student_id', $student->id)->exists()) {
                $this->fail('student_id', 'El estudiante ya está inscrito. Edita la inscripción existente para ajustar su vigencia.');
            }
            $end = $data['active_to'] ?? null;
            $this->withinDates($offering->cycle->starts_on->toDateString(), $offering->cycle->ends_on->toDateString(), $data['active_from'], $end, 'active_from');
            $reserves = $end === null || $end >= $this->seatDate($offering);
            $count = $offering->enrollments()->when($record->exists, fn ($q) => $q->whereKeyNot($record->id))->where(fn ($q) => $q->whereNull('active_to')->orWhere('active_to', '>=', $this->seatDate($offering)))->count();
            if ($reserves && $count >= $offering->capacity) {
                $this->fail('student_id', 'Este grupo ya alcanzó su cupo.');
            }
            $before = $record->exists ? $this->audit->snapshot($record) : null;
            if ($record->exists) {
                foreach ([$record->attendances()->with('session')->get(), $record->absenceRequests()->with('session')->get()] as $rows) {
                    foreach ($rows as $row) {
                        $date = $this->sessionDate($row->session);
                        if ($date < $data['active_from'] || ($end !== null && $date > $end)) {
                            $this->fail('active_to', 'La vigencia debe conservar las fechas de las asistencias y solicitudes existentes.');
                        }
                    }
                }
                $this->reasonForChanges($record, $data, ['active_from', 'active_to']);
            }
            $record->fill(['active_from' => $data['active_from'], 'active_to' => $end])->save();
            $this->audit->record($actor, $record, $before ? 'enrollment.updated' : 'enrollment.created', $before, $data['reason'] ?? null);

            return $record;
        });
    }

    public function reservedSeats(Offering $offering): int
    {
        return $offering->enrollments()->where(fn ($q) => $q->whereNull('active_to')->orWhere('active_to', '>=', $this->seatDate($offering)))->count();
    }

    public function seatDate(Offering $offering): string
    {
        return max(now(config('smartaulaues.display_timezone'))->toDateString(), $offering->cycle->starts_on->toDateString());
    }

    public function lockOffering(Offering $offering): Offering
    {
        $cycle = AcademicCycle::lockForUpdate()->findOrFail($offering->cycle_id);
        $this->openCycle($cycle);
        $offering = Offering::lockForUpdate()->findOrFail($offering->id);
        // A concurrent empty-group reassignment must be retried using its current cycle.
        if ($offering->cycle_id !== $cycle->id) {
            $this->fail('offering_id', 'El grupo cambió de ciclo. Actualiza la página e inténtalo nuevamente.');
        }
        $offering->setRelation('cycle', $cycle);

        return $offering;
    }

    private function openCycle(AcademicCycle $cycle): void
    {
        if ($cycle->status === 'closed') {
            $this->fail('cycle_id', 'Un ciclo cerrado es de solo lectura.');
        }
    }

    private function withinDates(string $start, string $end, string $from, ?string $to, string $field): void
    {
        if ($from < $start || $from > $end || ($to !== null && ($to < $from || $to > $end))) {
            $this->fail($field, 'Las fechas deben estar dentro del ciclo o rango que contiene este registro.');
        }
    }

    private function sessionDate($session, string $field = 'starts_at'): string
    {
        return $session->{$field}->setTimezone(config('smartaulaues.display_timezone'))->toDateString();
    }

    private function reasonForChanges($record, array $data, array $fields): void
    {
        foreach ($fields as $field) {
            $current = $record->{$field};
            $current = $current instanceof \DateTimeInterface ? $current->format('Y-m-d') : (is_bool($current) ? (int) $current : $current);
            if ((string) $current !== (string) ($data[$field] ?? null) && blank($data['reason'] ?? null)) {
                $this->fail('reason', 'Indica el motivo de este cambio para conservar su trazabilidad.');
            }
        }
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
