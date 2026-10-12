<?php

namespace App\Policies;

use App\Models\AbsenceRequest;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class AbsenceRequestPolicy
{
    public function view(User $user, AbsenceRequest $resource): bool
    {
        return $user->active && Gate::forUser($user)->allows('view', $resource->enrollment);
    }
}
