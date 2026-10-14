<?php

namespace App\Policies;

use App\Models\ClassSession;
use App\Models\Offering;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class ClassSessionPolicy
{
    public function create(User $user, Offering $offering): bool
    {
        return Gate::forUser($user)->allows('manage', $offering);
    }

    public function update(User $user, ClassSession $resource): bool
    {
        return Gate::forUser($user)->allows('manage', $resource->offering);
    }

    public function view(User $user, ClassSession $resource): bool
    {
        return $user->active && Gate::forUser($user)->allows('view', $resource->offering);
    }
}
