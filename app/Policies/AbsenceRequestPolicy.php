<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\AbsenceRequest;
use App\Models\ClassSession;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class AbsenceRequestPolicy
{
    public function create(User $user, ClassSession $session): bool
    {
        return $user->active && $user->role === Role::Student && Gate::forUser($user)->allows('view', $session);
    }

    public function resolve(User $user, AbsenceRequest $resource): bool
    {
        return Gate::forUser($user)->allows('update', $resource->session);
    }

    public function view(User $user, AbsenceRequest $resource): bool
    {
        return $user->active && Gate::forUser($user)->allows('view', $resource->enrollment);
    }
}
