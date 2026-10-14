<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class CoordinatorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->active && $user->role === Role::Coordinator;
    }

    public function view(User $user, Model $resource): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Model $resource): bool
    {
        return $this->viewAny($user);
    }
}
