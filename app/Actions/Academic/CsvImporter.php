<?php

namespace App\Actions\Academic;

use App\Enums\Role;
use App\Models\AcademicCycle;
use App\Models\AuditLog;
use App\Models\Offering;
use App\Models\User;
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
                    'study_year' => [$kind === 'students' ? 'required' : 'nullable', 'integer', 'between:1,10'],
                ], attributes: ['name' => 'nombre', 'email' => 'correo', 'role' => 'perfil', 'student_code' => 'carné']);
                $errors = $validation->errors()->all();
                if (User::whereRaw('lower(email) = ?', [$fields['email']])->exists() || isset($seenEmails[$fields['email']])) {
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
                    'study_year' => ['required', 'integer', 'between:1,10'],
                ], attributes: ['student_code' => 'carné', 'entry_year' => 'año de ingreso', 'study_year' => 'año de carrera']);
                $errors = $validation->errors()->all();
                $student = User::where('student_code', $fields['student_code'])->first();
                if (! $student || $student->role !== Role::Student) {
                    $errors[] = 'No existe un estudiante con este carné.';
                }
                $data = ['student_id' => $student?->id, 'entry_year' => $fields['entry_year'], 'study_year' => $fields['study_year']];
            } else {
                $validation = Validator::make($fields, ['student_code' => ['required', 'string', 'max:30'], 'active_from' => ['required', 'date_format:Y-m-d'], 'active_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:active_from']], attributes: ['active_from' => 'inicio de vigencia', 'active_to' => 'fin de vigencia', 'student_code' => 'carné']);
                $errors = $validation->errors()->all();
                $student = User::where('student_code', $fields['student_code'])->first();
                if (! $student || ! $student->active || $student->role !== Role::Student) {
                    $errors[] = 'No existe un estudiante activo con este carné.';
                } elseif ($offering?->enrollments()->where('student_id', $student->id)->exists()) {
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
                if (isset($seenCodes[$fields['student_code']]) || (! in_array($kind, ['enrollments', 'student_years']) && User::where('student_code', $fields['student_code'])->exists())) {
                    $errors[] = 'Carné duplicado en el archivo o en el sistema.';
                }
                $seenCodes[$fields['student_code']] = true;
            }
            $entries[] = ['line' => $row['line'], 'fields' => collect($fields)->only(self::HEADERS[$kind])->all(), 'data' => $data, 'errors' => $errors];
        }
        $batchErrors = [];
        if ($kind === 'enrollments') {
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
            $offering = null;
            if ($preview['kind'] === 'enrollments') {
                $offering = Offering::findOrFail($preview['offering_id']);
                $offering->setRelation('cycle', AcademicCycle::lockForUpdate()->findOrFail($offering->cycle_id));
                $offering = Offering::lockForUpdate()->findOrFail($offering->id);
            }
            $review = $this->review($preview['kind'], $preview['rows'], $offering);
            if (! $review['valid']) {
                $this->fail('El lote ya no es válido. Revisa los datos y genera una nueva vista previa. '.implode(' ', $review['errors']));
            }
            foreach ($review['entries'] as $entry) {
                if ($preview['kind'] === 'student_years') {
                    $this->users->setStudentYears($actor, User::findOrFail($entry['data']['student_id']), (int) $entry['data']['entry_year'], (int) $entry['data']['study_year']);
                } elseif ($preview['kind'] !== 'enrollments') {
                    $this->users->save($actor, [...$entry['data'], 'password' => $preview['password_hash']]);
                } else {
                    $this->registry->saveEnrollment($actor, $offering, $entry['data']);
                }
            }
            $count = count($review['entries']);
            AuditLog::create(['actor_id' => $actor->id, 'entity_type' => 'CsvImport', 'entity_id' => (string) Str::uuid(), 'action' => 'import.confirmed', 'after' => ['kind' => $preview['kind'], 'count' => $count, 'offering_id' => $preview['offering_id']], 'reason' => 'Lote confirmado después de revisión.']);

            return $count;
        });
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['file' => $message]);
    }
}
