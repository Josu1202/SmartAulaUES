<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Academic\AcademicRegistry;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveSubjectRequest;
use App\Models\Subject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SubjectController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Subject::class);
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:80']]);
        $subjects = Subject::withCount('offerings')->when($filters['q'] ?? null, fn ($q, $search) => $q->where(fn ($q) => $q->where('name', 'ilike', '%'.$search.'%')->orWhere('code', 'ilike', '%'.$search.'%')))->orderBy('name')->paginate(12)->withQueryString();

        return view('admin.subjects.index', compact('subjects'));
    }

    public function create(): View
    {
        Gate::authorize('create', Subject::class);

        return view('admin.academic.form', ['kind' => 'subject', 'record' => new Subject(['active' => true])]);
    }

    public function store(SaveSubjectRequest $request, AcademicRegistry $registry): RedirectResponse
    {
        $registry->saveSubject($request->user(), $request->validated());

        return to_route('admin.subjects.index')->with('status', 'Materia creada.');
    }

    public function edit(Subject $subject): View
    {
        Gate::authorize('update', $subject);

        return view('admin.academic.form', ['kind' => 'subject', 'record' => $subject]);
    }

    public function update(SaveSubjectRequest $request, Subject $subject, AcademicRegistry $registry): RedirectResponse
    {
        $registry->saveSubject($request->user(), $request->validated(), $subject);

        return to_route('admin.subjects.index')->with('status', 'Materia actualizada.');
    }
}
