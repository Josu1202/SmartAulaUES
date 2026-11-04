<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Enums\SessionState;
use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Offering;
use App\Models\User;
use App\Queries\DashboardStatistics;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $groups = Offering::visibleTo($user)->current();
        $ids = (clone $groups)->select('offerings.id');
        $offerings = (clone $groups)->with(['subject', 'cycle'])->withCount(['enrollments' => fn ($q) => $q->visibleTo($user)])
            ->orderBy('id')->paginate(6, pageName: 'groups_page')->withQueryString();
        $sessions = ClassSession::whereIn('offering_id', $ids)->where('state', SessionState::Scheduled)->where('starts_at', '>=', now())->orderBy('starts_at')->with('offering.subject')->limit(5)->get();
        $studentCount = $user->role === Role::Coordinator ? User::where('role', Role::Student)->where('active', true)->count()
            : Enrollment::whereIn('offering_id', $ids)->visibleTo($user)->count();

        $dashboard = app(DashboardStatistics::class);
        $statistics = $dashboard->forUser($user);
        $absenceRanking = null;
        if ($user->role === Role::Teacher) {
            $rankingInput = $request->validate([
                'ranking_cycle_id' => ['nullable', 'integer', 'min:1'],
                'ranking_sort' => ['nullable', 'in:absences,rate'],
                'ranking_date_from' => ['nullable', 'date_format:Y-m-d'],
                'ranking_date_to' => ['nullable', 'date_format:Y-m-d'],
                'ranking_group_id' => ['nullable', 'integer', 'min:1'],
                'ranking_period_id' => ['nullable', 'integer', 'min:1'],
                'ranking_enrollment_id' => ['nullable', 'integer', 'min:1'],
                'matrix_page' => ['nullable', 'integer', 'min:1'],
            ]);
            if (isset($rankingInput['ranking_date_from'], $rankingInput['ranking_date_to'])
                && $rankingInput['ranking_date_from'] > $rankingInput['ranking_date_to']) {
                throw ValidationException::withMessages([
                    'ranking_date_to' => 'La fecha final debe ser igual o posterior a la inicial.',
                ]);
            }
            $absenceRanking = $dashboard->teacherAbsenceRanking(
                $user,
                isset($rankingInput['ranking_group_id']) ? (int) $rankingInput['ranking_group_id'] : null,
                isset($rankingInput['ranking_period_id']) ? (int) $rankingInput['ranking_period_id'] : null,
                isset($rankingInput['ranking_enrollment_id']) ? (int) $rankingInput['ranking_enrollment_id'] : null,
                isset($rankingInput['matrix_page']) ? (int) $rankingInput['matrix_page'] : 1,
                isset($rankingInput['ranking_cycle_id']) ? (int) $rankingInput['ranking_cycle_id'] : null,
                $rankingInput['ranking_sort'] ?? 'absences',
                $rankingInput['ranking_date_from'] ?? null,
                $rankingInput['ranking_date_to'] ?? null,
            );
        }

        return view('dashboard.index', compact('user', 'offerings', 'sessions', 'studentCount', 'statistics', 'absenceRanking'));
    }
}
