<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Offering;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AcademicIntegrityTest extends TestCase
{
    public function test_zero_capacity_is_rejected_by_postgresql(): void
    {
        $this->expectException(QueryException::class);
        DB::transaction(fn () => Offering::factory()->create(['capacity' => 0]));
    }

    public function test_period_and_session_must_belong_to_the_same_group(): void
    {
        $session = ClassSession::factory()->create();
        $other = Offering::factory()->create();
        $this->expectException(QueryException::class);
        DB::transaction(fn () => ClassSession::factory()->create(['period_id' => $session->period_id, 'offering_id' => $other->id]));
    }

    public function test_enrollment_is_unique_per_student_and_group(): void
    {
        $enrollment = Enrollment::factory()->create();
        $this->expectException(QueryException::class);
        DB::transaction(fn () => Enrollment::factory()->create(['offering_id' => $enrollment->offering_id, 'student_id' => $enrollment->student_id]));
    }

    public function test_duplicate_attendance_is_rejected_by_postgresql(): void
    {
        $session = ClassSession::factory()->create();
        $enrollment = Enrollment::factory()->create(['offering_id' => $session->offering_id]);
        $data = ['offering_id' => $session->offering_id, 'session_id' => $session->id, 'enrollment_id' => $enrollment->id, 'recorded_by' => $session->offering->teacher_id, 'recorded_at' => now(), 'status' => 'present', 'method' => 'manual'];
        Attendance::create($data);
        $this->expectException(QueryException::class);
        DB::transaction(fn () => Attendance::create($data));
    }

    public function test_attendance_cannot_use_an_enrollment_from_another_group(): void
    {
        $session = ClassSession::factory()->create();
        $enrollment = Enrollment::factory()->create();
        $this->expectException(QueryException::class);
        DB::transaction(fn () => Attendance::create(['offering_id' => $session->offering_id, 'session_id' => $session->id, 'enrollment_id' => $enrollment->id, 'recorded_by' => $session->offering->teacher_id, 'recorded_at' => now(), 'status' => 'present', 'method' => 'manual']));
    }

    public function test_cancelled_session_requires_a_reason(): void
    {
        $this->expectException(QueryException::class);
        DB::transaction(fn () => ClassSession::factory()->create(['state' => 'cancelled', 'change_reason' => null]));
    }

    public function test_unknown_role_is_rejected_by_postgresql(): void
    {
        $user = User::factory()->create();
        $this->expectException(QueryException::class);
        DB::transaction(fn () => DB::table('users')->where('id', $user->id)->update(['role' => 'superuser']));
    }
}
