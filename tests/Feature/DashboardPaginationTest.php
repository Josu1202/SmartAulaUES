<?php

namespace Tests\Feature;

use App\Models\AcademicCycle;
use App\Models\AssessmentPeriod;
use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Offering;
use App\Models\User;
use App\Queries\DashboardStatistics;
use Tests\TestCase;

class DashboardPaginationTest extends TestCase
{
    public function test_dashboard_bounds_loaded_groups_but_keeps_totals_and_upcoming_sessions_from_all_pages(): void
    {
        $this->travelTo(now()->setDateTime(2026, 10, 24, 12, 0));
        $admin = User::factory()->coordinator()->create();
        $teacher = User::factory()->teacher()->create();
        $cycle = AcademicCycle::factory()->create();
        $groups = Offering::factory()->count(14)->create(['cycle_id' => $cycle->id, 'teacher_id' => $teacher->id]);
        foreach ($groups as $group) {
            Enrollment::factory()->create(['offering_id' => $group->id]);
        }
        $last = $groups->last();
        $period = AssessmentPeriod::factory()->create(['offering_id' => $last->id]);
        $session = ClassSession::factory()->create(['offering_id' => $last->id, 'period_id' => $period->id, 'starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addHour()]);
        $this->actingAs($admin)->get('/dashboard')->assertOk()
            ->assertDontSee('pagination.')->assertDontSee('Showing')->assertSee('Mostrando')
            ->assertViewHas('offerings', fn ($page) => $page->count() === 6 && $page->total() === 14)
            ->assertViewHas('sessions', fn ($sessions) => $sessions->modelKeys() === [$session->id]);
        $this->get('/dashboard?groups_page=3')->assertOk()->assertViewHas('offerings', fn ($page) => $page->count() === 2 && $page->total() === 14);
        $this->actingAs($teacher)->withSession(['password_hash_web' => $teacher->password])->get('/dashboard')->assertOk()->assertViewHas('studentCount', 14);
    }

    public function test_graduates_and_unassigned_years_have_independent_counts(): void
    {
        $admin = User::factory()->coordinator()->create();
        User::factory()->count(3)->create(['graduated' => true, 'study_year' => null]);
        User::factory()->count(2)->create(['graduated' => false, 'study_year' => null]);
        User::factory()->create(['study_year' => 1]);
        User::factory()->inactive()->create(['graduated' => true]);
        $this->assertEquals(['1º año' => 1, 'Sin asignar' => 2, 'Egresados' => 3], app(DashboardStatistics::class)->forUser($admin)['years']);
    }
}
