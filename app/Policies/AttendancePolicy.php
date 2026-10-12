<?php

namespace App\Policies;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class AttendancePolicy
{
    public function view(User $user, Attendance $resource): bool
    {
        return $user->active && Gate::forUser($user)->allows('view', $resource->enrollment);
    }
}
