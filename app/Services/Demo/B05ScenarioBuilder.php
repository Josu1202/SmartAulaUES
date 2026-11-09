<?php

namespace App\Services\Demo;

use App\Actions\Attendance\ClosureWorkflow;
use App\Enums\Role;
use App\Models\AbsenceRequest;
use App\Models\AcademicCycle;
use App\Models\AssessmentPeriod;
use App\Models\Attendance;
use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Offering;
use App\Models\Subject;
use App\Models\User;
use App\Services\Attendance\AttendancePolicy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Opt-in dataset isolated by B05 IDs; repeat runs never repair or rewrite existing rows. */
final class B05ScenarioBuilder
{
    public function seed(string $profile, string $reference, string $confirmDatabase): array
    {
        $definition = B05ScenarioCatalog::for($profile);
        $database = $this->guard($confirmDatabase);
        $anchor = $this->referenceDate($reference);
        $manifest = hash('sha256', json_encode($definition, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($definition, $database, $anchor, $manifest): array {
            // Session-scoped PostgreSQL lock released on commit or rollback.
            DB::select('select pg_advisory_xact_lock(?)', [50260501]);
            $existing = DB::table('demo_scenario_runs')->where('scenario_code', $definition['code'])->first();
            if ($existing) {
                if ((int) $existing->version !== B05ScenarioCatalog::VERSION
                    || $existing->profile !== ($definition['prefix'] === 'B05S' ? 'small' : 'full')
                    || $existing->reference_date !== $anchor->toDateString()
                    || $existing->manifest_hash !== $manifest) {
                    throw new RuntimeException('El escenario existente tiene otra referencia o contrato. No se altera. Cree una versión nueva.');
                }

                return ['created' => false, 'code' => $definition['code'], 'database' => $database, 'reference' => $anchor->toDateString()];
            }

            $this->rejectCollisions($definition);
            $password = (string) config('smartaulaues.demo_password');
            $prefix = $definition['prefix'];
            $code = $definition['code'];
            $coordinator = User::create([
                'name' => $prefix.' Coordinación ficticia', 'email' => strtolower($prefix).'-coordinacion@example.test',
                'role' => Role::Coordinator, 'password' => $password, 'active' => true,
            ]);
            $teachers = [];
            for ($i = 1; $i <= $definition['teachers']; $i++) {
                $number = str_pad((string) $i, 2, '0', STR_PAD_LEFT);
                $teachers[] = User::create([
                    'name' => $prefix.' Docente '.$number, 'email' => strtolower($prefix).'-docente'.$number.'@example.test',
                    'role' => Role::Teacher, 'password' => $password, 'active' => true,
                ]);
            }
            $students = [];
            for ($i = 0; $i < $definition['students']; $i++) {
                $number = str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT);
                $students[] = User::create([
                    'name' => $prefix.' Estudiante '.$number, 'email' => strtolower($prefix).'-estudiante'.$number.'@example.test',
                    'student_code' => $prefix.'-'.$number, 'role' => Role::Student, 'password' => $password,
                    'active' => true, 'entry_year' => 2024, 'study_year' => ($i % 5) + 1,
                ]);
            }

            $cycle = AcademicCycle::create([
                'code' => $code.'-ACT', 'starts_on' => $anchor->subDays(60)->toDateString(),
                'ends_on' => $anchor->addDays(60)->toDateString(), 'status' => 'active',
            ]);
            for ($group = 0; $group < $definition['active_groups']; $group++) {
                $number = str_pad((string) ($group + 1), 2, '0', STR_PAD_LEFT);
                $subject = Subject::create(['code' => $prefix.'-M'.$number, 'name' => 'B05 '.$prefix.' Materia ficticia '.$number, 'active' => true]);
                $offering = Offering::create([
                    'cycle_id' => $cycle->id, 'subject_id' => $subject->id,
                    'teacher_id' => $teachers[$group % count($teachers)]->id, 'group_code' => '01',
                    'capacity' => 30,
                ]);
                $period = AssessmentPeriod::create([
                    'offering_id' => $offering->id, 'number' => 1, 'starts_on' => $cycle->starts_on,
                    'ends_on' => $cycle->ends_on, 'state' => 'open', 'policy_version' => AttendancePolicy::VERSION,
                ]);
                $enrollments = [];
                for ($j = 0; $j < $definition['students_per_group']; $j++) {
                    $index = ($group * 10 + $j) % count($students);
                    $enrollments[$index] = Enrollment::create([
                        'offering_id' => $offering->id, 'student_id' => $students[$index]->id,
                        'active_from' => $cycle->starts_on,
                    ]);
                }
                for ($week = 0; $week < $definition['completed_per_group']; $week++) {
                    $start = $this->sessionStart($anchor->subDays(45 - 4 * $week)->toDateString(), 8 + $group % 3);
                    $session = $this->session($offering, $period, $start, 'completed');
                    foreach ($enrollments as $index => $enrollment) {
                        $statuses = B05ScenarioCatalog::statuses($index, $definition['completed_per_group']);
                        $status = $statuses[$week];
                        if ($status === null) {
                            continue; // Deliberate incomplete coverage; no fabricated result.
                        }
                        $this->attendance($offering, $session, $enrollment, $status, $start, $teachers[$group % count($teachers)]);
                        if ($status === 'justified') {
                            $this->request($offering, $session, $enrollment, 'approved', $start, $teachers[$group % count($teachers)]);
                        } elseif ($status === 'absent' && $week === $definition['completed_per_group'] - 1 && $index % 6 === 3) {
                            $this->request($offering, $session, $enrollment, 'pending', $start, null);
                        } elseif ($status === 'absent' && $week === $definition['completed_per_group'] - 1 && $index % 6 === 4) {
                            $this->request($offering, $session, $enrollment, 'rejected', $start, $teachers[$group % count($teachers)]);
                        }
                    }
                }
                $future = $this->sessionStart($anchor->addDays(5 + $group)->toDateString(), 9);
                $this->session($offering, $period, $future, 'scheduled');
                $cancelled = $this->sessionStart($anchor->subDays(3)->toDateString(), 10);
                $this->session($offering, $period, $cancelled, 'cancelled');
            }

            // Historical segment has fixed calendar dates independent of the reference.
            $historical = AcademicCycle::create([
                'code' => $code.'-2025', 'starts_on' => '2025-09-01',
                'ends_on' => '2025-12-15', 'status' => 'active',
            ]);
            $subject = Subject::create(['code' => $prefix.'-H01', 'name' => 'B05 historia ficticia verificable', 'active' => true]);
            $oldOffering = Offering::create([
                'cycle_id' => $historical->id, 'subject_id' => $subject->id,
                'teacher_id' => $teachers[0]->id, 'group_code' => '01', 'capacity' => 30,
            ]);
            $oldPeriod = AssessmentPeriod::create([
                'offering_id' => $oldOffering->id, 'number' => 1, 'starts_on' => $historical->starts_on,
                'ends_on' => $historical->ends_on, 'state' => 'open', 'policy_version' => AttendancePolicy::VERSION,
            ]);
            $oldEnrollments = [];
            for ($i = 0; $i < $definition['historical_students']; $i++) {
                $oldEnrollments[] = Enrollment::create([
                    'offering_id' => $oldOffering->id, 'student_id' => $students[$i]->id,
                    'active_from' => '2025-09-01',
                ]);
            }
            for ($week = 0; $week < $definition['historical_sessions']; $week++) {
                $start = $this->sessionStart('2025-10-'.str_pad((string) (6 + 7 * $week), 2, '0', STR_PAD_LEFT), 8);
                $session = $this->session($oldOffering, $oldPeriod, $start, 'completed');
                foreach ($oldEnrollments as $i => $enrollment) {
                    $this->attendance($oldOffering, $session, $enrollment, $i === 1 && $week === 3 ? 'absent' : 'present', $start, $teachers[0]);
                }
            }
            $workflow = app(ClosureWorkflow::class);
            $workflow->closePeriod($coordinator, $oldPeriod);
            if ($oldPeriod->fresh()->state !== 'closed') {
                throw new RuntimeException('No se pudo cerrar el historial B05 con un snapshot válido.');
            }
            $workflow->closeCycle($coordinator, $historical);
            if ($historical->fresh()->status !== 'closed') {
                throw new RuntimeException('No se pudo cerrar el ciclo histórico B05.');
            }
            DB::table('demo_scenario_runs')->insert([
                'scenario_code' => $definition['code'], 'version' => B05ScenarioCatalog::VERSION,
                'profile' => $definition['prefix'] === 'B05S' ? 'small' : 'full',
                'reference_date' => $anchor->toDateString(), 'manifest_hash' => $manifest,
                'expected_counts' => json_encode($definition['expected_counts'], JSON_THROW_ON_ERROR),
                'created_at' => now(), 'updated_at' => now(),
            ]);

            return ['created' => true, 'code' => $definition['code'], 'database' => $database, 'reference' => $anchor->toDateString()];
        }, 3);
    }

    private function guard(string $confirmed): string
    {
        $env = app()->environment();
        if (! in_array($env, ['local', 'testing'], true) || ! config('smartaulaues.demo_enabled')
            || config('database.default') !== 'pgsql') {
            throw new RuntimeException('B05 solo funciona en local/testing, PostgreSQL y DEMO_SEED=true.');
        }
        $effective = (string) DB::selectOne('select current_database() as name')->name;
        $allowed = $env === 'testing' ? 'siaa_test' : 'smartaulaues_local';
        if ($confirmed === '' || $effective !== $allowed || $confirmed !== $effective
            || config('database.connections.pgsql.database') !== $effective) {
            throw new RuntimeException('Base no reconocida o confirmación incorrecta. B05 no escribió datos.');
        }
        $password = (string) config('smartaulaues.demo_password');
        if (mb_strlen($password) < 12 || strlen($password) > 72 || str_contains($password, "\0")) {
            throw new RuntimeException('Defina DEMO_PASSWORD local, entre 12 caracteres y 72 bytes UTF-8.');
        }

        return $effective;
    }

    private function referenceDate(string $reference): CarbonImmutable
    {
        $date = CarbonImmutable::createFromFormat('!Y-m-d', $reference, config('smartaulaues.display_timezone'));
        if ($date === false || $date->format('Y-m-d') !== $reference) {
            throw new RuntimeException('La fecha de referencia debe ser YYYY-MM-DD válida.');
        }
        if ($date->gt(CarbonImmutable::now(config('smartaulaues.display_timezone'))->startOfDay())) {
            throw new RuntimeException('La fecha de referencia B05 no puede estar en el futuro.');
        }

        return $date;
    }

    private function rejectCollisions(array $definition): void
    {
        $prefix = $definition['prefix'];
        $email = strtolower($prefix).'-%@example.test';
        if (User::where('email', 'like', $email)->exists()
            || User::where('student_code', 'like', $prefix.'-%')->exists()
            || Subject::where('code', 'like', $prefix.'-%')->exists()
            || AcademicCycle::where('code', 'like', $definition['code'].'-%')->exists()) {
            throw new RuntimeException('Hay identificadores B05 preexistentes sin manifiesto válido. No se adoptan ni sobrescriben.');
        }
    }

    private function sessionStart(string $date, int $hour): CarbonImmutable
    {
        return CarbonImmutable::parse(sprintf('%s %02d:00:00', $date, $hour), config('smartaulaues.display_timezone'))->utc();
    }

    private function session(Offering $offering, AssessmentPeriod $period, CarbonImmutable $start, string $state): ClassSession
    {
        return ClassSession::create([
            'offering_id' => $offering->id, 'period_id' => $period->id,
            'starts_at' => $start, 'ends_at' => $start->addMinutes(90), 'modality' => 'online',
            'state' => $state, 'started_at' => $state === 'completed' ? $start->addMinute() : null,
            'finalized_at' => $state === 'completed' ? $start->addMinutes(90) : null,
            'change_reason' => $state === 'cancelled' ? 'Cancelación ficticia B05' : null,
        ]);
    }

    private function attendance(Offering $offering, ClassSession $session, Enrollment $enrollment, string $status, CarbonImmutable $start, User $teacher): void
    {
        Attendance::create([
            'offering_id' => $offering->id, 'session_id' => $session->id,
            'enrollment_id' => $enrollment->id, 'status' => $status,
            'method' => null, 'recorded_by' => $teacher->id, 'recorded_at' => $start->addMinutes(5),
        ]);
    }

    private function request(Offering $offering, ClassSession $session, Enrollment $enrollment, string $status, CarbonImmutable $start, ?User $teacher): void
    {
        AbsenceRequest::create([
            'offering_id' => $offering->id, 'session_id' => $session->id,
            'enrollment_id' => $enrollment->id, 'reason' => 'Solicitud ficticia B05 para verificar resultados y privacidad.',
            'status' => $status, 'resolved_by' => $teacher?->id,
            'resolved_at' => $teacher ? $start->addHours(2) : null,
            'resolution_note' => $teacher ? 'Resolución ficticia, sin documentos privados.' : null,
        ]);
    }
}
