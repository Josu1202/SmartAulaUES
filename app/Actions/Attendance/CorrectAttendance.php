<?php

namespace App\Actions\Attendance;

use App\Actions\Academic\AuditRecorder;
use App\Enums\AttendanceStatus;
use App\Enums\SessionState;
use App\Models\Attendance;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CorrectAttendance
{
    public function __construct(private AcademicSessionLock $locks, private AuditRecorder $audit) {}

    public function execute(User $actor, Attendance $attendance, string $status, string $reason): void
    {
        DB::transaction(function () use ($actor, $attendance, $status, $reason): void {
            $session = $this->locks->get($attendance->session);
            Gate::forUser($actor)->authorize('update', $session);
            if (! in_array($session->state, [SessionState::InProgress, SessionState::Completed])) {
                throw ValidationException::withMessages(['status' => 'Solo se corrigen sesiones en curso o realizadas.']);
            }
            $record = Attendance::lockForUpdate()->findOrFail($attendance->id);
            if ($record->status === AttendanceStatus::Justified) {
                throw ValidationException::withMessages(['status' => 'Una ausencia justificada por permiso conserva su resolución.']);
            }
            $before = $this->audit->snapshot($record);
            $record->update(['status' => $status, 'method' => 'manual', 'recorded_by' => $actor->id, 'recorded_at' => now()]);
            $this->audit->record($actor, $record, 'attendance.corrected', $before, $reason);
        });
    }
}
