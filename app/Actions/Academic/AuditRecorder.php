<?php

namespace App\Actions\Academic;

use App\Models\AcademicCycle;
use App\Models\AssessmentPeriod;
use App\Models\AuditLog;
use App\Models\Enrollment;
use App\Models\Offering;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AuditRecorder
{
    public function snapshot(Model $model): array
    {
        return $model->only(match ($model::class) {
            User::class => ['name', 'email', 'role', 'student_code', 'active'],
            AcademicCycle::class => ['code', 'starts_on', 'ends_on', 'status'],
            Subject::class => ['code', 'name', 'active'],
            Offering::class => ['cycle_id', 'subject_id', 'teacher_id', 'group_code', 'capacity'],
            AssessmentPeriod::class => ['offering_id', 'number', 'starts_on', 'ends_on', 'state'],
            Enrollment::class => ['offering_id', 'student_id', 'active_from', 'active_to'],
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
}
