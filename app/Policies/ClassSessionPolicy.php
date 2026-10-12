<?php

namespace App\Policies;

use App\Models\ClassSession;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class ClassSessionPolicy
{
    public function view(User $user, ClassSession $resource): bool
    {
        return $user->active && Gate::forUser($user)->allows('view', $resource->offering);
    }
}
