<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Academic\AcademicRegistry;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveOfferingRequest;
use App\Models\AcademicCycle;
use App\Models\Offering;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class OfferingController extends Controller
{
    public function create(): View
    {
        Gate::authorize('create', Offering::class);

        return $this->form(new Offering(['capacity' => 30]));
    }

    public function store(SaveOfferingRequest $request, AcademicRegistry $registry): RedirectResponse
    {
        $offering = $registry->saveOffering($request->user(), $request->validated());

        return to_route('offerings.show', $offering)->with('status', 'Grupo creado. Ahora puedes definir sus cómputos e inscripciones.');
    }

    public function edit(Offering $offering): View
    {
        Gate::authorize('update', $offering);

        return $this->form($offering);
    }

    public function update(SaveOfferingRequest $request, Offering $offering, AcademicRegistry $registry): RedirectResponse
    {
        $registry->saveOffering($request->user(), $request->validated(), $offering);

        return to_route('offerings.show', $offering)->with('status', 'Grupo actualizado.');
    }

    private function form(Offering $record): View
    {
        return view('admin.academic.form', [
            'kind' => 'offering', 'record' => $record,
            'cycles' => AcademicCycle::where('status', '!=', 'closed')->orWhere('id', $record->cycle_id)->orderByDesc('starts_on')->pluck('code', 'id'),
            'subjects' => Subject::where('active', true)->orWhere('id', $record->subject_id)->orderBy('name')->get()->mapWithKeys(fn ($s) => [$s->id => $s->code.' · '.$s->name]),
            'teachers' => User::where('role', Role::Teacher->value)->where('active', true)->orderBy('name')->pluck('name', 'id'),
        ]);
    }
}
