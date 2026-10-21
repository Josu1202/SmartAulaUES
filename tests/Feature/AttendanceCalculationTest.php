<?php

namespace Tests\Feature;

use App\Models\AbsenceRequest;
use App\Models\AssessmentPeriod;
use App\Models\Attendance;
use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\User;
use App\Queries\AttendanceSummary;
use App\Services\Attendance\AttendanceCalculator;
use App\Services\Attendance\AttendancePolicy;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AttendanceCalculationTest extends TestCase
{
    private function scenario(): array
    {
        $this->travelTo(now()->setDate(2026, 6, 15)->setTime(18, 0));
        $period = AssessmentPeriod::factory()->create(['policy_version' => AttendancePolicy::VERSION]);
        $enrollment = Enrollment::factory()->create(['offering_id' => $period->offering_id]);

        return [$period, $enrollment];
    }

    private function createSession(AssessmentPeriod $period, Enrollment $enrollment, ?string $status, ?string $request = null, array $attributes = []): ClassSession
    {
        $session = ClassSession::factory()->create($attributes + ['period_id' => $period->id, 'offering_id' => $period->offering_id, 'state' => 'completed', 'starts_at' => '2026-06-01 14:00:00+00', 'ends_at' => '2026-06-01 15:00:00+00']);
        if ($status !== null) {
            Attendance::create(['session_id' => $session->id, 'enrollment_id' => $enrollment->id, 'offering_id' => $period->offering_id, 'status' => $status, 'recorded_by' => $period->offering->teacher_id, 'recorded_at' => now()]);
        }
        if ($request !== null) {
            AbsenceRequest::create(['session_id' => $session->id, 'enrollment_id' => $enrollment->id, 'offering_id' => $period->offering_id, 'status' => $request, 'reason' => 'Fixture ficticio de A01']);
        }

        return $session;
    }

    public function test_shared_fixtures_match_database_results_without_writing(): void
    {
        foreach (require base_path('tests/Fixtures/attendance-calculation.php') as $fixture) {
            [$period, $enrollment] = $this->scenario();
            foreach ($fixture['statuses'] as $status) {
                $this->createSession($period, $enrollment, $status, $status === 'justified' ? 'approved' : null);
            }
            $writes = [];
            DB::listen(function ($query) use (&$writes): void {
                if (preg_match('/^\s*(insert|update|delete|alter|create|drop)\b/i', $query->sql)) {
                    $writes[] = $query->sql;
                }
            });
            $result = app(AttendanceSummary::class)->forEnrollment($enrollment->student, $enrollment, $period);
            $this->assertSame([], $writes);
            $this->assertSame($fixture['percentage'], $result['percentage']);
            $this->assertSame($fixture['meets'], $result['meets_minimum']);
            $this->assertSame(count($fixture['statuses']), $result['fraction']['denominator']);
            $this->assertSame($fixture['meets'] === null ? null : ($fixture['meets'] ? 'Alcanza el mínimo de asistencia' : 'No alcanza el mínimo de asistencia'), $result['conclusion']);
        }
    }

    public function test_rounding_cannot_change_the_conclusion(): void
    {
        $facts = [];
        for ($i = 0; $i < 40001; $i++) {
            $facts[] = ['id' => $i, 'academic_date' => '2026-06-01', 'state' => 'completed', 'status' => $i < 30000 ? 'present' : 'absent', 'request_status' => null, 'starts_at' => '2026-06-01T14:00:00.000000Z', 'ends_at' => '2026-06-01T15:00:00.000000Z'];
        }
        $result = app(AttendanceCalculator::class)->calculate(AttendancePolicy::VERSION, $facts, '2026-01-01', null, '2026-06-15T18:00:00.000000Z');
        $this->assertSame('75.00', $result['percentage']);
        $this->assertFalse($result['meets_minimum']);
    }

    public function test_missing_and_inconsistent_results_preserve_fraction_but_suppress_conclusion(): void
    {
        [$period, $enrollment] = $this->scenario();
        $this->createSession($period, $enrollment, 'present', 'approved');
        $this->createSession($period, $enrollment, 'justified', 'approved');
        $missing = $this->createSession($period, $enrollment, null, 'approved');
        $unapproved = $this->createSession($period, $enrollment, 'justified', 'pending');
        $conflict = $this->createSession($period, $enrollment, 'absent', 'approved');
        $result = app(AttendanceSummary::class)->forEnrollment($enrollment->student, $enrollment, $period);
        $this->assertSame('incomplete', $result['state']);
        $this->assertSame(['numerator' => 2, 'denominator' => 5], $result['fraction']);
        $this->assertNull($result['percentage']);
        $this->assertNull($result['conclusion']);
        $this->assertSame(1, $result['counts']['missing']);
        $this->assertSame(2, $result['counts']['inconsistent']);
        $this->assertSame([$missing->id, $unapproved->id, $conflict->id], array_column($result['incidents'], 'session_id'));
        $this->assertSame(4, Attendance::count());
        $this->assertSame('absent', $conflict->attendances()->sole()->status->value);
    }

    public function test_pending_and_rejected_requests_do_not_add_to_the_numerator(): void
    {
        [$period, $enrollment] = $this->scenario();
        $this->createSession($period, $enrollment, 'present');
        $pending = $this->createSession($period, $enrollment, 'absent', 'pending');
        $this->createSession($period, $enrollment, 'absent', 'rejected');
        $result = app(AttendanceSummary::class)->forEnrollment($enrollment->student, $enrollment, $period);
        $this->assertSame('provisional', $result['state']);
        $this->assertSame('33.33', $result['percentage']);
        $this->assertSame([$pending->id], $result['pending_request_session_ids']);
        $this->assertFalse($result['meets_minimum']);
    }

    public function test_local_enrollment_and_withdrawal_dates_and_filter_are_inclusive(): void
    {
        [$period, $enrollment] = $this->scenario();
        $enrollment->update(['active_from' => '2026-06-01', 'active_to' => '2026-06-01']);
        foreach (['2026-06-01 05:59:00', '2026-06-01 06:00:00', '2026-06-02 05:59:00', '2026-06-02 06:00:00'] as $start) {
            $this->createSession($period, $enrollment, 'present', attributes: ['starts_at' => $start, 'ends_at' => CarbonImmutable::parse($start, 'UTC')->addHour()]);
        }
        $enrollment->student->update(['active' => false]);
        $actor = $period->offering->teacher;
        $result = app(AttendanceSummary::class)->forEnrollment($actor, $enrollment, $period, '2026-06-01', '2026-06-01');
        $this->assertSame(2, $result['counts']['eligible']);
        $this->assertSame('100.00', $result['percentage']);
        $empty = app(AttendanceSummary::class)->forEnrollment($actor, $enrollment, $period, '2026-06-02', '2026-06-03');
        $this->assertSame('no_sessions', $empty['state']);
        $this->assertNull($empty['conclusion']);
    }

    public function test_unfinished_session_categories_are_distinct_and_not_eligible(): void
    {
        [$period, $enrollment] = $this->scenario();
        foreach ([['scheduled', '2026-06-16 18:00', '2026-06-16 19:00'], ['in_progress', '2026-06-15 17:00', '2026-06-15 19:00'], ['scheduled', '2026-06-15 17:00', '2026-06-15 19:00'], ['scheduled', '2026-06-14 17:00', '2026-06-14 19:00'], ['in_progress', '2026-06-14 17:00', '2026-06-14 19:00'], ['cancelled', '2026-06-14 17:00', '2026-06-14 19:00']] as [$state, $start, $end]) {
            $this->createSession($period, $enrollment, null, attributes: ['state' => $state, 'starts_at' => $start, 'ends_at' => $end, 'change_reason' => $state === 'cancelled' ? 'Cancelación ficticia de prueba' : null]);
        }
        $result = app(AttendanceSummary::class)->forEnrollment($enrollment->student, $enrollment, $period);
        foreach (['future', 'in_progress', 'awaiting_start', 'cancelled'] as $key) {
            $this->assertSame(1, $result['counts'][$key]);
        }
        $this->assertSame(2, $result['counts']['overdue_unfinished']);
        $this->assertSame(0, $result['counts']['eligible']);
        $this->assertSame('no_sessions', $result['state']);
    }

    public function test_unknown_and_demo_versions_never_use_the_new_formula(): void
    {
        [$period, $enrollment] = $this->scenario();
        $this->createSession($period, $enrollment, 'present');
        foreach (['demo-2026-v1', 'unknown-v9'] as $version) {
            $period->update(['policy_version' => $version]);
            $result = app(AttendanceSummary::class)->forEnrollment($enrollment->student, $enrollment, $period);
            $this->assertSame('policy_unavailable', $result['state']);
            $this->assertNull($result['fraction']['numerator']);
            $this->assertNull($result['percentage']);
            $this->assertNull($result['conclusion']);
            $this->assertSame($version, $period->fresh()->policy_version);
        }
    }

    public function test_incidents_and_pending_requests_belong_only_to_the_target_student(): void
    {
        [$period, $enrollment] = $this->scenario();
        $session = $this->createSession($period, $enrollment, 'present');
        $other = Enrollment::factory()->create(['offering_id' => $period->offering_id]);
        AbsenceRequest::create(['offering_id' => $period->offering_id, 'session_id' => $session->id, 'enrollment_id' => $other->id, 'status' => 'pending', 'reason' => 'Información ajena']);
        $result = app(AttendanceSummary::class)->forEnrollment($enrollment->student, $enrollment, $period);
        $this->assertSame([], $result['incidents']);
        $this->assertSame([], $result['pending_request_session_ids']);
        $this->assertSame('100.00', $result['percentage']);
        foreach ([$other->student, User::factory()->teacher()->create(), User::factory()->coordinator()->create(['active' => false])] as $unauthorized) {
            try {
                app(AttendanceSummary::class)->forEnrollment($unauthorized, $enrollment, $period);
                $this->fail('Se permitió consultar información ajena.');
            } catch (AuthorizationException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertSame('100.00', app(AttendanceSummary::class)->forEnrollment(User::factory()->coordinator()->create(), $enrollment, $period)['percentage']);
    }

    public function test_period_from_another_group_is_rejected(): void
    {
        [$period, $enrollment] = $this->scenario();
        $this->expectException(ModelNotFoundException::class);
        app(AttendanceSummary::class)->forEnrollment($enrollment->student, $enrollment, AssessmentPeriod::factory()->create());
    }

    public function test_invalid_ranges_are_rejected(): void
    {
        [$period, $enrollment] = $this->scenario();
        foreach ([['2026-02-30', null], ['yesterday', null], ['2026-06-02', '2026-06-01']] as [$from, $to]) {
            try {
                app(AttendanceSummary::class)->forEnrollment($enrollment->student, $enrollment, $period, $from, $to);
                $this->fail('Se aceptó un rango inválido.');
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
