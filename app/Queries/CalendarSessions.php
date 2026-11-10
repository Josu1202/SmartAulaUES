<?php

namespace App\Queries;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * B06: keep student visibility within PostgreSQL rather than creating an
 * unbounded OR clause and hydrating every enrollment for each calendar page.
 * This does not modify the calculation, historical closure, or group policies.
 */
final class CalendarSessions
{
    public function forStudent(Builder $sessions, User $student): Builder
    {
        abort_unless($student->active && $student->role === Role::Student, 403);

        // Both start and withdrawal days are inclusive in the academic timezone.
        // The same PostgreSQL date conversion is used by News::visibleTo().
        return $sessions->whereExists(static function (QueryBuilder $query) use ($student): void {
            $academicDate = "(class_sessions.starts_at AT TIME ZONE 'America/El_Salvador')::date";
            $query->selectRaw('1')
                ->from('enrollments as calendar_enrollments')
                ->whereColumn('calendar_enrollments.offering_id', 'class_sessions.offering_id')
                ->where('calendar_enrollments.student_id', $student->id)
                ->whereRaw("calendar_enrollments.active_from <= {$academicDate}")
                ->where(static function (QueryBuilder $active) use ($academicDate): void {
                    $active->whereNull('calendar_enrollments.active_to')
                        ->orWhereRaw("calendar_enrollments.active_to >= {$academicDate}");
                });
        });
    }
}
