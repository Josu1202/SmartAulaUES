<?php

namespace App\Http\Controllers;

use App\Actions\Attendance\SessionWorkflow;
use App\Http\Requests\SaveSessionRequest;
use App\Models\ClassSession;
use App\Models\Offering;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SessionController extends Controller
{
    public function index(Request $request): View
    {
        $data = $request->validate(['offering_id' => ['nullable', 'integer'], 'state' => ['nullable', 'in:scheduled,in_progress,completed,cancelled']]);
        $sessions = ClassSession::whereHas('offering', fn ($q) => $q->visibleTo($request->user()))->with('offering.subject', 'offering.cycle', 'period')->when($data['offering_id'] ?? null, fn ($q, $id) => $q->where('offering_id', $id))->when($data['state'] ?? null, fn ($q, $state) => $q->where('state', $state))->orderByDesc('starts_at')->paginate(15)->withQueryString();

        return view('academic.sessions.index', ['sessions' => $sessions, 'offerings' => Offering::visibleTo($request->user())->with('subject')->get()]);
    }

    public function create(Offering $offering): View
    {
        Gate::authorize('create', [ClassSession::class, $offering]);

        return $this->form($offering, new ClassSession(['modality' => 'in_person']));
    }

    public function store(SaveSessionRequest $request, Offering $offering, SessionWorkflow $workflow): RedirectResponse
    {
        $session = $workflow->save($request->user(), $offering, $request->validated());

        return to_route('sessions.show', $session)->with('status', 'Sesión programada.');
    }

    public function edit(Offering $offering, ClassSession $session): View
    {
        Gate::authorize('update', $session);

        return $this->form($offering, $session);
    }

    public function update(SaveSessionRequest $request, Offering $offering, ClassSession $session, SessionWorkflow $workflow): RedirectResponse
    {
        $workflow->save($request->user(), $offering, $request->validated(), $session);

        return to_route('sessions.show', $session)->with('status', 'Sesión actualizada. Los inscritos recibieron un aviso interno.');
    }

    public function show(Request $request, ClassSession $session): View
    {
        Gate::authorize('view', $session);
        $session->load('offering.subject', 'offering.cycle', 'period');
        $date = $session->starts_at->timezone(config('smartaulaues.display_timezone'))->toDateString();
        $enrollments = $session->offering->enrollments()->visibleTo($request->user())->with('student')->where('active_from', '<=', $date)->where(fn ($q) => $q->whereNull('active_to')->orWhere('active_to', '>=', $date))->orderBy('id')->paginate(25);
        $attendances = $session->attendances()->whereIn('enrollment_id', $enrollments->modelKeys())->get()->keyBy('enrollment_id');
        $requests = $session->absenceRequests()->whereIn('enrollment_id', $enrollments->modelKeys())->get()->keyBy('enrollment_id');
        $portal = $session->portals()->whereNull('closed_at')->where('expires_at', '>', now())->latest('id')->first();

        return view('academic.sessions.show', compact('session', 'enrollments', 'attendances', 'requests', 'portal'));
    }

    public function transition(Request $request, ClassSession $session, string $operation, SessionWorkflow $workflow): RedirectResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'min:5', 'max:1000']]);
        $workflow->transition($request->user(), $session, $operation, $data['reason'] ?? null);

        return to_route('sessions.show', $session)->with('status', 'Estado de la sesión actualizado.');
    }

    private function form(Offering $offering, ClassSession $session): View
    {
        return view('academic.sessions.form', ['offering' => $offering->load('subject', 'cycle'), 'session' => $session, 'periods' => $offering->periods()->where('state', 'open')->orderBy('number')->get()]);
    }
}
