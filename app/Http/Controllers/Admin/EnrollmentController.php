<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Academic\AcademicRegistry;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveEnrollmentRequest;
use App\Models\Enrollment;
use App\Models\Offering;
use App\Models\User;
use App\Rules\StudentLevel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class EnrollmentController extends Controller
{
    public function create(Offering $offering): View
    {
        Gate::authorize('create', Enrollment::class);
        $students = User::where('role', Role::Student->value)->where('active', true)->where('graduated', false)->whereDoesntHave('enrollments', fn ($q) => $q->where('offering_id', $offering->id))->orderBy('name')->get()->mapWithKeys(fn ($s) => [$s->id => $s->student_code.' · '.$s->name]);

        return view('admin.academic.form', ['kind' => 'enrollment', 'record' => new Enrollment(['active_from' => $offering->cycle->starts_on]), 'offering' => $offering->load('subject', 'cycle'), 'students' => $students]);
    }

    public function store(SaveEnrollmentRequest $request, Offering $offering, AcademicRegistry $registry): RedirectResponse
    {
        $registry->saveEnrollment($request->user(), $offering, $request->validated());

        return to_route('offerings.show', $offering)->with('status', 'Estudiante inscrito.');
    }

    public function batch(Request $request, Offering $offering): View
    {
        Gate::authorize('create', Enrollment::class);
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:80'], 'entry_year' => ['nullable', 'integer', 'between:1900,2200'], 'study_year' => ['nullable', new StudentLevel]]);
        $students = User::where('role', 'student')->where('active', true)->where('graduated', false)->whereDoesntHave('enrollments', fn ($q) => $q->where('offering_id', $offering->id))
            ->when($filters['entry_year'] ?? null, fn ($q, $year) => $q->where('entry_year', $year))->when($filters['study_year'] ?? null, fn ($q, $year) => $q->studyLevel($year))
            ->when($filters['q'] ?? null, fn ($q, $search) => $q->where(fn ($q) => $q->where('name', 'ilike', '%'.$search.'%')->orWhere('student_code', 'ilike', '%'.$search.'%')))->orderBy('name')->paginate(25)->withQueryString();

        return view('admin.enrollments.batch', ['students' => $students, 'offering' => $offering->load('cycle', 'subject')]);
    }

    public function storeBatch(Request $request, Offering $offering, AcademicRegistry $registry): RedirectResponse
    {
        Gate::authorize('create', Enrollment::class);
        $data = $request->validate(['student_ids' => ['required', 'array', 'min:1', 'max:200'], 'student_ids.*' => ['required', 'integer', 'distinct', 'exists:users,id'], 'active_from' => ['required', 'date_format:Y-m-d'], 'active_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:active_from']]);
        DB::transaction(function () use ($request, $offering, $registry, $data): void {
            $offering = $registry->lockOffering($offering);
            foreach ($data['student_ids'] as $id) {
                $registry->saveEnrollment($request->user(), $offering, ['student_id' => $id, 'active_from' => $data['active_from'], 'active_to' => $data['active_to'] ?? null]);
            }
        });

        return to_route('offerings.show', $offering)->with('status', count($data['student_ids']).' estudiantes inscritos.');
    }

    public function edit(Offering $offering, Enrollment $enrollment): View
    {
        Gate::authorize('update', $enrollment);

        return view('admin.academic.form', ['kind' => 'enrollment', 'record' => $enrollment->load('student'), 'offering' => $offering->load('subject', 'cycle')]);
    }

    public function update(SaveEnrollmentRequest $request, Offering $offering, Enrollment $enrollment, AcademicRegistry $registry): RedirectResponse
    {
        $registry->saveEnrollment($request->user(), $offering, $request->validated(), $enrollment);

        return to_route('offerings.show', $offering)->with('status', 'Vigencia de inscripción actualizada. El historial se conserva.');
    }
}
