<?php

namespace App\Actions\Attendance;

use App\Actions\Academic\AuditRecorder;
use App\Enums\Role;
use App\Models\NfcCredential;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CredentialManager
{
    public function __construct(private AuditRecorder $audit) {}

    public function issue(User $actor, User $student): array
    {
        Gate::forUser($actor)->authorize('create', NfcCredential::class);

        return DB::transaction(function () use ($actor, $student): array {
            $student = User::lockForUpdate()->findOrFail($student->id);
            if (! $student->active || $student->role !== Role::Student) {
                $this->fail('student_id', 'Selecciona un estudiante activo.');
            }
            foreach ($student->nfcCredentials()->where('state', 'pending')->get() as $old) {
                $before = $this->audit->snapshot($old);
                $old->update(['state' => 'revoked', 'revoked_at' => now()]);
                $this->audit->record($actor, $old, 'credential.revoked', $before, 'Alta pendiente reemplazada.');
            }
            $token = 'SIAA:C1:'.rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
            $credential = $student->nfcCredentials()->create(['token_hash' => hash('sha256', $token), 'state' => 'pending', 'pending_expires_at' => now()->addMinutes(config('attendance.credential_pending_minutes'))]);
            $this->audit->record($actor, $credential, 'credential.issued');

            return ['credential' => $credential, 'token' => $token];
        });
    }

    public function confirm(User $actor, NfcCredential $credential, string $token): void
    {
        Gate::forUser($actor)->authorize('update', $credential);
        DB::transaction(function () use ($actor, $credential, $token): void {
            $student = User::lockForUpdate()->findOrFail($credential->user_id);
            $credential = NfcCredential::lockForUpdate()->findOrFail($credential->id);
            // Lost successful responses are safe to retry; revoked replacements never reactivate.
            if ($student->active && $student->role === Role::Student && $credential->state === 'active'
                && hash_equals($credential->token_hash, hash('sha256', $token))) {
                return;
            }
            if (! $student->active || $student->role !== Role::Student || $credential->state !== 'pending' || $credential->pending_expires_at <= now() || ! hash_equals($credential->token_hash, hash('sha256', $token))) {
                $this->fail('credential_token', 'La etiqueta no coincide, el alta venció o la cuenta está inactiva.');
            }
            foreach ($student->nfcCredentials()->where('state', 'active')->get() as $old) {
                $before = $this->audit->snapshot($old);
                $old->update(['state' => 'revoked', 'revoked_at' => now()]);
                $this->audit->record($actor, $old, 'credential.revoked', $before, 'Etiqueta reemplazada al confirmar otra.');
            }
            $before = $this->audit->snapshot($credential);
            $credential->update(['state' => 'active', 'activated_at' => now(), 'pending_expires_at' => null]);
            $this->audit->record($actor, $credential, 'credential.activated', $before);
        });
    }

    public function revoke(User $actor, NfcCredential $credential, string $reason): void
    {
        Gate::forUser($actor)->authorize('update', $credential);
        DB::transaction(function () use ($actor, $credential, $reason): void {
            User::lockForUpdate()->findOrFail($credential->user_id);
            $credential = NfcCredential::lockForUpdate()->findOrFail($credential->id);
            $before = $this->audit->snapshot($credential);
            $credential->update(['state' => 'revoked', 'revoked_at' => now()]);
            $this->audit->record($actor, $credential, 'credential.revoked', $before, $reason);
        });
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
