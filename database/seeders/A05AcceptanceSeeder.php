<?php

namespace Database\Seeders;

use App\Actions\Attendance\ClosureWorkflow;
use App\Actions\Attendance\CredentialManager;
use App\Actions\Attendance\PortalManager;
use App\Actions\Attendance\SessionWorkflow;
use App\Models\AbsenceRequest;
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

/** Identifiable synthetic data only. Never run automatically or against development/production. */
class A05AcceptanceSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('testing') || config('database.default') !== 'pgsql'
            || DB::connection()->getPdo()->query('select current_database()')->fetchColumn() !== 'siaa_test'
            || DB::connection()->getReadPdo()->query('select current_database()')->fetchColumn() !== 'siaa_test') {
            throw new \RuntimeException('El escenario A05 requiere testing y siaa_test efectivos.');
        }
        if (User::where('email', 'a05-coordination@example.test')->exists()) {
            throw new \RuntimeException('El escenario A05 ya existe. No se sobrescriben sus cuentas.');
        }
        DB::transaction(function (): void {
            $now = CarbonImmutable::now('UTC');
            $admin = User::factory()->coordinator()->create(['name' => 'A05 Coordinación', 'email' => 'a05-coordination@example.test']);
            $teacher = User::factory()->teacher()->create(['name' => 'A05 Docente actual', 'email' => 'a05-teacher@example.test']);
            User::factory()->teacher()->create(['name' => 'A05 Docente alterno', 'email' => 'a05-other-teacher@example.test']);
            $students = collect();
            foreach (User::studyLevels() as $level => $label) {
                $students->push(User::factory()->create(['name' => 'A05 Estudiante '.$label,
                    'email' => 'a05-student-'.$level.'@example.test', 'student_code' => 'A05-'.strtoupper((string) $level),
                    'entry_year' => $now->year - 5, 'study_year' => $level === 'egresado' ? null : $level, 'graduated' => $level === 'egresado']));
            }
            User::factory()->inactive()->create(['name' => 'A05 Cuenta inactiva', 'email' => 'a05-inactive@example.test', 'study_year' => 3]);
            User::factory()->create(['name' => 'A05 Acceso provisional', 'email' => 'a05-provisional@example.test', 'study_year' => 1, 'must_change_password' => true]);
            $subject = Subject::factory()->create(['code' => 'A05-INT', 'name' => 'A05 Integración verificable']);
            $cycle = AcademicCycle::factory()->create(['code' => 'A05-ACTUAL', 'starts_on' => $now->startOfYear(), 'ends_on' => $now->endOfYear()]);
            $group = Offering::factory()->create(['cycle_id' => $cycle->id, 'teacher_id' => $teacher->id, 'subject_id' => $subject->id, 'group_code' => 'A05-A']);
            $period = AssessmentPeriod::factory()->create(['offering_id' => $group->id, 'starts_on' => $cycle->starts_on, 'ends_on' => $cycle->ends_on]);
            foreach ($students as $student) {
                Enrollment::factory()->create(['offering_id' => $group->id, 'student_id' => $student->id, 'active_from' => $cycle->starts_on]);
            }
            $session = ClassSession::factory()->create(['offering_id' => $group->id, 'period_id' => $period->id, 'starts_at' => $now->subMinutes(10), 'ends_at' => $now->addMinutes(50), 'modality' => 'online']);
            app(SessionWorkflow::class)->transition($teacher, $session, 'start');
            app(PortalManager::class)->open($teacher, $session, ['nfc', 'nfc_tag', 'qr', 'link', 'manual'], 5);
            $enrollment = $group->enrollments()->where('student_id', $students[1]->id)->sole();
            AbsenceRequest::create(['offering_id' => $group->id, 'session_id' => $session->id, 'enrollment_id' => $enrollment->id, 'status' => 'pending', 'reason' => 'A05 permiso sintético pendiente de revisión']);
            $future = ClassSession::factory()->create(['offering_id' => $group->id, 'period_id' => $period->id, 'starts_at' => $now->addDay(), 'ends_at' => $now->addDay()->addHour(), 'modality' => 'in_person']);
            Enrollment::factory()->create(['offering_id' => $group->id, 'active_from' => $now->addDays(2)->toDateString(), 'student_id' => User::factory()->create(['name' => 'A05 Matrícula futura', 'email' => 'a05-future@example.test', 'study_year' => 2])->id]);
            foreach (['pending', 'active', 'revoked', 'expired'] as $i => $state) {
                $issued = app(CredentialManager::class)->issue($admin, $students[$i]);
                if ($state === 'active') {
                    app(CredentialManager::class)->confirm($admin, $issued['credential'], $issued['token']);
                } elseif ($state === 'revoked') {
                    app(CredentialManager::class)->revoke($admin, $issued['credential'], 'A05 revocación sintética');
                } elseif ($state === 'expired') {
                    $issued['credential']->update(['pending_expires_at' => $now->subMinute()]);
                }
            }
            $disabled = Offering::factory()->create(['cycle_id' => $cycle->id, 'teacher_id' => $teacher->id, 'subject_id' => $subject->id, 'group_code' => 'A05-D']);
            $disabled->forceFill(['active' => false])->save();
            foreach (['ABIERTO-VENCIDO', 'CERRADO', 'REABIERTO'] as $state) {
                $past = AcademicCycle::factory()->create(['code' => 'A05-'.$state, 'starts_on' => $now->subYear()->startOfYear(), 'ends_on' => $now->subYear()->endOfYear()]);
                $pastGroup = Offering::factory()->create(['cycle_id' => $past->id, 'teacher_id' => $teacher->id, 'subject_id' => $subject->id]);
                $pastPeriod = AssessmentPeriod::factory()->create(['offering_id' => $pastGroup->id, 'starts_on' => $past->starts_on, 'ends_on' => $past->ends_on]);
                Enrollment::factory()->create(['offering_id' => $pastGroup->id, 'student_id' => $students[0]->id, 'active_from' => $past->starts_on]);
                ClassSession::factory()->create(['offering_id' => $pastGroup->id, 'period_id' => $pastPeriod->id, 'starts_at' => $now->subYear()->subHour(), 'ends_at' => $now->subYear(), 'state' => 'scheduled']);
                if ($state !== 'ABIERTO-VENCIDO') {
                    app(ClosureWorkflow::class)->closePeriod($admin, $pastPeriod);
                    app(ClosureWorkflow::class)->closeCycle($admin, $past);
                }
                if ($state === 'REABIERTO') {
                    app(ClosureWorkflow::class)->reopenCycle($admin, $past, 'A05 revisión sintética del ciclo');
                    app(ClosureWorkflow::class)->reopenPeriod($admin, $pastPeriod->fresh(), 'A05 revisión sintética del cómputo');
                }
            }
        });
    }
}
