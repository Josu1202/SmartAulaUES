<?php

namespace App\Http\Controllers;

use App\Actions\Attendance\AbsenceWorkflow;
use App\Models\AbsenceRequest;
use App\Models\ClassSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AbsenceRequestController extends Controller
{
    public function index(Request $request): View
    {
        $data = $request->validate(['status' => ['nullable', 'in:pending,approved,rejected']]);
        $requests = AbsenceRequest::whereHas('enrollment', fn ($q) => $q->visibleTo($request->user()))->with('session', 'offering.subject', 'enrollment.student')->when($data['status'] ?? null, fn ($q, $status) => $q->where('status', $status))->latest('id')->paginate(15)->withQueryString();

        return view('attendance.requests.index', compact('requests'));
    }

    public function create(ClassSession $session): View
    {
        Gate::authorize('create', [AbsenceRequest::class, $session]);

        return view('attendance.requests.form', ['session' => $session->load('offering.subject')]);
    }

    public function store(Request $request, ClassSession $session, AbsenceWorkflow $workflow): RedirectResponse
    {
        Gate::authorize('create', [AbsenceRequest::class, $session]);
        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:2000'], 'evidence' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:'.config('attendance.evidence_max_kilobytes')]]);
        $record = $workflow->submit($request->user(), $session, $data['reason'], $data['evidence'] ?? null);

        return to_route('requests.show', $record)->with('status', 'Solicitud enviada al docente.');
    }

    public function show(AbsenceRequest $absenceRequest): View
    {
        Gate::authorize('view', $absenceRequest);

        return view('attendance.requests.show', ['record' => $absenceRequest->load('session', 'offering.subject', 'enrollment.student', 'resolver')]);
    }

    public function resolve(Request $request, AbsenceRequest $absenceRequest, AbsenceWorkflow $workflow): RedirectResponse
    {
        Gate::authorize('resolve', $absenceRequest);
        $data = $request->validate(['decision' => ['required', 'in:approved,rejected'], 'note' => ['required', 'string', 'min:5', 'max:2000']]);
        $workflow->resolve($request->user(), $absenceRequest, $data['decision'], $data['note']);

        return to_route('requests.show', $absenceRequest)->with('status', 'Solicitud resuelta. El estudiante recibió un aviso interno.');
    }

    public function evidence(AbsenceRequest $absenceRequest): StreamedResponse
    {
        Gate::authorize('view', $absenceRequest);
        abort_unless($absenceRequest->evidence_path && Storage::disk('private')->exists($absenceRequest->evidence_path), 404);

        return Storage::disk('private')->download($absenceRequest->evidence_path, 'evidencia-permiso-'.$absenceRequest->id.'.'.pathinfo($absenceRequest->evidence_path, PATHINFO_EXTENSION));
    }
}
