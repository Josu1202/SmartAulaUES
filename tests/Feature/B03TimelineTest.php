<?php

namespace Tests\Feature;

use App\Models\AssessmentPeriod;
use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Offering;
use App\Models\User;
use Tests\TestCase;

final class B03TimelineTest extends TestCase
{
    private function login(User $actor): static
    {
        return $this->withSession(['password_hash_web' => $actor->password])->actingAs($actor);
    }

    public function test_student_history_is_own_only_and_zero_eligible_is_not_a_percentage(): void
    {
        $this->travelTo(now()->setDateTime(2026, 11, 7, 14, 0));
        $group = Offering::factory()->create();
        $student = User::factory()->create();
        $other = User::factory()->create(['name' => 'OTRO_ESTUDIANTE_SECRETO']);
        $mine = Enrollment::factory()->create(['offering_id' => $group->id, 'student_id' => $student->id]);
        $foreign = Enrollment::factory()->create(['offering_id' => $group->id, 'student_id' => $other->id]);
        $period = AssessmentPeriod::factory()->create(['offering_id' => $group->id]);
        $this->login($student)->get(route('history.index', ['enrollment_id' => $mine->id, 'period_id' => $period->id]))
            ->assertOk()->assertSee('No existen sesiones evaluables')->assertDontSee('OTRO_ESTUDIANTE_SECRETO');
        $this->get(route('history.index', ['enrollment_id' => $foreign->id]))->assertNotFound();
    }

    public function test_calendar_denies_future_sessions_after_withdrawal(): void
    {
        $this->travelTo(now()->setDateTime(2026, 11, 7, 14, 0));
        $group = Offering::factory()->create();
        $period = AssessmentPeriod::factory()->create(['offering_id' => $group->id]);
        $student = User::factory()->create();
        Enrollment::factory()->create(['offering_id' => $group->id, 'student_id' => $student->id,
            'active_from' => '2026-01-01', 'active_to' => '2026-11-07']);
        ClassSession::factory()->create(['offering_id' => $group->id, 'period_id' => $period->id,
            'starts_at' => '2026-11-08T16:00:00Z', 'ends_at' => '2026-11-08T17:00:00Z']);
        $this->login($student)->get(route('calendar.index', ['from' => '2026-11-07', 'to' => '2026-11-10']))
            ->assertOk()->assertSee('Sin sesiones en este intervalo.');
    }

    public function test_teacher_calendar_is_restricted_to_currently_assigned_groups(): void
    {
        $this->travelTo(now()->setDateTime(2026, 11, 7, 14, 0));
        $teacher = User::factory()->teacher()->create();
        $own = Offering::factory()->create(['teacher_id' => $teacher->id]);
        $foreign = Offering::factory()->create();
        $periodA = AssessmentPeriod::factory()->create(['offering_id' => $own->id]);
        $periodB = AssessmentPeriod::factory()->create(['offering_id' => $foreign->id]);
        ClassSession::factory()->create(['offering_id' => $own->id, 'period_id' => $periodA->id,
            'starts_at' => '2026-11-08T16:00:00Z', 'ends_at' => '2026-11-08T17:00:00Z']);
        ClassSession::factory()->create(['offering_id' => $foreign->id, 'period_id' => $periodB->id,
            'starts_at' => '2026-11-08T17:00:00Z', 'ends_at' => '2026-11-08T18:00:00Z']);
        $this->login($teacher)->get(route('calendar.index', ['from' => '2026-11-07', 'to' => '2026-11-10']))
            ->assertOk()->assertSee($own->subject->name)->assertDontSee($foreign->subject->name);
        $this->get(route('calendar.index', ['teacher_id' => $foreign->teacher_id]))->assertForbidden();
    }
}
