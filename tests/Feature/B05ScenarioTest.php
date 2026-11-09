<?php

namespace Tests\Feature;

use App\Models\AcademicCycle;
use App\Models\AssessmentPeriod;
use App\Models\Attendance;
use App\Models\Enrollment;
use App\Models\Offering;
use App\Models\ReportVersion;
use App\Models\User;
use App\Queries\AttendanceSummary;
use App\Services\Demo\B05ScenarioBuilder;
use App\Services\Demo\B05ScenarioCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class B05ScenarioTest extends TestCase
{
    private const REFERENCE = '2026-10-15';

    private function enableDemo(): void
    {
        config(['smartaulaues.demo_enabled' => true, 'smartaulaues.demo_password' => 'Private-test-password-2026!']);
    }

    private function seedScenario(string $profile, string $reference = self::REFERENCE): array
    {
        return app(B05ScenarioBuilder::class)->seed($profile, $reference, 'siaa_test');
    }

    public function test_small_scenario_has_versioned_counts_and_uses_actual_attendance_calculation(): void
    {
        $this->enableDemo();
        $result = $this->seedScenario('small');
        $this->assertTrue($result['created']);
        $this->assertSame('B05S-V1', $result['code']);
        $definition = B05ScenarioCatalog::for('small');
        $this->assertCounts($definition);
        $this->assertDatabaseHas('demo_scenario_runs', [
            'scenario_code' => 'B05S-V1', 'version' => 1, 'profile' => 'small', 'reference_date' => self::REFERENCE,
        ]);
        $this->assertAcademicSamples('B05S', 6, 8);
        $this->assertHistoricalSnapshot('B05S-V1');
        $this->assertDatabaseCount('nfc_credentials', 0);
        $this->assertDatabaseCount('attendance_events', 0);
    }

    public function test_artisan_command_is_discovered_and_requires_explicit_database_confirmation(): void
    {
        $this->enableDemo();
        $this->artisan('b05:demo', [
            'profile' => 'small', '--reference' => self::REFERENCE,
            '--confirm-database' => 'siaa_test',
        ])->assertExitCode(0);
        $this->assertDatabaseHas('demo_scenario_runs', ['scenario_code' => 'B05S-V1']);
    }

    public function test_replay_never_overwrites_passwords_memberships_or_attendance(): void
    {
        $this->enableDemo();
        $this->seedScenario('small');
        $student = User::where('email', 'b05s-estudiante01@example.test')->sole();
        $student->update(['password' => 'Personal-test-password-2026!', 'name' => 'Nombre cambiado por una persona']);
        $counts = [User::count(), Enrollment::count(), Attendance::count(), ReportVersion::count()];
        $this->assertFalse($this->seedScenario('small')['created']);
        $this->assertSame($counts, [User::count(), Enrollment::count(), Attendance::count(), ReportVersion::count()]);
        $this->assertTrue(Hash::check('Personal-test-password-2026!', $student->fresh()->password));
        $this->assertSame('Nombre cambiado por una persona', $student->fresh()->name);
        try {
            $this->seedScenario('small', '2026-10-14');
            $this->fail('No se debe cambiar la fecha de un escenario existente.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('otra referencia', $exception->getMessage());
        }
    }

    public function test_full_scenario_provides_sixty_students_six_active_groups_and_historical_snapshot(): void
    {
        $this->enableDemo();
        $this->seedScenario('full');
        $definition = B05ScenarioCatalog::for('full');
        $this->assertCounts($definition);
        $this->assertSame(3, User::where('email', 'like', 'b05f-docente%@example.test')->count());
        $this->assertSame(60, User::where('email', 'like', 'b05f-estudiante%@example.test')->count());
        $this->assertSame(6, Offering::whereHas('cycle', fn ($q) => $q->where('code', 'B05F-V1-ACT'))->count());
        $this->assertAcademicSamples('B05F', 6, 10);
        $this->assertHistoricalSnapshot('B05F-V1');
        $this->assertDatabaseCount('nfc_credentials', 0);
    }

    public function test_guard_rejects_disabled_demo_wrong_database_and_unknown_profile(): void
    {
        config(['smartaulaues.demo_enabled' => false, 'smartaulaues.demo_password' => 'Private-test-password-2026!']);
        try {
            $this->seedScenario('small');
            $this->fail('La demo deshabilitada no debe escribir.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('DEMO_SEED', $exception->getMessage());
        }
        $this->enableDemo();
        try {
            app(B05ScenarioBuilder::class)->seed('small', self::REFERENCE, 'otra_base');
            $this->fail('No debe aceptar una base sin confirmar.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Base no reconocida', $exception->getMessage());
        }
        try {
            $this->seedScenario('otra');
            $this->fail('No debe aceptar perfiles desconocidos.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertStringContainsString('small o full', $exception->getMessage());
        }
        $this->assertDatabaseCount('demo_scenario_runs', 0);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_guard_denies_production_even_if_the_flag_is_enabled(): void
    {
        $this->enableDemo();
        app()->instance('env', 'production');
        try {
            $this->seedScenario('small');
            $this->fail('La demo nunca debe ejecutarse en producción.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('local/testing', $exception->getMessage());
        } finally {
            app()->instance('env', 'testing');
        }
        $this->assertDatabaseCount('demo_scenario_runs', 0);
    }

    public function test_preexisting_prefix_is_not_adopted_and_rollback_leaves_other_data_intact(): void
    {
        $this->enableDemo();
        $foreign = User::factory()->create(['email' => 'b05s-docente01@example.test', 'name' => 'Cuenta existente de otra persona']);
        try {
            $this->seedScenario('small');
            $this->fail('No puede tomar una cuenta existente como demo.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('preexistentes', $exception->getMessage());
        }
        $this->assertSame('Cuenta existente de otra persona', $foreign->fresh()->name);
        $this->assertDatabaseCount('demo_scenario_runs', 0);
        $this->assertDatabaseCount('academic_cycles', 0);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_invalid_reference_is_rejected_without_writing(): void
    {
        $this->enableDemo();
        try {
            $this->seedScenario('small', '2026-02-30');
            $this->fail('Una fecha inexistente no puede construir un escenario.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('YYYY-MM-DD', $exception->getMessage());
        }
        $this->assertDatabaseCount('demo_scenario_runs', 0);
    }

    private function assertCounts(array $definition): void
    {
        foreach ($definition['expected_counts'] as $table => $count) {
            $this->assertSame($count, DB::table($table)->count(), $table);
        }
    }

    private function assertAcademicSamples(string $prefix, int $numberOfStudents, int $sessionCount): void
    {
        $offering = Offering::whereHas('subject', fn ($q) => $q->where('code', $prefix.'-M01'))->sole();
        $period = AssessmentPeriod::where('offering_id', $offering->id)->sole();
        for ($i = 0; $i < $numberOfStudents; $i++) {
            $student = User::where('student_code', $prefix.'-'.str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT))->sole();
            $enrollment = Enrollment::where('student_id', $student->id)->where('offering_id', $offering->id)->sole();
            $actual = app(AttendanceSummary::class)->forEnrollment($student, $enrollment, $period);
            $expected = B05ScenarioCatalog::expectedSample($i, $sessionCount);
            foreach (['eligible', 'present', 'justified', 'absent', 'missing'] as $key) {
                $this->assertSame($expected[$key], $actual['counts'][$key], 'B05 '.$key.' student '.$i);
            }
            $this->assertSame($expected['percentage'], $actual['percentage']);
            $this->assertSame($expected['fraction'], array_values($actual['fraction']));
            $this->assertSame($expected['missing'] > 0 ? 'incomplete' : 'provisional', $actual['state']);
        }
    }

    private function assertHistoricalSnapshot(string $code): void
    {
        $cycle = AcademicCycle::where('code', $code.'-2025')->sole();
        $this->assertSame('closed', $cycle->status);
        $offering = $cycle->offerings()->sole();
        $period = $offering->periods()->sole();
        $this->assertSame('closed', $period->state);
        $this->assertNotNull($period->current_report_id);
        $this->assertSame(1, $period->reports()->count());
        $student = $offering->enrollments()->firstOrFail()->student;
        $report = app(AttendanceSummary::class)->forEnrollment($student, $offering->enrollments()->firstOrFail(), $period);
        $this->assertSame('snapshot', $report['source']);
        $this->assertSame(4, $report['counts']['eligible']);
    }
}
