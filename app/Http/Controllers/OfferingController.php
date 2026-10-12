<?php

namespace App\Http\Controllers;

use App\Models\Offering;
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

    public function show(Request $request, Offering $offering): View
    {
        Gate::authorize('view', $offering);
        $offering->load(['subject', 'cycle', 'teacher', 'periods', 'sessions']);
        $enrollments = $offering->enrollments()->visibleTo($request->user())->with('student')->get();

        return view('academic.offerings.show', compact('offering', 'enrollments'));
    }
}
