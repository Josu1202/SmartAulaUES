<?php

namespace Database\Seeders;

use App\Actions\Academic\AcademicRegistry;
use App\Actions\Academic\AuditRecorder;
use App\Actions\Academic\CycleFormatManager;
use App\Actions\Academic\OfferingLifecycle;
use App\Actions\Academic\UserManager;
use App\Actions\Attendance\AbsenceWorkflow;
use App\Actions\Attendance\ClosureWorkflow;
use App\Actions\Attendance\CredentialManager;
use App\Actions\Attendance\PortalManager;
use App\Actions\Attendance\RegisterAttendance;
use App\Actions\Attendance\SessionWorkflow;
use App\Models\AcademicCycle;
use App\Models\AssessmentPeriod;
use App\Models\ClassSession;
use App\Models\Offering;
use App\Models\Subject;
use App\Models\User;
use App\Support\LocalDatabase;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/** Local synthetic scenario only. Historical time and NFC confirmation are confined here. */
final class ExplorationSeeder extends Seeder
{
    public const MARKER = 'local-exploration-v1';

    private User $coordinator;

    private CarbonImmutable $anchor;

    private array $students = [];

    private array $teachers = [];

    public function run(): void
    {
        LocalDatabase::verify(allowTesting: true);
        if (DB::table('operation_runs')->where('name', self::MARKER)->exists()) {
            $this->command?->info('El escenario ya existe; no se sobrescribieron sus datos.');

            return;
        }
        $hash = config('smartaulaues.exploration_password_hash');
        if (! is_string($hash) || ! password_get_info($hash)['algo']) {
            throw new RuntimeException('Configura la contraseña con php artisan local:scenario. No existe una clave predeterminada.');
        }
        if (User::where('email', 'like', '%@example.test')->exists() || Subject::whereIn('code', ['MAT101', 'INF101', 'MAT202', 'INF203'])->exists()) {
            throw new RuntimeException('Existen cuentas o materias que podrían pertenecer a otra preparación. No se sobrescribió nada. Usa una base local vacía y verificada.');
        }
        $this->anchor = CarbonImmutable::now('America/El_Salvador')->startOfMinute();
        $previousClock = Date::getTestNow();
        try {
            DB::transaction(function () use ($hash): void {
                DB::select("select pg_advisory_xact_lock(hashtextextended('smartaulaues:local-scenario', 0))");
                if (DB::table('operation_runs')->where('name', self::MARKER)->exists()) {
                    return;
                }
                Date::setTestNow($this->anchor->subDays(450));
                $this->accounts($hash);
                $registry = app(AcademicRegistry::class);
                $subjects = [];
                foreach (['MAT101' => 'Cálculo I', 'INF101' => 'Programación I', 'MAT202' => 'Álgebra lineal', 'INF203' => 'Bases de datos', 'FIS101' => 'Física general', 'MAT204' => 'Estadística', 'INF304' => 'Redes de computadoras', 'INF305' => 'Ingeniería de software'] as $code => $name) {
                    $subjects[] = $registry->saveSubject($this->coordinator, compact('code', 'name') + ['active' => true]);
                }
                $current = $this->cycle('Ciclo actual '.$this->anchor->year, -90, 90);
                $format = app(CycleFormatManager::class)->save($this->coordinator, ['cycle_id' => $current->id, 'name' => 'Calendario de tres cómputos', 'active' => true,
                    'periods' => array_map(fn ($p) => ['number' => $p[0], 'starts_on' => $this->day($p[1]), 'ends_on' => $this->day($p[2])], [[1, -90, -31], [2, -30, 29], [3, 30, 90]])]);
                foreach ($subjects as $i => $subject) {
                    $group = $this->group($current, $subject, $i, '01', $format->id);
                    foreach ($this->students as $j => $student) {
                        if ($j < 40 && in_array($i, [$j % 8, ($j + 1) % 8, ($j + 3) % 8], true)) {
                            $this->enroll($group, $student);
                        }
                    }
                    $first = $group->periods()->where('number', 1)->sole();
                    $second = $group->periods()->where('number', 2)->sole();
                    $this->completed($group, $first, $i, -50, 2);
                    Date::setTestNow($this->anchor);
                    app(ClosureWorkflow::class)->closePeriod($this->coordinator, $first);
                    $finished = $this->completed($group, $second, $i, -7, 4);
                    foreach ([1, 7, 14, 28, 45] as $offset) {
                        $period = $group->periods()->where('starts_on', '<=', $this->day($offset))->where('ends_on', '>=', $this->day($offset))->sole();
                        $this->session($group, $period, $this->anchor->addDays($offset)->startOfDay()->addHours(8 + $i), $i % 2 ? 'online' : 'in_person');
                    }
                    $never = $this->session($group, $second, $this->anchor->subDays(2)->startOfDay()->addHours(8 + $i));
                    $cancelled = $this->session($group, $second, $this->anchor->addDays(3)->startOfDay()->addHours(8 + $i));
                    app(SessionWorkflow::class)->transition($group->teacher, $cancelled, 'cancel', 'La facultad reprogramará la actividad.');
                    if ($i === 0) {
                        $enrollments = $group->enrollments()->with('student')->orderBy('id')->get();
                        foreach (['pending', 'approved', 'rejected'] as $k => $decision) {
                            $candidate = $enrollments[$k === 0 ? 1 : 2]->student;
                            $target = $finished[$k === 1 ? 2 : 3];
                            $request = app(AbsenceWorkflow::class)->submit($candidate, $target, 'Cita médica y documentación presentada al docente.', null);
                            if ($decision !== 'pending') {
                                app(AbsenceWorkflow::class)->resolve($group->teacher, $request, $decision, $decision === 'approved' ? 'La documentación respalda la ausencia.' : 'La solicitud no reúne los requisitos indicados.');
                            }
                        }
                        $live = $this->session($group, $second, $this->anchor->subMinutes(5), 'online', $this->anchor->addMinutes(45));
                        Date::setTestNow($this->anchor);
                        app(SessionWorkflow::class)->transition($group->teacher, $live, 'start');
                        app(PortalManager::class)->open($group->teacher, $live, ['link', 'qr', 'manual', 'nfc', 'nfc_tag'], 30);
                    }
                }
                $disabled = $this->group($current, $subjects[7], 5, '02', $format->id);
                foreach (array_slice($this->students, 0, 8) as $student) {
                    $this->enroll($disabled, $student);
                }
                Date::setTestNow($this->anchor);
                app(OfferingLifecycle::class)->setActive($this->coordinator, $disabled, false, 'La sección no se ofrecerá durante el resto del ciclo.');
                $future = $this->cycle('Ciclo siguiente '.$this->anchor->addDays(100)->year, 100, 250);
                foreach (array_slice($subjects, 0, 3) as $i => $subject) {
                    $group = $this->group($future, $subject, $i);
                    foreach (array_slice($this->students, 0, 12) as $student) {
                        $this->enroll($group, $student);
                    }
                    $this->session($group, $group->periods()->sole(), $this->anchor->addDays(101)->startOfDay()->addHours(8 + $i));
                }
                $past = $this->cycle('Ciclo anterior '.$this->anchor->subDays(300)->year, -420, -180);
                foreach (array_slice($subjects, 0, 2) as $i => $subject) {
                    $group = $this->group($past, $subject, $i);
                    foreach ([...array_filter(array_slice($this->students, 0, 20), fn ($student) => $student->entry_year <= $past->starts_on->year), ...array_slice($this->students, 40, 3)] as $student) {
                        $this->enroll($group, $student);
                    }
                    $period = $group->periods()->sole();
                    $this->completed($group, $period, $i, -220, 4);
                    $this->session($group, $period, $this->anchor->subDays(210)->startOfDay()->addHours(8 + $i));
                    Date::setTestNow($this->anchor);
                    app(ClosureWorkflow::class)->closePeriod($this->coordinator, $period);
                }
                app(ClosureWorkflow::class)->closeCycle($this->coordinator, $past);
                $blocked = $this->cycle('Ciclo pendiente de resolución', -160, -20);
                $group = $this->group($blocked, $subjects[2], 2);
                foreach (array_slice($this->students, 0, 8) as $student) {
                    $this->enroll($group, $student);
                }
                $period = $group->periods()->sole();
                $sessions = $this->completed($group, $period, 2, -30, 4);
                Date::setTestNow($this->anchor);
                app(AbsenceWorkflow::class)->submit($this->students[1], $sessions[3], 'Solicitud pendiente de documentación complementaria.', null);
                app(ClosureWorkflow::class)->closePeriod($this->coordinator, $period);
                app(ClosureWorkflow::class)->closeCycle($this->coordinator, $blocked);
                $review = $this->cycle('Ciclo en revisión', -170, -70);
                $group = $this->group($review, $subjects[3], 3);
                foreach (array_slice($this->students, 0, 10) as $student) {
                    $this->enroll($group, $student);
                }
                $period = $group->periods()->sole();
                $this->completed($group, $period, 3, -100, 4);
                Date::setTestNow($this->anchor);
                app(ClosureWorkflow::class)->closePeriod($this->coordinator, $period);
                app(ClosureWorkflow::class)->closeCycle($this->coordinator, $review);
                app(ClosureWorkflow::class)->reopenCycle($this->coordinator, $review, 'Revisión solicitada por la coordinación de carrera.');
                app(ClosureWorkflow::class)->reopenPeriod($this->coordinator, $period->fresh(), 'Revisión de las resoluciones remitidas por la facultad.');
                foreach (array_slice($this->students, 40, 3) as $student) {
                    app(UserManager::class)->setStudentYears($this->coordinator, $student, $student->entry_year, 'egresado');
                }
                $student = $this->students[39];
                app(UserManager::class)->save($this->coordinator, [...$student->only(['name', 'email', 'student_code', 'entry_year', 'study_year']), 'role' => 'student', 'active' => false, 'reason' => 'Cuenta suspendida por solicitud de su titular.'], $student);
                foreach (['pending', 'active', 'revoked', 'expired'] as $i => $state) {
                    $issued = app(CredentialManager::class)->issue($this->coordinator, $this->students[$i]);
                    // External NDEF verification is intentionally simulated only in this local seeder.
                    if (in_array($state, ['active', 'revoked'], true)) {
                        app(CredentialManager::class)->confirm($this->coordinator, $issued['credential'], $issued['token']);
                    }
                    if ($state === 'revoked') {
                        app(CredentialManager::class)->revoke($this->coordinator, $issued['credential'], 'Sustitución por deterioro de la etiqueta.');
                    }
                    if ($state === 'expired') {
                        $issued['credential']->update(['pending_expires_at' => $this->anchor->subMinute()]);
                    }
                }
                DB::table('operation_runs')->insert(['name' => self::MARKER, 'started_at' => $this->anchor, 'finished_at' => $this->anchor, 'errors' => 0]);
            });
        } finally {
            Date::setTestNow($previousClock);
        }
    }

    private function accounts(string $hash): void
    {
        $this->coordinator = User::create(['name' => 'Lucía Herrera', 'email' => 'lucia.herrera@example.test', 'password' => $hash, 'role' => 'coordinator', 'active' => true]);
        app(AuditRecorder::class)->record($this->coordinator, $this->coordinator, 'user.created');
        foreach (['Ana Martínez', 'Carlos Rivera', 'Beatriz López', 'Diego Flores', 'Elena Cruz', 'Francisco Gómez', 'Patricia Suárez'] as $i => $name) {
            $this->teachers[] = app(UserManager::class)->save($this->coordinator, ['name' => $name, 'email' => Str::slug($name, '.').'@example.test', 'role' => 'teacher', 'active' => $i < 6, 'password' => $hash]);
        }
        $first = ['Camila', 'Daniel', 'Sofía', 'Gabriel', 'Valeria', 'Andrés', 'Isabella', 'Mateo', 'Mariana', 'Sebastián', 'Paula', 'Nicolás', 'Luciana', 'Samuel', 'Adriana', 'David', 'Victoria', 'Alejandro', 'Gabriela', 'Javier', 'Daniela', 'Emilio', 'Natalia', 'Leonardo', 'Carolina', 'Tomás', 'Fernanda', 'Rodrigo', 'Andrea', 'Luis', 'Julia', 'Ricardo', 'Claudia', 'Pablo', 'Raquel', 'Manuel', 'Teresa', 'Hugo', 'Mónica', 'Óscar', 'Renata', 'Esteban', 'Verónica', 'Inés'];
        $last = ['Morales', 'Ramírez', 'Castro', 'Mendoza', 'Aguilar', 'Romero', 'Vásquez', 'Torres', 'Navarro', 'Santos', 'Reyes', 'Ortiz', 'Pineda', 'Mejía', 'Campos', 'Cabrera', 'Molina', 'Rivas', 'Salazar', 'Fuentes'];
        foreach ($first as $i => $given) {
            $name = $given.' '.$last[$i % count($last)];
            $entryYear = $i >= 40 && $i <= 42 ? $this->anchor->year - 6 : $this->anchor->subDays(90)->year - ($i % 5);
            $this->students[] = app(UserManager::class)->save($this->coordinator, ['name' => $name, 'email' => Str::slug($name, '.').'@example.test', 'role' => 'student', 'student_code' => sprintf('ES%04d%03d', $entryYear, $i + 1), 'entry_year' => $entryYear, 'study_year' => $i >= 40 && $i <= 42 ? 5 : ($i % 5) + 1, 'active' => true, 'password' => $hash, 'must_change_password' => $i === 43]);
        }
    }

    private function day(int $offset): string
    {
        return $this->anchor->addDays($offset)->toDateString();
    }

    private function cycle(string $code, int $start, int $end): AcademicCycle
    {
        $this->creationTime($this->day($start));

        return app(AcademicRegistry::class)->saveCycle($this->coordinator, ['code' => $code, 'starts_on' => $this->day($start), 'ends_on' => $this->day($end), 'status' => 'active']);
    }

    private function group(AcademicCycle $cycle, Subject $subject, int $teacher, string $code = '01', ?int $format = null): Offering
    {
        $this->creationTime($cycle->starts_on->toDateString());
        $group = app(AcademicRegistry::class)->saveOffering($this->coordinator, ['cycle_id' => $cycle->id, 'subject_id' => $subject->id, 'teacher_id' => $this->teachers[$teacher % 6]->id, 'group_code' => $code, 'capacity' => 80, 'cycle_format_id' => $format]);
        if ($format === null) {
            app(AcademicRegistry::class)->savePeriod($this->coordinator, $group, ['number' => 1, 'starts_on' => $cycle->starts_on->toDateString(), 'ends_on' => $cycle->ends_on->toDateString()]);
        }

        return $group->load('teacher', 'cycle');
    }

    private function enroll(Offering $group, User $student): void
    {
        $this->creationTime($group->cycle->starts_on->toDateString());
        app(AcademicRegistry::class)->saveEnrollment($this->coordinator, $group, ['student_id' => $student->id, 'active_from' => $group->cycle->starts_on->toDateString(), 'active_to' => null]);
    }

    private function session(Offering $group, AssessmentPeriod $period, CarbonImmutable $start, string $modality = 'online', ?CarbonImmutable $end = null): ClassSession
    {
        Date::setTestNow($start->subMinute()->min($this->anchor));

        return app(SessionWorkflow::class)->save($group->teacher, $group, ['period_id' => $period->id, 'starts_at' => $start->format('Y-m-d\TH:i'), 'ends_at' => ($end ?? $start->addHour())->format('Y-m-d\TH:i'), 'modality' => $modality]);
    }

    private function creationTime(string $startsOn): void
    {
        Date::setTestNow(CarbonImmutable::parse($startsOn, 'America/El_Salvador')->subDay()->min($this->anchor));
    }

    private function completed(Offering $group, AssessmentPeriod $period, int $slot, int $offset, int $count): array
    {
        $sessions = [];
        for ($i = 0; $i < $count; $i++) {
            $start = $this->anchor->addDays($offset + $i)->startOfDay()->addHours(8 + $slot);
            $session = $this->session($group, $period, $start, $slot % 2 ? 'in_person' : 'online');
            Date::setTestNow($start->addMinute());
            app(SessionWorkflow::class)->transition($group->teacher, $session, 'start');
            $portal = app(PortalManager::class)->open($group->teacher, $session, ['manual'], 60);
            foreach ($group->enrollments()->orderBy('id')->get() as $j => $enrollment) {
                if ($i < $count - ($j % 3)) {
                    app(RegisterAttendance::class)->record($group->teacher, $session, 'manual', ['event_id' => (string) Str::uuid(), 'student_id' => $enrollment->student_id, 'portal_id' => $portal['portal']->id, 'reason' => 'Registro confirmado por el docente en la lista de clase.']);
                }
            }
            Date::setTestNow($start->addHour());
            app(SessionWorkflow::class)->transition($group->teacher, $session, 'finish');
            $sessions[] = $session;
        }
        Date::setTestNow($this->anchor);

        return $sessions;
    }
}
