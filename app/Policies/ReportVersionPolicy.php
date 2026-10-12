<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\ReportVersion;
use App\Models\User;

class ReportVersionPolicy
{
    public function view(User $user, ReportVersion $report): bool
    {
        return $user->active && ($user->role === Role::Coordinator || ($user->role === Role::Teacher && $report->period->offering->teacher_id === $user->id));
    }
}
