<?php

namespace App\Actions\Attendance;

use App\Enums\Role;
use App\Enums\SessionState;
use App\Exceptions\RegistrationRejected;
use App\Models\AttendancePortal;
use App\Models\ClassSession;
use App\Models\NfcCredential;
use App\Models\User;
use App\Services\Attendance\GeographicAccess;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

final class PhysicalTerminalWorkflow
{
    public function __construct(
        private AcademicSessionLock $locks,
        private PortalManager $portals,
        private CredentialManager $credentials,
        private RegisterAttendance $register,
        private GeographicAccess $geography,
    ) {}

    public function provision(User $actor, array $data, ?string $terminalId = null): array
    {
        return DB::transaction(function () use ($actor, $data, $terminalId): array {
            $id = $terminalId ?? (string) Str::uuid();
            $secret = bin2hex(random_bytes(32));
            if ($terminalId === null) {
                DB::table('physical_terminals')->insert([
                    'id' => $id, 'name' => $data['name'], 'secret_hash' => hash('sha256', $secret),
                    'capabilities' => json_encode($data['capabilities'], JSON_THROW_ON_ERROR), 'created_by' => $actor->id,
                ]);
                $this->audit($id, $actor, 'created');
            } else {
                $this->terminal($id, allowDisabled: true);
                $this->releaseResources($id, $actor, $data['reason']);
                DB::table('physical_terminals')->where('id', $id)->update([
                    'secret_hash' => hash('sha256', $secret), 'enabled' => true, 'last_seen_at' => null,
                ]);
                $this->audit($id, $actor, 'rotated', reason: $data['reason']);
            }

            return ['id' => $id, 'secret' => $secret];
        });
    }

    public function disable(User $actor, string $id, string $reason): void
    {
        DB::transaction(function () use ($actor, $id, $reason): void {
            $this->terminal($id, allowDisabled: true);
            $this->releaseResources($id, $actor, $reason);
            DB::table('physical_terminals')->where('id', $id)->update(['enabled' => false]);
            $this->audit($id, $actor, 'disabled', reason: $reason);
        });
    }

    public function assign(User $actor, ClassSession $session, array $data): void
    {
        DB::transaction(function () use ($actor, $session, $data): void {
            $terminal = $this->terminal($data['terminal_id']);
            $session = $this->locks->get($session);
            Gate::forUser($actor)->authorize('update', $session);
            $portal = AttendancePortal::lockForUpdate()->findOrFail($data['portal_id']);
            $methods = $data['methods'];
            $this->available($terminal, $methods);
            abort_unless($portal->session_id === $session->id && $this->portalOpen($portal, $session)
                && ! array_diff($methods, $portal->enabled_methods), 409);
            abort_if($this->activeAssignment($terminal->id) || $this->pendingCommand($terminal->id), 409);
            DB::table('terminal_assignments')->where('terminal_id', $terminal->id)->whereNull('released_at')->update(['released_at' => now()]);

            $reader = in_array('nfc', $methods, true) ? $this->portals->reader($actor, $portal, $terminal->name) : null;
            $id = (string) Str::uuid();
            DB::table('terminal_assignments')->insert([
                'id' => $id, 'terminal_id' => $terminal->id, 'portal_id' => $portal->id,
                'session_id' => $session->id, 'operator_id' => $actor->id, 'reader_id' => $reader?->id,
                'methods' => json_encode($methods, JSON_THROW_ON_ERROR),
                'assigned_at' => now(),
                'expires_at' => min($portal->expires_at, $session->ends_at, $reader?->expires_at ?? $portal->expires_at),
            ]);
            $this->audit($terminal->id, $actor, 'assigned', $id);
        });
    }

    public function release(User $actor, ClassSession $session, string $assignmentId): void
    {
        DB::transaction(function () use ($actor, $session, $assignmentId): void {
            $assignment = $this->assignment($assignmentId);
            $this->terminal($assignment->terminal_id, allowDisabled: true);
            Gate::forUser($actor)->authorize('update', $session->fresh());
            abort_unless($assignment->session_id === $session->id, 404);
            if (DB::table('terminal_assignments')->where('id', $assignmentId)->whereNull('released_at')->update(['released_at' => now()])) {
                DB::table('terminal_qr_challenges')->where('assignment_id', $assignmentId)->update(['delivery' => null]);
                $this->audit($assignment->terminal_id, $actor, 'released', $assignmentId);
            }
        });
    }

    public function issue(User $actor, User $student, string $terminalId): string
    {
        return DB::transaction(function () use ($actor, $student, $terminalId): string {
            $terminal = $this->terminal($terminalId);
            $this->available($terminal, ['write']);
            abort_if($this->activeAssignment($terminalId) || $this->pendingCommand($terminalId), 409);
            $issued = $this->credentials->issue($actor, $student);
            $id = (string) Str::uuid();
            DB::table('terminal_commands')->insert([
                'id' => $id, 'terminal_id' => $terminalId, 'operator_id' => $actor->id,
                'credential_id' => $issued['credential']->id, 'state' => 'pending',
                'delivery' => Crypt::encryptString($issued['token']),
                'expires_at' => $issued['credential']->pending_expires_at,
            ]);
            $this->audit($terminalId, $actor, 'write_queued', $id);

            return $id;
        });
    }

    public function cancel(User $actor, string $commandId, string $reason): void
    {
        DB::transaction(function () use ($actor, $commandId, $reason): void {
            $command = $this->command($commandId);
            $this->terminal($command->terminal_id, allowDisabled: true);
            $this->discard($this->command($commandId), $actor, 'cancelled', $reason);
        });
    }

    public function terminal(string $id, ?string $secretHash = null, bool $allowDisabled = false): object
    {
        $terminal = DB::table('physical_terminals')->where('id', $id)->lockForUpdate()->first();
        abort_unless($terminal, 404);
        if ($secretHash !== null) {
            abort_unless($terminal->enabled && hash_equals($terminal->secret_hash, $secretHash), 401);
        } else {
            abort_unless($allowDisabled || $terminal->enabled, 409);
        }

        return $terminal;
    }

    public function state(object $terminal): array
    {
        $assignment = $this->activeAssignment($terminal->id);
        $command = $this->pendingCommand($terminal->id);

        return [
            'server_time' => now()->toISOString(),
            'mode' => $command ? 'write' : ($assignment ? 'attendance' : 'idle'),
            'assignment' => $assignment ? [
                'id' => $assignment->id, 'portal_id' => $assignment->portal_id,
                'methods' => json_decode($assignment->methods, true),
                'expires_at' => Carbon::parse($assignment->expires_at)->toISOString(),
                'challenge' => $this->challengeResponse($this->latestChallenge($assignment->id), $assignment),
            ] : null,
        ];
    }

    public function claim(object $terminal): ?array
    {
        $command = $this->pendingCommand($terminal->id);
        if ($command === null) {
            return null;
        }
        $actor = User::findOrFail($command->operator_id);
        abort_unless($actor->active && ! $actor->must_change_password && $actor->role === Role::Coordinator, 403);
        if (! $command->lease_until || Carbon::parse($command->lease_until) <= now()) {
            $command->lease_id = (string) Str::uuid();
            $command->lease_until = min(now()->addSeconds(config('physical-terminal.write_lease_seconds')), Carbon::parse($command->expires_at));
            DB::table('terminal_commands')->where('id', $command->id)->update([
                'lease_id' => $command->lease_id, 'lease_until' => $command->lease_until,
            ]);
        }

        return [
            'id' => $command->id, 'lease_id' => $command->lease_id,
            'lease_until' => Carbon::parse($command->lease_until)->toISOString(),
            'server_time' => now()->toISOString(),
            'credential_token' => Crypt::decryptString($command->delivery),
        ];
    }

    public function complete(object $terminal, string $id, array $data, bool $confirm): array
    {
        $command = $this->command($id);
        abort_unless($command->terminal_id === $terminal->id, 404);
        abort_unless($command->lease_id === strtolower($data['lease_id']), 409);
        $credential = NfcCredential::findOrFail($command->credential_id);
        $actor = User::findOrFail($command->operator_id);
        abort_unless($actor->active && ! $actor->must_change_password && $actor->role === Role::Coordinator, 403);

        if ($confirm && $command->state === 'confirmed') {
            $this->credentials->confirm($actor, $credential, $data['readback']);

            return ['status' => 'confirmed', 'replayed' => true];
        }
        if (! $confirm && $command->state === $data['failure']) {
            return ['status' => $command->state, 'replayed' => true];
        }
        abort_unless($command->state === 'pending' && Carbon::parse($command->expires_at) > now()
            && Carbon::parse($command->lease_until) > now(), 409);

        if ($confirm) {
            $this->credentials->confirm($actor, $credential, $data['readback']);
            DB::table('terminal_commands')->where('id', $id)->update(['state' => 'confirmed', 'delivery' => null]);
            $this->audit($terminal->id, $actor, 'write_confirmed', $id);

            return ['status' => 'confirmed'];
        }
        $this->discard($command, $actor, $data['failure']);

        return ['status' => $data['failure']];
    }

    public function rotateChallenge(object $terminal, string $id): array
    {
        $assignment = $this->assignment($id);
        abort_unless($assignment->terminal_id === $terminal->id, 404);
        $session = $this->locks->get(ClassSession::findOrFail($assignment->session_id));
        $this->verifyAssignment($assignment, $session, 'qr');
        $latest = $this->latestChallenge($id);
        if ($latest && Carbon::parse($latest->issued_at)->addSeconds(config('physical-terminal.qr_rotation_seconds')) > now()) {
            return $this->challengeResponse($latest, $assignment) ?? abort(409);
        }

        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $token = '';
        for ($index = 0; $index < 20; $index++) {
            $token .= $alphabet[random_int(0, 31)];
        }
        $latest = (object) [
            'id' => (string) Str::uuid(), 'assignment_id' => $id,
            'token_hash' => hash('sha256', $token), 'delivery' => Crypt::encryptString($token),
            'issued_at' => now(), 'expires_at' => min(now()->addSeconds(config('physical-terminal.qr_lifetime_seconds')), Carbon::parse($assignment->expires_at)),
        ];
        $retain = DB::table('terminal_qr_challenges')->where('assignment_id', $id)->orderByDesc('issued_at')->value('id');
        DB::table('terminal_qr_challenges')->where('assignment_id', $id)->whereNotNull('delivery')
            ->when($retain, fn ($query) => $query->where('id', '!=', $retain))
            ->update(['expires_at' => now(), 'delivery' => null]);
        DB::table('terminal_qr_challenges')->insert((array) $latest);

        return $this->challengeResponse($latest, $assignment) ?? abort(409);
    }

    public function recordNfc(object $terminal, array $data): array
    {
        $assignment = $this->assignment($data['assignment_id']);
        abort_unless($assignment->terminal_id === $terminal->id && $assignment->reader_id, 404);
        $session = ClassSession::findOrFail($assignment->session_id);
        $actor = $this->operator($assignment, $session);

        $result = $this->register->recordFromTerminal($actor, $session,
            ['event_id' => $data['event_id'], 'reader_session_id' => $assignment->reader_id, 'credential_token' => $data['credential_token']],
            new TerminalRegistration($assignment->id, null, null, fn () => $this->verifyAssignment($assignment, $session->fresh(), 'nfc')));

        return ['http' => $result['http'], 'body' => array_intersect_key($result['body'], array_flip(['status', 'message', 'code', 'replayed']))];
    }

    public function resolve(User $student, string $token): array
    {
        $challenge = $this->findChallenge($token);
        $assignment = $this->assignment($challenge->assignment_id);
        $session = ClassSession::findOrFail($assignment->session_id);
        Gate::forUser($student)->authorize('view', $session);
        $day = $session->starts_at->timezone(config('smartaulaues.display_timezone'))->toDateString();
        abort_unless($session->offering->enrollments()->where('student_id', $student->id)
            ->where('active_from', '<=', $day)->where(fn ($query) => $query->whereNull('active_to')->orWhere('active_to', '>=', $day))->exists(), 403);
        abort_unless(DB::table('physical_terminals')->where('id', $assignment->terminal_id)->where('enabled', true)->exists(), 409);
        $this->checkQr($challenge, $assignment, $session);

        return [
            'challenge_id' => $challenge->id, 'portal_id' => $assignment->portal_id,
            'subject' => $session->offering->subject->name, 'expires_at' => Carbon::parse($challenge->expires_at)->toISOString(),
            'requires_location' => $this->geography->requiresLocation($session),
        ];
    }

    public function recordQr(User $student, array $data): array
    {
        return DB::transaction(function () use ($student, $data): array {
            DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', [strtolower($data['event_id'])]);
            $challenge = $this->findChallenge($data['short_token']);
            $assignment = $this->assignment($challenge->assignment_id);
            $this->terminal($assignment->terminal_id);
            $assignment = $this->assignment($challenge->assignment_id);
            abort_unless($challenge->id === strtolower($data['challenge_id']) && $assignment->portal_id === (int) $data['portal_id'], 409);
            $session = ClassSession::findOrFail($assignment->session_id);
            $this->operator($assignment, $session);

            return $this->register->recordFromTerminal($student, $session,
                ['event_id' => $data['event_id'], 'portal_id' => $assignment->portal_id, ...array_intersect_key($data, ['location' => true])],
                new TerminalRegistration($assignment->id, $challenge->id, $challenge->token_hash, function () use ($challenge, $assignment, $session): void {
                    $current = DB::table('terminal_qr_challenges')->where('id', $challenge->id)->first();
                    $this->checkQr($current, $assignment, $session->fresh());
                }));
        });
    }

    private function checkQr(object $challenge, object $assignment, ClassSession $session): void
    {
        if (Carbon::parse($challenge->expires_at) <= now()) {
            throw new RegistrationRejected('El QR de la terminal venció. Escanea el código vigente.', 409, 'terminal_qr_expired');
        }
        $this->verifyAssignment($assignment, $session, 'qr');
    }

    private function verifyAssignment(object $assignment, ClassSession $session, string $method): void
    {
        $this->operator($assignment, $session);
        $portal = AttendancePortal::findOrFail($assignment->portal_id);
        if ($assignment->released_at || Carbon::parse($assignment->expires_at) <= now() || ! $this->portalOpen($portal, $session)
            || ! in_array($method, json_decode($assignment->methods, true), true) || ! in_array($method, $portal->enabled_methods, true)) {
            throw new RegistrationRejected('La asignación de la terminal ya no está vigente.', 409, 'terminal_assignment_closed');
        }
    }

    private function operator(object $assignment, ClassSession $session): User
    {
        $actor = User::findOrFail($assignment->operator_id);
        abort_unless($actor->active && ! $actor->must_change_password, 403);
        Gate::forUser($actor)->authorize('update', $session);

        return $actor;
    }

    private function portalOpen(AttendancePortal $portal, ClassSession $session): bool
    {
        return $session->state === SessionState::InProgress && $session->starts_at <= now() && $session->ends_at > now()
            && $session->offering->active && $session->offering->cycle->status === 'active' && $session->period->state === 'open'
            && ! $portal->closed_at && $portal->opens_at <= now() && $portal->expires_at > now();
    }

    private function activeAssignment(string $id): ?object
    {
        $assignments = DB::table('terminal_assignments')->where('terminal_id', $id)->whereNull('released_at')
            ->where('expires_at', '>', now())->get();
        foreach ($assignments as $assignment) {
            $portal = AttendancePortal::findOrFail($assignment->portal_id);
            if ($this->portalOpen($portal, $portal->session)) {
                return $assignment;
            }
        }

        return null;
    }

    private function pendingCommand(string $id): ?object
    {
        return DB::table('terminal_commands')->join('nfc_credentials', 'nfc_credentials.id', '=', 'terminal_commands.credential_id')
            ->where('terminal_id', $id)->where('terminal_commands.state', 'pending')->where('nfc_credentials.state', 'pending')
            ->where('expires_at', '>', now())->first(['terminal_commands.*']);
    }

    private function available(object $terminal, array $capabilities): void
    {
        abort_unless($terminal->last_seen_at && Carbon::parse($terminal->last_seen_at)->addSeconds(config('physical-terminal.offline_seconds')) > now()
            && ! array_diff($capabilities, json_decode($terminal->capabilities, true)), 409);
    }

    private function assignment(string $id): object
    {
        return DB::table('terminal_assignments')->where('id', $id)->first() ?? abort(404);
    }

    private function command(string $id): object
    {
        return DB::table('terminal_commands')->where('id', $id)->first() ?? abort(404);
    }

    private function latestChallenge(string $id): ?object
    {
        return DB::table('terminal_qr_challenges')->where('assignment_id', $id)->orderByDesc('issued_at')->first();
    }

    private function findChallenge(string $token): object
    {
        return DB::table('terminal_qr_challenges')->where('token_hash', hash('sha256', $token))->first() ?? abort(404);
    }

    private function challengeResponse(?object $challenge, object $assignment): ?array
    {
        if (! $challenge || ! $challenge->delivery || Carbon::parse($challenge->expires_at) <= now()) {
            return null;
        }

        return [
            'challenge_id' => $challenge->id, 'assignment_id' => $assignment->id, 'portal_id' => $assignment->portal_id,
            'token' => Crypt::decryptString($challenge->delivery), 'server_time' => now()->toISOString(),
            'expires_at' => Carbon::parse($challenge->expires_at)->toISOString(),
            'assignment_expires_at' => Carbon::parse($assignment->expires_at)->toISOString(),
            'valid_for_seconds' => max(0, (int) now()->diffInSeconds(Carbon::parse($challenge->expires_at))),
            'qr' => ['version' => 1, 'mode' => 'alphanumeric', 'error_correction' => 'M', 'module_pixels' => 2,
                'quiet_zone_modules' => 4, 'symbol_modules' => 21, 'total_pixels' => 58],
        ];
    }

    private function discard(object $command, User $actor, string $state, ?string $reason = null): void
    {
        abort_unless($command->state === 'pending', 409);
        $credential = NfcCredential::findOrFail($command->credential_id);
        $reason ??= 'Trabajo de escritura NFC cancelado o fallido.';
        $this->credentials->revokePending($actor, $credential, $reason);
        DB::table('terminal_commands')->where('id', $command->id)->update(['state' => $state, 'delivery' => null]);
        $this->audit($command->terminal_id, $actor, 'write_'.$state, $command->id, $reason);
    }

    private function releaseResources(string $id, User $actor, string $reason): void
    {
        DB::table('terminal_assignments')->where('terminal_id', $id)->whereNull('released_at')->update(['released_at' => now()]);
        DB::table('terminal_qr_challenges')->whereIn('assignment_id', DB::table('terminal_assignments')->where('terminal_id', $id)->select('id'))
            ->whereNotNull('delivery')->update(['delivery' => null]);
        foreach (DB::table('terminal_commands')->where('terminal_id', $id)->where('state', 'pending')->get() as $command) {
            $this->discard($command, $actor, 'cancelled', $reason);
        }
    }

    private function audit(string $id, User $actor, string $action, ?string $resource = null, ?string $reason = null): void
    {
        DB::table('terminal_operation_logs')->insert([
            'terminal_id' => $id, 'actor_id' => $actor->id, 'action' => $action,
            'resource_id' => $resource, 'reason' => $reason, 'occurred_at' => now(),
        ]);
    }
}
