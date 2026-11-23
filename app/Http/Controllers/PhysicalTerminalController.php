<?php

namespace App\Http\Controllers;

use App\Actions\Attendance\PhysicalTerminalWorkflow;
use App\Models\ClassSession;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

final class PhysicalTerminalController extends Controller
{
    public function index(): View
    {
        return view('terminal.devices', [
            'terminals' => DB::table('physical_terminals')->select('id', 'name', 'enabled', 'capabilities', 'last_seen_at', 'firmware')->orderBy('name')->paginate(20),
            'operations' => DB::table('terminal_operation_logs')->join('physical_terminals', 'physical_terminals.id', '=', 'terminal_operation_logs.terminal_id')
                ->join('users', 'users.id', '=', 'terminal_operation_logs.actor_id')
                ->orderByDesc('terminal_operation_logs.id')->limit(15)
                ->get(['physical_terminals.name', 'users.name as actor', 'action', 'occurred_at']),
        ]);
    }

    public function provision(Request $request, PhysicalTerminalWorkflow $workflow, ?string $terminal = null): View
    {
        $data = $request->validate($terminal === null ? [
            'name' => ['required', 'string', 'max:80'],
            'capabilities' => ['required', 'array', 'min:1', 'max:3'],
            'capabilities.*' => ['required', 'distinct', 'in:nfc,write,qr'],
        ] : ['reason' => ['required', 'string', 'min:5', 'max:255']]);

        return view('terminal.provision', $workflow->provision($request->user(), $data, $terminal));
    }

    public function disable(Request $request, PhysicalTerminalWorkflow $workflow, string $terminal): RedirectResponse
    {
        $workflow->disable($request->user(), $terminal, $this->reason($request));

        return to_route('admin.physical-terminals.index')->with('status', 'Acceso de la terminal revocado.');
    }

    public function assignment(ClassSession $session): View
    {
        Gate::authorize('update', $session);

        return view('terminal.assign', [
            'session' => $session,
            'portals' => $session->portals()->whereNull('closed_at')->where('expires_at', '>', now())->get(),
            'terminals' => $this->availableTerminals()->get(['id', 'name', 'capabilities']),
            'assignments' => DB::table('terminal_assignments')->join('physical_terminals', 'physical_terminals.id', '=', 'terminal_assignments.terminal_id')
                ->where('session_id', $session->id)->whereNull('released_at')->where('expires_at', '>', now())
                ->get(['terminal_assignments.id', 'physical_terminals.name', 'expires_at']),
        ]);
    }

    public function assign(Request $request, ClassSession $session, PhysicalTerminalWorkflow $workflow): RedirectResponse
    {
        Gate::authorize('update', $session);
        $data = $request->validate([
            'terminal_id' => ['required', 'uuid'], 'portal_id' => ['required', 'integer'],
            'methods' => ['required', 'array', 'min:1', 'max:2'], 'methods.*' => ['required', 'distinct', 'in:nfc,qr'],
        ]);
        $workflow->assign($request->user(), $session, $data);

        return back()->with('status', 'Terminal vinculada al portal.');
    }

    public function release(Request $request, ClassSession $session, string $assignment, PhysicalTerminalWorkflow $workflow): RedirectResponse
    {
        Gate::authorize('update', $session);
        $workflow->release($request->user(), $session, $assignment);

        return back()->with('status', 'Asignación liberada.');
    }

    public function jobs(Request $request): View
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:80']]);
        $search = $data['q'] ?? '';
        $students = User::query()->where('role', 'student')->where('active', true)
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query->where('name', 'ilike', '%'.$search.'%')
                ->orWhere('student_code', 'ilike', '%'.$search.'%')))
            ->orderBy('name')->paginate(25, ['id', 'name', 'student_code'], 'students')->withQueryString();

        return view('terminal.jobs', [
            'students' => $students,
            'terminals' => $this->availableTerminals()->whereRaw("capabilities @> '[\"write\"]'::jsonb")->get(['id', 'name']),
            'jobs' => DB::table('terminal_commands')->join('physical_terminals', 'physical_terminals.id', '=', 'terminal_commands.terminal_id')
                ->join('nfc_credentials', 'nfc_credentials.id', '=', 'terminal_commands.credential_id')
                ->join('users', 'users.id', '=', 'nfc_credentials.user_id')
                ->orderByDesc('terminal_commands.expires_at')->paginate(15, [
                    'terminal_commands.id', 'terminal_commands.state', 'terminal_commands.lease_until', 'terminal_commands.expires_at',
                    'physical_terminals.name as terminal', 'users.name as student', 'nfc_credentials.state as credential_state',
                ], 'jobs')->withQueryString(),
        ]);
    }

    public function issue(Request $request, PhysicalTerminalWorkflow $workflow): RedirectResponse
    {
        $data = $request->validate(['terminal_id' => ['required', 'uuid'], 'student_id' => ['required', 'integer']]);
        $workflow->issue($request->user(), User::findOrFail($data['student_id']), $data['terminal_id']);

        return back()->with('status', 'Trabajo de escritura preparado.');
    }

    public function cancel(Request $request, PhysicalTerminalWorkflow $workflow, string $command): RedirectResponse
    {
        $workflow->cancel($request->user(), $command, $this->reason($request));

        return back()->with('status', 'Trabajo cancelado. Puedes preparar una nueva emisión.');
    }

    private function availableTerminals(): Builder
    {
        return DB::table('physical_terminals')->where('enabled', true)
            ->where('last_seen_at', '>', now()->subSeconds(config('physical-terminal.offline_seconds')))->orderBy('name');
    }

    private function reason(Request $request): string
    {
        return $request->validate(['reason' => ['required', 'string', 'min:5', 'max:255']])['reason'];
    }
}
