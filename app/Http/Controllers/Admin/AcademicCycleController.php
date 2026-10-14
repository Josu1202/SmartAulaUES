<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Academic\AcademicRegistry;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveCycleRequest;
use App\Models\AcademicCycle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AcademicCycleController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', AcademicCycle::class);

        return view('admin.cycles.index', ['cycles' => AcademicCycle::withCount('offerings')->orderByDesc('starts_on')->get()]);
    }

    public function create(): View
    {
        Gate::authorize('create', AcademicCycle::class);

        return view('admin.academic.form', ['kind' => 'cycle', 'record' => new AcademicCycle(['status' => 'planned'])]);
    }

    public function store(SaveCycleRequest $request, AcademicRegistry $registry): RedirectResponse
    {
        $registry->saveCycle($request->user(), $request->validated());

        return to_route('admin.cycles.index')->with('status', 'Ciclo creado.');
    }

    public function edit(AcademicCycle $cycle): View
    {
        Gate::authorize('update', $cycle);

        return view('admin.academic.form', ['kind' => 'cycle', 'record' => $cycle]);
    }

    public function update(SaveCycleRequest $request, AcademicCycle $cycle, AcademicRegistry $registry): RedirectResponse
    {
        $registry->saveCycle($request->user(), $request->validated(), $cycle);

        return to_route('admin.cycles.index')->with('status', 'Ciclo actualizado.');
    }
}
