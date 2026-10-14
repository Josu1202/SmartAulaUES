<?php

namespace App\Actions\Attendance;

use App\Actions\Academic\AuditRecorder;
use App\Enums\Role;
use App\Enums\SessionState;
use App\Models\AttendancePortal;
use App\Models\ClassSession;
use App\Models\ReaderSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PortalManager
{
    public function __construct(private AcademicSessionLock $locks, private SessionWorkflow $sessions, private AuditRecorder $audit) {}

    public function open(User $actor, ClassSession $session, array $methods, int $minutes): array
    {
        return DB::transaction(function () use ($actor, $session, $methods, $minutes): array {
            $record = $this->locks->get($session);
            Gate::forUser($actor)->authorize('update', $record);
            if ($record->state !== SessionState::InProgress || $record->ends_at <= now() || $record->starts_at > now()) {
                $this->fail('session', 'La sesión debe estar en curso y dentro de su horario.');
            }
            $this->sessions->closePortals($record);
            $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
            $portal = $record->portals()->create(['opens_at' => now(), 'expires_at' => min(now()->addMinutes($minutes), $record->ends_at), 'enabled_methods' => array_values(array_unique($methods)), 'token_hash' => hash('sha256', $token)]);
            $this->audit->record($actor, $portal, 'portal.opened');

            return ['portal' => $portal, 'token' => $token];
        });
    }

    public function close(User $actor, AttendancePortal $portal): void
    {
        DB::transaction(function () use ($actor, $portal): void {
            $session = $this->locks->get($portal->session);
            Gate::forUser($actor)->authorize('update', $session);
            $portal = AttendancePortal::lockForUpdate()->findOrFail($portal->id);
            $before = $this->audit->snapshot($portal);
            $portal->update(['closed_at' => now()]);
            $portal->readerSessions()->update(['state' => 'closed']);
            $this->audit->record($actor, $portal, 'portal.closed', $before);
        });
    }

    public function reader(User $actor, AttendancePortal $portal, string $label): ReaderSession
    {
        return DB::transaction(function () use ($actor, $portal, $label): ReaderSession {
            $session = $this->locks->get($portal->session);
            Gate::forUser($actor)->authorize('update', $session);
            $portal = AttendancePortal::lockForUpdate()->findOrFail($portal->id);
            if ($portal->closed_at || $portal->opens_at > now() || $portal->expires_at <= now() || ! in_array('nfc', $portal->enabled_methods) || $session->state !== SessionState::InProgress) {
                $this->fail('portal_id', 'El portal NFC no está vigente.');
            }
            $reader = $portal->readerSessions()->create(['id' => (string) Str::uuid(), 'teacher_id' => $actor->id, 'label' => $label, 'expires_at' => $portal->expires_at, 'state' => 'active']);
            $this->audit->record($actor, $reader, 'reader.opened');

            return $reader;
        });
    }

    public function closeReader(User $actor, ReaderSession $reader): void
    {
        DB::transaction(function () use ($actor, $reader): void {
            $session = $this->locks->get($reader->portal->session);
            Gate::forUser($actor)->authorize('update', $session);
            abort_unless($reader->teacher_id === $actor->id || $actor->role === Role::Coordinator, 403);
            $reader = ReaderSession::lockForUpdate()->findOrFail($reader->id);
            $reader->update(['state' => 'closed']);
            $this->audit->record($actor, $reader, 'reader.closed');
        });
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
