<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Enums\SessionState;
use App\Models\ClassSession;
use App\Models\Offering;
use App\Models\User;
use App\Queries\DashboardStatistics;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $offerings = Offering::visibleTo($user)->with(['subject', 'cycle', 'teacher'])->withCount('enrollments')->get();
        $sessions = ClassSession::whereIn('offering_id', $offerings->modelKeys())->where('state', SessionState::Scheduled)->where('starts_at', '>=', now())->orderBy('starts_at')->with('offering.subject')->limit(5)->get();
        $studentCount = $user->role === Role::Coordinator ? User::where('role', Role::Student)->where('active', true)->count() : $offerings->sum('enrollments_count');

        $statistics = app(DashboardStatistics::class)->forUser($user);

        return view('dashboard.index', compact('user', 'offerings', 'sessions', 'studentCount', 'statistics'));
    }
}
