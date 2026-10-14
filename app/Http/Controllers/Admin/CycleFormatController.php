<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Academic\CycleFormatManager;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveCycleFormatRequest;
use App\Models\AcademicCycle;
use App\Models\CycleFormat;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CycleFormatController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', CycleFormat::class);

        return view('admin.formats.index', ['formats' => CycleFormat::with('cycle', 'periods')->withCount('offerings')->orderByDesc('id')->paginate(12)]);
    }

    public function create(): View
    {
        Gate::authorize('create', CycleFormat::class);

        return $this->form(new CycleFormat(['active' => true]));
    }

    public function store(SaveCycleFormatRequest $request, CycleFormatManager $manager): RedirectResponse
    {
        $manager->save($request->user(), $request->validated());

        return to_route('admin.formats.index')->with('status', 'Formato creado. Los grupos nuevos podrán heredar sus cómputos y fechas.');
    }

    public function edit(CycleFormat $format): View
    {
        Gate::authorize('update', $format);

        return $this->form($format->load('periods'));
    }

    public function update(SaveCycleFormatRequest $request, CycleFormat $format, CycleFormatManager $manager): RedirectResponse
    {
        $manager->save($request->user(), $request->validated(), $format);

        return to_route('admin.formats.index')->with('status', 'Formato actualizado.');
    }

    private function form(CycleFormat $format): View
    {
        return view('admin.formats.form', ['format' => $format, 'cycles' => AcademicCycle::where('status', '!=', 'closed')->orWhere('id', $format->cycle_id)->orderByDesc('starts_on')->get()]);
    }
}
