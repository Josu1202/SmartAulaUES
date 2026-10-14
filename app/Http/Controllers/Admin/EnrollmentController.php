<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Academic\AcademicRegistry;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveEnrollmentRequest;
use App\Models\Enrollment;
use App\Models\Offering;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class EnrollmentController extends Controller
{
    public function create(Offering $offering): View
    {
        Gate::authorize('create', Enrollment::class);
        $students = User::where('role', Role::Student->value)->where('active', true)->whereDoesntHave('enrollments', fn ($q) => $q->where('offering_id', $offering->id))->orderBy('name')->get()->mapWithKeys(fn ($s) => [$s->id => $s->student_code.' · '.$s->name]);

        return view('admin.academic.form', ['kind' => 'enrollment', 'record' => new Enrollment(['active_from' => $offering->cycle->starts_on]), 'offering' => $offering->load('subject', 'cycle'), 'students' => $students]);
    }

    public function store(SaveEnrollmentRequest $request, Offering $offering, AcademicRegistry $registry): RedirectResponse
    {
        $registry->saveEnrollment($request->user(), $offering, $request->validated());

        return to_route('offerings.show', $offering)->with('status', 'Estudiante inscrito.');
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
