<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Academic\AcademicRegistry;
use App\Http\Controllers\Controller;
use App\Http\Requests\SavePeriodRequest;
use App\Models\AssessmentPeriod;
use App\Models\Offering;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AssessmentPeriodController extends Controller
{
    public function create(Offering $offering): View
    {
        Gate::authorize('create', AssessmentPeriod::class);

        return view('admin.academic.form', ['kind' => 'period', 'record' => new AssessmentPeriod(['number' => ($offering->periods()->max('number') ?? 0) + 1]), 'offering' => $offering->load('subject', 'cycle')]);
    }

    public function store(SavePeriodRequest $request, Offering $offering, AcademicRegistry $registry): RedirectResponse
    {
        $registry->savePeriod($request->user(), $offering, $request->validated());

        return to_route('offerings.show', $offering)->with('status', 'Cómputo creado.');
    }

    public function edit(Offering $offering, AssessmentPeriod $period): View
    {
        Gate::authorize('update', $period);

        return view('admin.academic.form', ['kind' => 'period', 'record' => $period, 'offering' => $offering->load('subject', 'cycle')]);
    }

    public function update(SavePeriodRequest $request, Offering $offering, AssessmentPeriod $period, AcademicRegistry $registry): RedirectResponse
    {
        $registry->savePeriod($request->user(), $offering, $request->validated(), $period);

        return to_route('offerings.show', $offering)->with('status', 'Cómputo actualizado.');
    }
}
