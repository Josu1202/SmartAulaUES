<?php

namespace Tests\Feature;

use App\Models\AcademicCycle;
use App\Models\AssessmentPeriod;
use App\Models\Attendance;
use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Offering;
use App\Models\User;
use Tests\TestCase;

class TeacherAcademicFiltersTest extends TestCase
{
    private function asUser(User $user): static
    {
        return $this->withSession(['password_hash_web' => $user->password])->actingAs($user);
    }

    public function test_teacher_can_select_own_historical_cycle_without_exposing_other_groups(): void
    {
        $this->travelTo(now()->setDate(2026, 6, 15)->setTime(18, 0));
        $teacher = User::factory()->teacher()->create();
        $current = Offering::factory()->create(['teacher_id' => $teacher->id]);
        $history = AcademicCycle::factory()->create(['status' => 'closed']);
        $archived = Offering::factory()->create(['teacher_id' => $teacher->id, 'cycle_id' => $history->id, 'active' => false]);
        $foreign = Offering::factory()->create(['cycle_id' => $history->id]);

        $default = $this->asUser($teacher)->get('/dashboard');
        $default->assertOk();
        $this->assertContains($current->id, $default->viewData('absenceRanking')['groups']->pluck('id')->all());
        $this->assertNotContains($archived->id, $default->viewData('absenceRanking')['groups']->pluck('id')->all());

        $filtered = $this->get('/dashboard?ranking_cycle_id='.$history->id);
        $filtered->assertOk()->assertSee('Ciclo académico')->assertSee('Histórico');
        $groups = $filtered->viewData('absenceRanking')['groups']->pluck('id')->all();
        $this->assertSame([$archived->id], $groups);
        $this->assertNotContains($foreign->id, $groups);
        $this->assertSame($history->id, $filtered->viewData('absenceRanking')['cycle']->id);
    }

    public function test_teacher_cannot_request_a_foreign_cycle_or_a_group_from_another_cycle(): void
    {
        $this->travelTo(now()->setDate(2026, 6, 15)->setTime(18, 0));
        $teacher = User::factory()->teacher()->create();
        $own = Offering::factory()->create(['teacher_id' => $teacher->id]);
        $ownOtherCycle = Offering::factory()->create(['teacher_id' => $teacher->id]);
        $foreign = Offering::factory()->create();

        $this->asUser($teacher)->get('/dashboard?ranking_cycle_id='.$foreign->cycle_id)->assertNotFound();
        $this->get('/dashboard?ranking_cycle_id='.$own->cycle_id.'&ranking_group_id='.$ownOtherCycle->id)->assertNotFound();
        $this->get('/dashboard?ranking_cycle_id='.$own->cycle_id.'&ranking_group_id='.$foreign->id)->assertNotFound();
    }

    public function test_teacher_can_order_by_unrounded_rate_without_changing_absence_counts(): void
    {
        $this->travelTo(now()->setDate(2026, 6, 15)->setTime(18, 0));
        $period = AssessmentPeriod::factory()->create();
        $teacher = $period->offering->teacher;
        $alicia = Enrollment::factory()->create(['offering_id' => $period->offering_id, 'active_from' => '2026-01-01']);
        $bruno = Enrollment::factory()->create(['offering_id' => $period->offering_id, 'active_from' => '2026-06-03']);
        $alicia->student->update(['name' => 'Alicia Conteo']);
        $bruno->student->update(['name' => 'Bruno Tasa']);

        foreach (['2026-06-01', '2026-06-02', '2026-06-03'] as $number => $date) {
            $session = ClassSession::factory()->create([
                'offering_id' => $period->offering_id,
                'period_id' => $period->id,
                'state' => 'completed',
                'starts_at' => $date.'T16:00:00Z',
                'ends_at' => $date.'T17:00:00Z',
            ]);
            Attendance::create([
                'offering_id' => $period->offering_id,
                'session_id' => $session->id,
                'enrollment_id' => $alicia->id,
                'status' => $number < 2 ? 'absent' : 'present',
                'recorded_by' => $period->offering->teacher_id,
                'recorded_at' => now(),
            ]);
            if ($number === 2) {
                Attendance::create([
                    'offering_id' => $period->offering_id,
                    'session_id' => $session->id,
                    'enrollment_id' => $bruno->id,
                    'status' => 'absent',
                    'recorded_by' => $period->offering->teacher_id,
                    'recorded_at' => now(),
                ]);
            }
        }

        $url = '/dashboard?ranking_group_id='.$period->offering_id.'&ranking_period_id='.$period->id;
        $byCount = $this->asUser($teacher)->get($url);
        $byCount->assertOk();
        $this->assertSame([$alicia->id, $bruno->id], array_column($byCount->viewData('absenceRanking')['rows'], 'enrollment_id'));
        $this->assertSame([2, 1], array_column($byCount->viewData('absenceRanking')['rows'], 'absent'));

        $byRate = $this->get($url.'&ranking_sort=rate');
        $byRate->assertOk()->assertSee('Tasa de inasistencia');
        $this->assertSame([$bruno->id, $alicia->id], array_column($byRate->viewData('absenceRanking')['rows'], 'enrollment_id'));
        $this->assertSame(['100.00', '66.67'], array_column($byRate->viewData('absenceRanking')['rows'], 'absence_percentage'));
    }

    public function test_invalid_sort_value_is_rejected_without_falling_back_silently(): void
    {
        $teacher = User::factory()->teacher()->create();
        $this->asUser($teacher)->getJson('/dashboard?ranking_sort=unknown')->assertUnprocessable();
    }
}
