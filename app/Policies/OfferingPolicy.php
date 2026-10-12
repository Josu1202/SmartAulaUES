<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Offering;
use App\Models\User;

class OfferingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->active;
    }

    public function view(User $user, Offering $offering): bool
    {
        if (! $user->active) {
            return false;
        }

        return match ($user->role) {
            Role::Coordinator => true,
            Role::Teacher => $offering->teacher_id === $user->id,
            Role::Student => $offering->enrollments()->where('student_id', $user->id)->exists(),
        };
    }
}
