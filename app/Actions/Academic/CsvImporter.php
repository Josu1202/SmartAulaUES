<?php

namespace App\Actions\Academic;

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Offering;
use App\Models\User;
use App\Rules\StudentLevel;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CsvImporter
{
    public const HEADERS = [
        'users' => ['name', 'email', 'role', 'student_code'],
        'students' => ['name', 'email', 'student_code', 'entry_year', 'study_year'],
        'teachers' => ['name', 'email'],
        'student_years' => ['student_code', 'entry_year', 'study_year'],
        'enrollments' => ['student_code', 'active_from', 'active_to'],
    ];

    public function __construct(private UserManager $users, private AcademicRegistry $registry) {}

    public function read(UploadedFile $file, string $kind): array
    {
        $contents = file_get_contents($file->getRealPath());
        if (! mb_check_encoding($contents, 'UTF-8') || str_contains($contents, "\0")) {
            $this->fail('Usa un archivo CSV codificado en UTF-8.');
        }
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, preg_replace('/^\xEF\xBB\xBF/', '', $contents));
        rewind($handle);
        try {
            $headers = fgetcsv($handle, 0, ',', '"', '');
            if ($headers !== self::HEADERS[$kind]) {
                $this->fail('Encabezados incorrectos. Usa exactamente la plantilla descargable, separada por comas.');
            }
            $rows = [];
            $line = 1;
            while (($values = fgetcsv($handle, 0, ',', '"', '')) !== false) {
                $line++;
                if ($values === [null]) {
                    continue;
                }
                if (count($values) !== count($headers)) {
                    $this->fail('El registro '.$line.' tiene una cantidad incorrecta de columnas.');
                }
                if (count($rows) >= 500) {
                    $this->fail('Cada lote admite como máximo 500 registros.');
                }
                $rows[] = ['line' => $line, 'fields' => array_map(fn ($value) => trim($value ?? ''), array_combine($headers, $values))];
            }
            if ($rows === []) {
                $this->fail('El archivo no contiene registros para importar.');
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    public function review(string $kind, array $rows, ?Offering $offering = null): array
    {
        // Bound lookup cost by batch, not by row (also reused at confirmation).
        $fields = collect($rows)->pluck('fields');
        $emails = $fields->pluck('email')->filter()->map(fn ($v) => mb_strtolower($v))->all();
        $codes = $fields->pluck('student_code')->filter()->map(fn ($v) => mb_strtoupper($v))->all();
        $accounts = User::whereIn('student_code', $codes)->orWhereIn('email', $emails)->get();
        $byEmail = $accounts->keyBy(fn ($u) => mb_strtolower($u->email));
        $byCode = $accounts->keyBy('student_code');
        $enrolled = $offering ? $offering->enrollments()->pluck('student_id')->flip() : collect();
        $entries = [];
        $seenEmails = [];
        $seenCodes = [];
        foreach ($rows as $row) {
            $fields = $row['fields'];
            $fields['student_code'] = mb_strtoupper($fields['student_code'] ?? '');
            $errors = [];
            $data = [];
            if (in_array($kind, ['users', 'students', 'teachers'])) {
                $fields['role'] = match ($kind) {
                    'students' => 'student', 'teachers' => 'teacher', default => $fields['role']
                };
                $fields['student_code'] ??= '';
                $fields['email'] = mb_strtolower($fields['email']);
                $validation = Validator::make($fields, [
                    'name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email:rfc', 'max:255'],
                    'role' => ['required', Rule::in(['teacher', 'student'])],
                    'student_code' => [Rule::requiredIf($fields['role'] === 'student'), 'nullable', 'max:30', 'regex:/^[A-Z0-9][A-Z0-9._-]*$/'],
                    'entry_year' => [$kind === 'students' ? 'required' : 'nullable', 'integer', 'between:1900,2200'],
                    'study_year' => [$kind === 'students' ? 'required' : 'nullable', new StudentLevel],
                ], attributes: ['name' => 'nombre', 'email' => 'correo', 'role' => 'perfil', 'student_code' => 'carné']);
                $errors = $validation->errors()->all();
                if ($byEmail->has($fields['email']) || isset($seenEmails[$fields['email']])) {
                    $errors[] = 'Correo duplicado en el archivo o en el sistema.';
                }
                $seenEmails[$fields['email']] = true;
                if ($fields['role'] === 'teacher' && $fields['student_code'] !== '') {
                    $errors[] = 'El carné debe estar vacío para docentes.';
                }
                $data = [...$fields, 'student_code' => $fields['student_code'] ?: null, 'active' => true];
            } elseif ($kind === 'student_years') {
                $validation = Validator::make($fields, [
                    'student_code' => ['required', 'string', 'max:30'],
                    'entry_year' => ['required', 'integer', 'between:1900,2200'],
                    'study_year' => ['required', new StudentLevel],
                ], attributes: ['student_code' => 'carné', 'entry_year' => 'año de ingreso', 'study_year' => 'año de carrera']);
                $errors = $validation->errors()->all();
                $student = $byCode->get($fields['student_code']);
                if (! $student || $student->role !== Role::Student) {
                    $errors[] = 'No existe un estudiante con este carné.';
                }
                $data = ['student_id' => $student?->id, 'entry_year' => $fields['entry_year'], 'study_year' => $fields['study_year']];
            } else {
                $validation = Validator::make($fields, ['student_code' => ['required', 'string', 'max:30'], 'active_from' => ['required', 'date_format:Y-m-d'], 'active_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:active_from']], attributes: ['active_from' => 'inicio de vigencia', 'active_to' => 'fin de vigencia', 'student_code' => 'carné']);
                $errors = $validation->errors()->all();
                $student = $byCode->get($fields['student_code']);
                if (! $student || ! $student->active || $student->graduated || $student->role !== Role::Student) {
                    $errors[] = 'No existe un estudiante activo con este carné.';
                } elseif ($enrolled->has($student->id)) {
                    $errors[] = 'El estudiante ya está inscrito en este grupo.';
                }
                if ($offering && ! $validation->fails()) {
                    $start = $offering->cycle->starts_on->toDateString();
                    $end = $offering->cycle->ends_on->toDateString();
                    if ($fields['active_from'] < $start || $fields['active_from'] > $end || ($fields['active_to'] !== '' && $fields['active_to'] > $end)) {
                        $errors[] = 'La vigencia debe estar dentro de las fechas del ciclo.';
                    }
                }
                $data = ['student_id' => $student?->id, 'active_from' => $fields['active_from'], 'active_to' => $fields['active_to'] ?: null];
            }
            if ($fields['student_code'] !== '') {
                if (isset($seenCodes[$fields['student_code']]) || (! in_array($kind, ['enrollments', 'student_years']) && $byCode->has($fields['student_code']))) {
                    $errors[] = 'Carné duplicado en el archivo o en el sistema.';
                }
                $seenCodes[$fields['student_code']] = true;
            }
            $entries[] = ['line' => $row['line'], 'fields' => collect($fields)->only(self::HEADERS[$kind])->all(), 'data' => $data, 'errors' => $errors];
        }
        $batchErrors = [];
        if ($kind === 'enrollments') {
            if ($offering && ! $offering->active) {
                $batchErrors[] = 'El grupo está desactivado. Reactívalo antes de cargar inscripciones.';
            }
            if (! $offering || $offering->cycle->status === 'closed') {
                $batchErrors[] = 'Selecciona un grupo de un ciclo abierto.';
            } else {
                $newSeats = count(array_filter($entries, fn ($entry) => blank($entry['data']['active_to']) || $entry['data']['active_to'] >= $this->registry->seatDate($offering)));
                if ($this->registry->reservedSeats($offering) + $newSeats > $offering->capacity) {
                    $batchErrors[] = 'El lote supera el cupo disponible del grupo.';
                }
            }
        }

        return ['entries' => $entries, 'errors' => $batchErrors, 'valid' => $batchErrors === [] && ! collect($entries)->contains(fn ($entry) => $entry['errors'] !== [])];
    }

    public function confirm(User $actor, array $preview): int
    {
        Gate::forUser($actor)->authorize('create', User::class);

        return DB::transaction(function () use ($actor, $preview): int {
            // Same ordering as UserManager; manual edits cannot race batch revalidation.
            User::where('role', 'coordinator')->orderBy('id')->lockForUpdate()->get();
            $offering = null;
            if ($preview['kind'] === 'enrollments') {
                $offering = $this->registry->lockOffering(Offering::findOrFail($preview['offering_id']));
            }
            if (isset($preview['baseline']) && ! hash_equals($preview['baseline'], $this->baseline($preview, lock: true))) {
                $this->fail('Los datos cambiaron desde la revisión. Genera una nueva vista previa.');
            }
            $review = $this->review($preview['kind'], $preview['rows'], $offering);
            if (! $review['valid']) {
                $this->fail('El lote ya no es válido. Revisa los datos y genera una nueva vista previa. '.implode(' ', $review['errors']));
            }
            foreach ($review['entries'] as $entry) {
                if ($preview['kind'] === 'student_years') {
                    $this->users->setStudentYears($actor, User::findOrFail($entry['data']['student_id']), (int) $entry['data']['entry_year'], $entry['data']['study_year']);
                } elseif ($preview['kind'] !== 'enrollments') {
                    $this->users->save($actor, [...$entry['data'], 'password' => $preview['password_hash'], 'must_change_password' => true]);
                } else {
                    $this->registry->saveEnrollment($actor, $offering, $entry['data']);
                }
            }
            $count = count($review['entries']);
            AuditLog::create(['actor_id' => $actor->id, 'entity_type' => 'CsvImport', 'entity_id' => (string) Str::uuid(), 'action' => 'import.confirmed', 'after' => ['kind' => $preview['kind'], 'count' => $count, 'offering_id' => $preview['offering_id']], 'reason' => 'Lote confirmado después de revisión.']);

            return $count;
        });
    }

    public function baseline(array $preview, bool $lock = false): string
    {
        $fields = collect($preview['rows'])->pluck('fields');
        $query = User::whereIn('student_code', $fields->pluck('student_code')->filter()->map(fn ($v) => mb_strtoupper($v)))
            ->orWhereIn('email', $fields->pluck('email')->filter()->map(fn ($v) => mb_strtolower($v)))->orderBy('id');
        if ($lock) {
            $query->lockForUpdate();
        }

        return hash('sha256', $query->get(['id', 'name', 'email', 'student_code', 'role', 'active', 'entry_year', 'study_year', 'graduated', 'updated_at'])->toJson());
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['file' => $message]);
    }
}
