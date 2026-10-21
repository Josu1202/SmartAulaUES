<?php

namespace Tests\Feature;

use App\Actions\Academic\NormalizeDevelopmentAttendancePolicy;
use App\Models\AssessmentPeriod;
use App\Models\Attendance;
use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\ReportVersion;
use App\Services\Attendance\AttendancePolicy;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class DevelopmentAttendancePolicyTest extends TestCase
{
    public function test_explicit_normalization_preserves_history_is_audited_and_is_repeatable(): void
    {
        $period = AssessmentPeriod::factory()->create(['policy_version' => 'demo-2026-v1']);
        $session = ClassSession::factory()->create(['period_id' => $period->id, 'offering_id' => $period->offering_id]);
        $enrollment = Enrollment::factory()->create(['offering_id' => $period->offering_id]);
        Attendance::create(['offering_id' => $period->offering_id, 'session_id' => $session->id, 'enrollment_id' => $enrollment->id, 'status' => 'present', 'recorded_by' => $period->offering->teacher_id, 'recorded_at' => now()]);
        $before = [];
        foreach (['users', 'enrollments', 'class_sessions', 'attendances', 'absence_requests'] as $table) {
            $before[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }
        $dates = $period->only(['starts_on', 'ends_on', 'created_at']);
        $normalizer = app(NormalizeDevelopmentAttendancePolicy::class);
        $this->assertSame(1, $normalizer->run('siaa_test')['candidates']);
        $this->assertSame('demo-2026-v1', $period->fresh()->policy_version);
        $this->assertSame(1, $normalizer->run('siaa_test', true)['updated']);
        $this->assertSame(0, $normalizer->run('siaa_test', true)['updated']);
        $this->assertSame(AttendancePolicy::VERSION, $period->fresh()->policy_version);
        $this->assertEquals($dates, $period->fresh()->only(array_keys($dates)));
        foreach ($before as $table => $json) {
            $this->assertSame($json, DB::table($table)->orderBy('id')->get()->toJson());
        }
        $audit = DB::table('audit_logs')->where('action', 'period.policy_normalized')->sole();
        $this->assertNull($audit->actor_id);
        $this->assertSame('demo-2026-v1', json_decode($audit->before, true)['policy_version']);
        $this->assertSame(AttendancePolicy::VERSION, json_decode($audit->after, true)['policy_version']);
    }

    public function test_reports_and_closed_or_closing_periods_are_never_reassigned(): void
    {
        $reported = AssessmentPeriod::factory()->create(['policy_version' => 'demo-2026-v1']);
        $report = ReportVersion::create(['period_id' => $reported->id, 'version' => 1, 'snapshot' => ['immutable' => true], 'policy_version' => 'demo-2026-v1', 'generated_by' => $reported->offering->teacher_id]);
        $closed = AssessmentPeriod::factory()->create(['policy_version' => 'unknown', 'state' => 'closed', 'closed_at' => now()]);
        $closing = AssessmentPeriod::factory()->create(['policy_version' => 'unknown', 'state' => 'closing']);
        $before = $report->fresh()->toArray();
        $plan = app(NormalizeDevelopmentAttendancePolicy::class)->run('siaa_test', true);
        $this->assertSame(3, $plan['protected']);
        $this->assertSame(0, $plan['updated']);
        $this->assertSame('demo-2026-v1', $reported->fresh()->policy_version);
        $this->assertSame('unknown', $closed->fresh()->policy_version);
        $this->assertSame('unknown', $closing->fresh()->policy_version);
        $this->assertSame($before, $report->fresh()->toArray());
    }

    public function test_maintenance_rejects_production_and_a_database_mismatch(): void
    {
        foreach ([['production', 'siaa_test'], ['testing', 'siaa'], ['testing', 'another_test']] as [$environment, $database]) {
            app()->instance('env', $environment);
            try {
                app(NormalizeDevelopmentAttendancePolicy::class)->run($database, true);
                $this->fail('Se permitió una normalización fuera del ámbito autorizado.');
            } catch (RuntimeException) {
                $this->addToAssertionCount(1);
            }
        }
        app()->instance('env', 'testing');
    }

    public function test_command_previews_before_apply_and_requires_database(): void
    {
        AssessmentPeriod::factory()->create(['policy_version' => 'demo-2026-v1']);
        $this->artisan('attendance:normalize-development-policy', ['--database' => 'siaa_test'])->expectsOutput('Cómputos por actualizar: 1')->assertSuccessful();
        $this->assertDatabaseHas('assessment_periods', ['policy_version' => 'demo-2026-v1']);
        $this->artisan('attendance:normalize-development-policy', ['--database' => 'siaa_test', '--apply' => true])->expectsOutput('Cómputos actualizados: 1')->assertSuccessful();
        $this->artisan('attendance:normalize-development-policy')->assertFailed();
    }
}
