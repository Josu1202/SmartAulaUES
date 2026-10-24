<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Academic\AcademicRegistry;
use App\Actions\Academic\OfferingLifecycle;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveOfferingRequest;
use App\Models\AcademicCycle;
use App\Models\CycleFormat;
use App\Models\Offering;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class OfferingController extends Controller
{
    public function state(Request $request, Offering $offering, string $operation, OfferingLifecycle $lifecycle): RedirectResponse
    {
        Gate::authorize('administer', $offering);
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']]);
        if ($operation === 'expire-resources') {
            $lifecycle->closeExpiredResources($request->user(), $offering, $data['reason']);
        } else {
            $lifecycle->setActive($request->user(), $offering, $operation === 'reactivate', $data['reason']);
        }

        return to_route('offerings.show', $offering)->with('status', match ($operation) {
            'deactivate' => 'Grupo desactivado. Su información y sesiones futuras se conservan.',
            'reactivate' => 'Grupo habilitado. Los ciclos, cómputos y recursos conservan su estado.',
            default => 'Recursos vencidos cerrados. Las sesiones y los resultados se conservan.',
        });
    }

    public function destroy(Request $request, Offering $offering, OfferingLifecycle $lifecycle): RedirectResponse
    {
        Gate::authorize('delete', $offering);
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000'], 'confirmation' => ['required', 'string', 'max:20']]);
        $lifecycle->delete($request->user(), $offering, $data['confirmation'], $data['reason']);

        return to_route('offerings.index')->with('status', 'Grupo vacío eliminado. La identificación y el motivo se conservan en la bitácora.');
    }

    public function create(): View
    {
        Gate::authorize('create', Offering::class);

        return $this->form(new Offering(['capacity' => 30]));
    }

    public function store(SaveOfferingRequest $request, AcademicRegistry $registry): RedirectResponse
    {
        $offering = $registry->saveOffering($request->user(), $request->validated());

        return to_route('offerings.show', $offering)->with('status', $offering->cycle_format_id ? 'Grupo creado con el calendario del formato. Ahora puedes cargar sus estudiantes.' : 'Grupo creado. Ahora puedes definir sus cómputos e inscripciones.');
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
            'formats' => CycleFormat::where('active', true)->whereHas('cycle', fn ($q) => $q->where('status', '!=', 'closed'))->with('cycle', 'periods')->orderBy('name')->get(),
        ]);
    }
}
