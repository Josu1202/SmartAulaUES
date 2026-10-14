<?php

namespace App\Actions\Attendance;

use App\Actions\Academic\AuditRecorder;
use App\Enums\AttendanceStatus;
use App\Enums\SessionState;
use App\Models\AbsenceRequest;
use App\Models\Attendance;
use App\Models\ClassSession;
use App\Models\User;
use App\Notifications\AcademicNotice;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AbsenceWorkflow
{
    public function __construct(private AcademicSessionLock $locks, private AuditRecorder $audit) {}

    public function submit(User $actor, ClassSession $session, string $reason, ?UploadedFile $evidence): AbsenceRequest
    {
        Gate::forUser($actor)->authorize('create', [AbsenceRequest::class, $session]);
        $path = null;
        try {
            return DB::transaction(function () use ($actor, $session, $reason, $evidence, &$path): AbsenceRequest {
                $session = $this->locks->get($session);
                if ($session->state === SessionState::Cancelled) {
                    $this->fail('reason', 'La sesión está cancelada.');
                }
                $date = $session->starts_at->timezone(config('smartaulaues.display_timezone'))->toDateString();
                $enrollment = $session->offering->enrollments()->where('student_id', $actor->id)->where('active_from', '<=', $date)->where(fn ($q) => $q->whereNull('active_to')->orWhere('active_to', '>=', $date))->first();
                if (! $enrollment) {
                    $this->fail('reason', 'No tienes inscripción vigente en la fecha de esta sesión.');
                }
                if (AbsenceRequest::where('session_id', $session->id)->where('enrollment_id', $enrollment->id)->exists()) {
                    $this->fail('reason', 'Ya existe una solicitud para esta sesión.');
                }
                if (Attendance::where('session_id', $session->id)->where('enrollment_id', $enrollment->id)->where('status', 'present')->exists()) {
                    $this->fail('reason', 'Esta sesión ya registra tu presencia.');
                }
                if ($evidence) {
                    $path = $evidence->store('absence-evidence', 'private');
                }
                $record = AbsenceRequest::create(['offering_id' => $session->offering_id, 'session_id' => $session->id, 'enrollment_id' => $enrollment->id, 'reason' => $reason, 'evidence_path' => $path, 'status' => 'pending']);
                $this->audit->record($actor, $record, 'request.submitted');
                $session->offering->teacher->notify(new AcademicNotice('Nueva solicitud de permiso', $actor->name.' solicitó permiso para '.$session->offering->subject->name, route('requests.show', $record, absolute: false)));

                return $record;
            });
        } catch (\Throwable $exception) {
            if ($path) {
                Storage::disk('private')->delete($path);
            } throw $exception;
        }
    }

    public function resolve(User $actor, AbsenceRequest $request, string $decision, string $note): void
    {
        DB::transaction(function () use ($actor, $request, $decision, $note): void {
            $session = $this->locks->get($request->session);
            $request = AbsenceRequest::lockForUpdate()->findOrFail($request->id);
            Gate::forUser($actor)->authorize('resolve', $request->setRelation('session', $session));
            if ($request->status !== 'pending') {
                $this->fail('decision', 'La solicitud ya fue resuelta.');
            }
            $attendance = Attendance::where('session_id', $session->id)->where('enrollment_id', $request->enrollment_id)->lockForUpdate()->first();
            if ($decision === 'approved' && $attendance?->status === AttendanceStatus::Present) {
                $this->fail('decision', 'El estudiante tiene presencia registrada. Rechaza la solicitud indicando el motivo.');
            }
            $before = $this->audit->snapshot($request);
            $request->update(['status' => $decision, 'resolved_by' => $actor->id, 'resolution_note' => $note, 'resolved_at' => now()]);
            if ($decision === 'approved' && $attendance) {
                $previous = $this->audit->snapshot($attendance);
                $attendance->update(['status' => 'justified', 'recorded_by' => $actor->id, 'recorded_at' => now()]);
                $this->audit->record($actor, $attendance, 'attendance.justified', $previous, $note);
            }
            $this->audit->record($actor, $request, 'request.'.$decision, $before, $note);
            $request->enrollment->student->notify(new AcademicNotice('Permiso '.($decision === 'approved' ? 'aprobado' : 'rechazado'), $note, route('requests.show', $request, absolute: false)));
        });
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
