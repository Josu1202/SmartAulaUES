<?php

namespace App\Actions\Academic;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UserManager
{
    public function __construct(private AuditRecorder $audit) {}

    public function save(User $actor, array $data, ?User $user = null): User
    {
        Gate::forUser($actor)->authorize($user ? 'update' : 'create', $user ?? User::class);

        return DB::transaction(function () use ($actor, $data, $user): User {
            // Serialize changes to the coordinator set to preserve at least one active account.
            User::where('role', Role::Coordinator->value)->orderBy('id')->lockForUpdate()->get();
            $record = $user ? User::lockForUpdate()->findOrFail($user->id) : new User;
            $before = $record->exists ? $this->audit->snapshot($record) : null;
            $email = mb_strtolower(trim($data['email']));
            if (User::whereRaw('lower(email) = ?', [$email])->when($record->exists, fn ($q) => $q->whereKeyNot($record->id))->exists()) {
                $this->fail('email', 'Este correo ya pertenece a otra cuenta.');
            }
            $role = Role::from($data['role']);
            $active = (bool) $data['active'];
            if ($record->exists && $record->id === $actor->id && (! $active || $role !== Role::Coordinator)) {
                $this->fail('role', 'No puedes desactivar tu propia cuenta ni quitarte el perfil de coordinación.');
            }
            if ($record->exists && $record->role !== $role && ($record->taughtOfferings()->exists() || $record->enrollments()->exists() || $record->nfcCredentials()->exists())) {
                $this->fail('role', 'Esta cuenta tiene historial académico. Conserva su perfil; puedes desactivarla.');
            }
            if ($record->exists && $record->role === Role::Coordinator && $record->active && (! $active || $role !== Role::Coordinator)
                && User::where('role', Role::Coordinator->value)->where('active', true)->count() <= 1) {
                $this->fail('active', 'Debe quedar al menos una cuenta activa de coordinación.');
            }
            $studentCode = $role === Role::Student ? mb_strtoupper(trim($data['student_code'])) : null;
            if ($studentCode && User::where('student_code', $studentCode)->when($record->exists, fn ($q) => $q->whereKeyNot($record->id))->exists()) {
                $this->fail('student_code', 'Este carné ya pertenece a otra cuenta.');
            }
            if ($record->exists && ($record->role !== $role || $record->active !== $active) && blank($data['reason'] ?? null)) {
                $this->fail('reason', 'Indica el motivo del cambio de perfil o estado.');
            }
            $record->fill(['name' => $data['name'], 'email' => $email, 'role' => $role, 'student_code' => $studentCode, 'active' => $active]);
            if (! $record->exists) {
                $record->password = $data['password'];
            }
            $record->save();
            if (! $active) {
                $this->revokeSessions($record);
            }
            $this->audit->record($actor, $record, $before ? 'user.updated' : 'user.created', $before, $data['reason'] ?? null);

            return $record;
        });
    }

    public function resetPassword(User $actor, User $user, string $password, string $reason): void
    {
        Gate::forUser($actor)->authorize('update', $user);
        DB::transaction(function () use ($actor, $user, $password, $reason): void {
            $user = User::lockForUpdate()->findOrFail($user->id);
            $user->password = $password;
            $user->save();
            $this->revokeSessions($user);
            $this->audit->record($actor, $user, 'user.password_reset', reason: $reason);
        });
    }

    public function changeOwnPassword(User $user, string $password): void
    {
        DB::transaction(function () use ($user, $password): void {
            $user->password = $password;
            $user->save();
            $this->revokeSessions($user);
            $this->audit->record($user, $user, 'user.password_changed');
        });
    }

    private function revokeSessions(User $user): void
    {
        $user->forceFill(['remember_token' => Str::random(60)])->save();
        if (config('session.driver') === 'database') {
            DB::connection(config('session.connection'))->table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        }
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
