<?php

namespace App\Actions\Attendance;

use App\Actions\Academic\AuditRecorder;
use App\Enums\AttendanceStatus;
use App\Enums\Role;
use App\Enums\SessionState;
use App\Exceptions\RegistrationRejected;
use App\Models\Attendance;
use App\Models\AttendanceEvent;
use App\Models\AttendancePortal;
use App\Models\ClassSession;
use App\Models\NfcCredential;
use App\Models\ReaderSession;
use App\Models\User;
use App\Services\Attendance\GeographicAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class RegisterAttendance
{
    public function __construct(private AcademicSessionLock $locks, private AuditRecorder $audit, private GeographicAccess $geography) {}

    public function record(User $actor, ClassSession $session, string $method, array $data): array
    {
        // PostgreSQL UUIDs and integer IDs have a canonical identity across JSON and HTML inputs.
        $data['event_id'] = strtolower($data['event_id']);
        foreach (['student_id', 'portal_id'] as $field) {
            if (isset($data[$field])) {
                $data[$field] = (int) $data[$field];
            }
        }
        if (isset($data['reader_session_id'])) {
            $data['reader_session_id'] = strtolower($data['reader_session_id']);
        }
        $payload = ['actor' => $actor->id, 'session' => $session->id, 'method' => $method,
            'student' => $data['student_id'] ?? null, 'portal' => $data['portal_id'] ?? null, 'reader' => $data['reader_session_id'] ?? null,
            'credential' => isset($data['credential_token']) ? hash('sha256', $data['credential_token']) : null,
            'token' => isset($data['portal_token']) ? hash('sha256', $data['portal_token']) : null, 'reason' => $data['reason'] ?? null];
        if (array_key_exists('location', $data)) {
            $location = $data['location'];
            if (is_array($location)) {
                ksort($location);
            }
            // Hash only; serialize also safely fingerprints malformed/nonfinite direct action inputs.
            $payload['location'] = hash('sha256', serialize($location));
        }
        $hash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($actor, $session, $method, $data, $hash): array {
            // Global event lock precedes the parent locks, including calls targeting different cycles.
            DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', [$data['event_id']]);
            $session = ClassSession::with('offering')->findOrFail($session->id);
            if (in_array($method, ['qr', 'link'])) {
                abort_unless($actor->active && $actor->role === Role::Student, 403);
                Gate::forUser($actor)->authorize('view', $session);
            } else {
                Gate::forUser($actor)->authorize('update', $session);
            }
            $existing = AttendanceEvent::whereKey($data['event_id'])->first();
            if ($existing) {
                abort_unless($existing->actor_id === $actor->id && hash_equals($existing->request_hash, $hash), 409, 'Este identificador ya pertenece a otra solicitud.');

                return ['http' => $existing->http_status === 201 ? 200 : ($existing->http_status ?? 200), 'body' => [...($existing->response ?? ['status' => $existing->outcome]), 'replayed' => true]];
            }
            $session = $this->locks->get($session);
            $credential = null;
            $reader = null;
            $attendance = null;
            $geoValidation = null;
            try {
                if ($session->state !== SessionState::InProgress || $session->starts_at > now() || $session->ends_at <= now()) {
                    $this->reject('La sesión ya no admite registros.', 409);
                }
                if ($method === 'nfc') {
                    $reader = ReaderSession::lockForUpdate()->findOrFail($data['reader_session_id']);
                    $portal = AttendancePortal::lockForUpdate()->findOrFail($reader->portal_id);
                    if ($reader->teacher_id !== $actor->id) {
                        abort(403);
                    }
                    if ($reader->state !== 'active' || $reader->expires_at <= now()) {
                        $this->reject('La sesión del lector está cerrada o vencida.', 409);
                    }
                    $candidate = NfcCredential::where('token_hash', hash('sha256', $data['credential_token']))->first();
                    if (! $candidate) {
                        $this->reject('La credencial no está registrada.');
                    }
                    $student = User::lockForUpdate()->findOrFail($candidate->user_id);
                    $credential = NfcCredential::lockForUpdate()->findOrFail($candidate->id);
                    if ($credential->state !== 'active') {
                        $this->reject('La credencial está pendiente o revocada.');
                    }
                } else {
                    $portal = AttendancePortal::lockForUpdate()->findOrFail($data['portal_id']);
                    $student = User::lockForUpdate()->findOrFail(in_array($method, ['qr', 'link']) ? $actor->id : $data['student_id']);
                    if (in_array($method, ['qr', 'link']) && ! hash_equals($portal->token_hash, hash('sha256', $data['portal_token']))) {
                        $this->reject('El enlace no es válido.');
                    }
                }
                if ($portal->session_id !== $session->id) {
                    $this->reject('El portal pertenece a otra sesión.');
                }
                if ($portal->closed_at || $portal->opens_at > now() || $portal->expires_at <= now() || ! in_array($method, $portal->enabled_methods)) {
                    $this->reject('El método no está habilitado o el portal está cerrado/vencido.', 409);
                }
                if (! $student->active || $student->role !== Role::Student) {
                    $this->reject('El estudiante no tiene una cuenta activa.');
                }
                $date = $session->starts_at->timezone(config('smartaulaues.display_timezone'))->toDateString();
                $enrollment = $session->offering->enrollments()->where('student_id', $student->id)->where('active_from', '<=', $date)->where(fn ($q) => $q->whereNull('active_to')->orWhere('active_to', '>=', $date))->first();
                if (! $enrollment) {
                    $this->reject('El estudiante no tiene inscripción vigente en este grupo.');
                }
                if (in_array($method, ['qr', 'link'])) {
                    $geoValidation = $this->geography->validate($portal, $session, $data['location'] ?? null);
                }
                $attendance = Attendance::where('session_id', $session->id)->where('enrollment_id', $enrollment->id)->first();
                if ($attendance && $attendance->status !== AttendanceStatus::Present) {
                    $this->reject('Ya existe otro resultado para esta sesión. Solicita revisión del docente.', 409);
                }
                $status = $attendance ? 'already_recorded' : 'recorded';
                if (! $attendance) {
                    $attendance = Attendance::create(['session_id' => $session->id, 'offering_id' => $session->offering_id, 'enrollment_id' => $enrollment->id, 'status' => 'present', 'method' => $method, 'recorded_by' => $actor->id, 'recorded_at' => now()]);
                    $this->audit->record($actor, $attendance, 'attendance.recorded', reason: $data['reason'] ?? null);
                }
                $result = ['http' => $status === 'recorded' ? 201 : 200, 'body' => ['status' => $status, 'attendance_id' => $attendance->id, 'student_id' => $student->id, 'student_name' => $student->name, 'student_code' => $student->student_code]];
            } catch (RegistrationRejected $exception) {
                $result = ['http' => $exception->httpStatus, 'body' => ['status' => 'rejected', 'message' => $exception->getMessage()]];
                if ($exception->reasonCode) {
                    $result['body']['code'] = $exception->reasonCode;
                }
            }
            AttendanceEvent::create(['event_id' => $data['event_id'], 'actor_id' => $actor->id, 'session_id' => $session->id,
                'reader_session_id' => $reader?->id, 'credential_id' => $credential?->id, 'request_hash' => $hash, 'outcome' => $result['body']['status'],
                'received_at' => now(), 'attendance_id' => $attendance?->id, 'response' => $result['body'], 'http_status' => $result['http'], 'geo_validation' => $result['http'] < 300 ? $geoValidation : null]);

            return $result;
        });
    }

    private function reject(string $message, int $status = 422): never
    {
        throw new RegistrationRejected($message, $status);
    }
}
