<?php

namespace App\Http\Controllers;

use App\Actions\Academic\AcademicRegistry;
use App\Actions\Academic\OfferingLifecycle;
use App\Models\Enrollment;
use App\Models\Offering;
use App\Queries\AttendanceSummary;
use App\Queries\DashboardStatistics;
use App\Queries\OfferingCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class OfferingController extends Controller
{
    public function index(Request $request, OfferingCatalog $catalog, DashboardStatistics $dashboard): View
    {
        $filters = $catalog->filters($request);
        $query = $catalog->query($request->user(), $filters);
        $offerings = (clone $query)->with(['subject', 'cycle', 'teacher'])->withCount(['enrollments' => fn ($q) => $q->visibleTo($request->user())])->orderBy('id')->paginate(12)->withQueryString();
        $cycles = $catalog->cycles($request->user());
        $statistics = $filters['view'] === 'history' && $filters['cycle_id'] !== null ? $dashboard->forUser($request->user(), $query) : null;
        $groupCount = (clone $query)->count();
        $enrollmentCount = Enrollment::visibleTo($request->user())->whereIn('offering_id', (clone $query)->select('offerings.id'))->count();

        return view('academic.offerings.index', compact('offerings', 'filters', 'cycles', 'statistics', 'groupCount', 'enrollmentCount'));
    }

    public function show(Request $request, Offering $offering, AcademicRegistry $registry, AttendanceSummary $summary): View
    {
        Gate::authorize('view', $offering);
        $data = $request->validate(['period_id' => ['nullable', 'integer', 'min:1'], 'results_page' => ['nullable', 'integer', 'min:1'], 'report_version' => ['nullable', 'integer', 'min:1']]);
        $attendanceResults = $summary->forOffering($request->user(), $offering, isset($data['period_id']) ? (int) $data['period_id'] : null, (int) ($data['results_page'] ?? 1), reportVersion: isset($data['report_version']) ? (int) $data['report_version'] : null);
        $attendanceResults['rows']->withQueryString();
        $offering->load(['subject', 'cycle', 'teacher', 'periods'])->loadCount(['sessions as scheduled_sessions_count' => fn ($q) => $q->where('state', 'scheduled')]);
        $sessions = $offering->sessions()->orderBy('starts_at')->paginate(15, ['*'], 'sessions_page')->withQueryString();
        $enrollments = $offering->enrollments()->visibleTo($request->user())->with('student')->orderBy('id')->paginate(20)->withQueryString();
        $reservedSeats = $registry->reservedSeats($offering);
        $lifecycle = app(OfferingLifecycle::class);
        $deactivationIssues = $request->user()->can('administer', $offering) && $offering->active ? $lifecycle->diagnosticIssues($offering) : [];
        $deletionIssues = $request->user()->can('delete', $offering) ? $lifecycle->deletionIssues($offering) : [];

        return view('academic.offerings.show', compact('offering', 'enrollments', 'reservedSeats', 'attendanceResults', 'deactivationIssues', 'deletionIssues', 'sessions'));
    }
}
