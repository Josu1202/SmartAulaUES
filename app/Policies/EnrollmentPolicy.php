<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Enrollment;
use App\Models\User;

class EnrollmentPolicy
{
    public function view(User $user, Enrollment $enrollment): bool
    {
        if (! $user->active) {
            return false;
        }

        return match ($user->role) {
            Role::Coordinator => true,
            Role::Teacher => $enrollment->offering->teacher_id === $user->id,
            Role::Student => $enrollment->student_id === $user->id,
        };
    }
}
