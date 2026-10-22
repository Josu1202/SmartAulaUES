<?php

namespace App\Actions\Academic;

use App\Models\AbsenceRequest;
use App\Models\AcademicCycle;
use App\Models\AssessmentPeriod;
use App\Models\Attendance;
use App\Models\AttendancePortal;
use App\Models\AuditLog;
use App\Models\ClassSession;
use App\Models\CycleFormat;
use App\Models\Enrollment;
use App\Models\NfcCredential;
use App\Models\Offering;
use App\Models\ReaderSession;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AuditRecorder
{
    public function snapshot(Model $model): array
    {
        return $model->only(match ($model::class) {
            User::class => ['name', 'email', 'role', 'student_code', 'active', 'entry_year', 'study_year'],
            AcademicCycle::class => ['code', 'starts_on', 'ends_on', 'status'],
            Subject::class => ['code', 'name', 'active'],
            Offering::class => ['cycle_id', 'subject_id', 'teacher_id', 'group_code', 'capacity', 'cycle_format_id'],
            CycleFormat::class => ['cycle_id', 'name', 'version', 'active', 'periods'],
            AssessmentPeriod::class => ['offering_id', 'number', 'starts_on', 'ends_on', 'state', 'policy_version'],
            Enrollment::class => ['offering_id', 'student_id', 'active_from', 'active_to'],
            ClassSession::class => ['offering_id', 'period_id', 'starts_at', 'ends_at', 'modality', 'event_attendance_mode', 'state', 'change_reason'],
            AttendancePortal::class => ['session_id', 'opens_at', 'expires_at', 'closed_at', 'enabled_methods', 'access_modality'],
            ReaderSession::class => ['portal_id', 'teacher_id', 'label', 'expires_at', 'state'],
            NfcCredential::class => ['user_id', 'state', 'pending_expires_at', 'activated_at', 'revoked_at'],
            Attendance::class => ['offering_id', 'enrollment_id', 'session_id', 'status', 'method', 'recorded_by', 'recorded_at'],
            AbsenceRequest::class => ['offering_id', 'enrollment_id', 'session_id', 'reason', 'status', 'resolved_by', 'resolution_note', 'resolved_at'],
            default => [],
        });
    }

    public function record(User $actor, Model $model, string $action, ?array $before = null, ?string $reason = null): void
    {
        AuditLog::create([
            'actor_id' => $actor->id, 'entity_type' => class_basename($model), 'entity_id' => (string) $model->getKey(),
            'action' => $action, 'before' => $before, 'after' => $this->snapshot($model), 'reason' => $reason,
        ]);
    }

    public function recordSystem(Model $model, string $action, array $before, string $reason): void
    {
        AuditLog::create([
            'actor_id' => null, 'entity_type' => class_basename($model), 'entity_id' => (string) $model->getKey(),
            'action' => $action, 'before' => $before, 'after' => $this->snapshot($model), 'reason' => $reason,
        ]);
    }
}
