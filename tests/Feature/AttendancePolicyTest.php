<?php

namespace Tests\Feature;

use App\Actions\Academic\AcademicRegistry;
use App\Actions\Academic\CycleFormatManager;
use App\Models\AssessmentPeriod;
use App\Models\AuditLog;
use App\Models\Offering;
use App\Models\User;
use App\Services\Attendance\AttendancePolicy;
use Tests\TestCase;

class AttendancePolicyTest extends TestCase
{
    public function test_standalone_policy_route_and_navigation_are_retired(): void
    {
        $this->get('/attendance-policy')->assertNotFound();
        $actor = User::factory()->coordinator()->create();
        $offering = Offering::factory()->create();
        $this->actingAs($actor)->get('/offerings/'.$offering->id)->assertOk()->assertDontSee('Política de asistencia')->assertDontSee('Demostración')->assertSee('Resultados de asistencia');
    }

    public function test_internal_versions_remain_explicit_and_unknown_versions_are_not_aliases(): void
    {
        $this->assertNull(AttendancePolicy::find('demo-2026-v1'));
        $this->assertNull(AttendancePolicy::find('unknown-v9'));
        $this->assertSame(75, AttendancePolicy::find(AttendancePolicy::VERSION)['minimum_percentage']);
    }

    public function test_new_individual_and_copied_periods_use_new_policy_without_reassigning_existing_ones(): void
    {
        $actor = User::factory()->coordinator()->create();
        $existing = AssessmentPeriod::factory()->create(['policy_version' => 'demo-2026-v1']);
        $offering = Offering::factory()->create();
        $registry = app(AcademicRegistry::class);
        $period = $registry->savePeriod($actor, $offering, ['number' => 1, 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
        $this->assertSame(AttendancePolicy::VERSION, $period->policy_version);
        $format = app(CycleFormatManager::class)->save($actor, ['cycle_id' => $offering->cycle_id, 'name' => 'Calendario A01', 'active' => true, 'periods' => [['number' => 1, 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']]]);
        $copy = $registry->saveOffering($actor, $offering->only(['cycle_id', 'subject_id', 'teacher_id', 'capacity']) + ['group_code' => 'A01', 'cycle_format_id' => $format->id]);
        $this->assertSame(AttendancePolicy::VERSION, $copy->periods()->sole()->policy_version);
        $registry->savePeriod($actor, $existing->offering, ['number' => $existing->number, 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31'], $existing);
        $this->assertSame('demo-2026-v1', $existing->fresh()->policy_version);
        $audit = AuditLog::where('entity_type', 'AssessmentPeriod')->where('entity_id', (string) $period->id)->where('action', 'period.created')->sole();
        $this->assertSame(AttendancePolicy::VERSION, $audit->after['policy_version']);
    }
}
