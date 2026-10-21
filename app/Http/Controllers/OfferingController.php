<?php

namespace App\Http\Controllers;

use App\Actions\Academic\AcademicRegistry;
use App\Models\Offering;
use App\Queries\AttendanceSummary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class OfferingController extends Controller
{
    public function index(Request $request): View
    {
        $offerings = Offering::visibleTo($request->user())->with(['subject', 'cycle', 'teacher'])->withCount('enrollments')->orderBy('id')->paginate(12);

        return view('academic.offerings.index', compact('offerings'));
    }

    public function show(Request $request, Offering $offering, AcademicRegistry $registry, AttendanceSummary $summary): View
    {
        Gate::authorize('view', $offering);
        $data = $request->validate(['period_id' => ['nullable', 'integer', 'min:1'], 'results_page' => ['nullable', 'integer', 'min:1']]);
        $attendanceResults = $summary->forOffering($request->user(), $offering, isset($data['period_id']) ? (int) $data['period_id'] : null, (int) ($data['results_page'] ?? 1));
        $attendanceResults['rows']->withQueryString();
        $offering->load(['subject', 'cycle', 'teacher', 'periods', 'sessions']);
        $enrollments = $offering->enrollments()->visibleTo($request->user())->with('student')->orderBy('id')->paginate(20)->withQueryString();
        $reservedSeats = $registry->reservedSeats($offering);

        return view('academic.offerings.show', compact('offering', 'enrollments', 'reservedSeats', 'attendanceResults'));
    }
}
