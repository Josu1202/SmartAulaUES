<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\AcademicCycle;
use App\Models\AssessmentPeriod;
use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Offering;
use App\Models\Subject;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing']) || ! config('smartaulaues.demo_enabled')) {
            throw new RuntimeException('Los datos ficticios solo se permiten en local/testing con DEMO_SEED=true.');
        }
        $password = (string) config('smartaulaues.demo_password');
        if (mb_strlen($password) < 12 || strlen($password) > 72 || str_contains($password, "\0")) {
            throw new RuntimeException('Define DEMO_PASSWORD con al menos 12 caracteres y hasta 72 bytes UTF-8 antes de sembrar.');
        }
        DB::transaction(function () use ($password): void {
            User::firstOrCreate(['email' => 'coordinacion@siaa.test'], ['name' => 'Coordinación de prueba', 'role' => Role::Coordinator, 'password' => $password, 'active' => true]);
            $teachers = [];
            for ($i = 1; $i <= 2; $i++) {
                $teachers[] = User::firstOrCreate(['email' => "docente{$i}@siaa.test"], ['name' => "Docente de prueba {$i}", 'role' => Role::Teacher, 'password' => $password, 'active' => true]);
            }
            $students = [];
            for ($i = 1; $i <= 20; $i++) {
                $number = str_pad((string) $i, 2, '0', STR_PAD_LEFT);
                $students[] = User::firstOrCreate(['email' => "estudiante{$number}@siaa.test"], ['name' => "Estudiante de prueba {$number}", 'student_code' => "DEMO-{$number}", 'role' => Role::Student, 'password' => $password, 'active' => true, 'entry_year' => 2026 - (($i - 1) % 5), 'study_year' => (($i - 1) % 5) + 1]);
            }
            $cycle = AcademicCycle::firstOrCreate(['code' => '2026-II-DEMO'], ['starts_on' => '2026-08-01', 'ends_on' => '2026-12-15', 'status' => 'active']);
            foreach ([['ACS-DEMO', 'Análisis de sistemas'], ['FAR-DEMO', 'Fundamentos de arquitectura']] as $i => [$code,$name]) {
                $subject = Subject::firstOrCreate(['code' => $code], ['name' => $name, 'active' => true]);
                $offering = Offering::firstOrCreate(['cycle_id' => $cycle->id, 'subject_id' => $subject->id, 'group_code' => '01'], ['teacher_id' => $teachers[$i]->id, 'capacity' => 30]);
                foreach ($students as $student) {
                    Enrollment::firstOrCreate(['offering_id' => $offering->id, 'student_id' => $student->id], ['active_from' => '2026-08-01']);
                }
                $period = AssessmentPeriod::firstOrCreate(['offering_id' => $offering->id, 'number' => 1], ['starts_on' => '2026-08-01', 'ends_on' => '2026-12-15', 'state' => 'open', 'policy_version' => config('attendance.policy_version')]);
                $first = CarbonImmutable::parse($i === 0 ? '2026-10-13 10:00:00' : '2026-10-14 08:00:00', config('smartaulaues.display_timezone'))->utc();
                for ($week = 0; $week < 4; $week++) {
                    $start = $first->addWeeks($week);
                    ClassSession::firstOrCreate(['period_id' => $period->id, 'offering_id' => $offering->id, 'starts_at' => $start], ['ends_at' => $start->addMinutes(100), 'modality' => 'in_person', 'state' => 'scheduled']);
                }
            }
        });
        $this->command?->info('Escenario ficticio: 23 usuarios, 2 grupos, 40 inscripciones, 8 sesiones programadas. Sin asistencias inventadas.');
    }
}
