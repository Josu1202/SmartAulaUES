<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Help\TutorialCatalog;
use App\Services\Help\TutorialProgressService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class B04TutorialTest extends TestCase
{
    private function login(User $user): static
    {
        return $this->withSession(['password_hash_web' => $user->password])->actingAs($user);
    }

    public function test_first_access_offers_coordinator_tour_with_real_authorized_destinations(): void
    {
        $coordinator = User::factory()->coordinator()->create();
        $this->login($coordinator)->get(route('help.index'))->assertOk()
            ->assertSee('data-tour-auto', false)
            ->assertSee('Directorios')->assertSee('Formatos de ciclos')
            ->assertSee('Cierres académicos')->assertSee('Reportes y versiones');
        $this->assertCount(5, app(TutorialCatalog::class)->forRole($coordinator->role));
    }

    public function test_teacher_tour_has_six_steps_and_no_coordinator_actions(): void
    {
        $teacher = User::factory()->teacher()->create();
        $this->login($teacher)->get(route('help.index'))->assertOk()
            ->assertSee('Métodos de asistencia')->assertSee('Seguimiento')
            ->assertSee('Exportar resultados')->assertDontSee('Formatos de ciclos');
        $this->assertCount(6, app(TutorialCatalog::class)->forRole($teacher->role));
    }

    public function test_student_tour_only_explains_student_actions(): void
    {
        $student = User::factory()->create();
        $this->login($student)->get(route('help.index'))->assertOk()
            ->assertSee('Historial académico')->assertSee('Avisos y noticias')
            ->assertDontSee('Reportes y versiones')->assertDontSee('Métodos de asistencia');
        $this->assertCount(5, app(TutorialCatalog::class)->forRole($student->role));
    }

    public function test_skipping_is_persisted_and_does_not_reappear_on_next_request(): void
    {
        $student = User::factory()->create();
        $this->login($student)->get(route('help.index'))->assertSee('data-tour-auto');
        $this->post(route('help.tour.skip'))->assertRedirect();
        $this->assertDatabaseHas('user_tutorial_progress', [
            'user_id' => $student->id,
            'role' => 'student',
            'version' => TutorialCatalog::VERSION,
            'status' => 'skipped',
        ]);
        $this->get(route('help.index'))->assertOk()->assertDontSee('data-tour-auto');
        $this->assertDatabaseCount('user_tutorial_progress', 1);
    }

    public function test_completion_is_persisted_and_replay_does_not_erase_progress(): void
    {
        $teacher = User::factory()->teacher()->create();
        $this->login($teacher)->post(route('help.tour.complete'))->assertRedirect();
        $this->get(route('help.index'))->assertOk()->assertSee('Completado')->assertDontSee('data-tour-auto');
        $this->get(route('help.index'))->assertOk()->assertSee('data-tour-open')->assertDontSee('data-tour-auto');
        $this->assertDatabaseHas('user_tutorial_progress', [
            'user_id' => $teacher->id,
            'role' => 'teacher',
            'version' => TutorialCatalog::VERSION,
            'status' => 'completed',
        ]);
    }

    public function test_tour_state_belongs_only_to_its_owner_and_is_versioned(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        $this->login($first)->post(route('help.tour.skip'))->assertRedirect();
        $this->login($second)->get(route('help.index'))->assertOk()->assertSee('data-tour-auto');
        $this->assertTrue(app(TutorialProgressService::class)->shouldOffer($second));
        DB::table('user_tutorial_progress')->where('user_id', $first->id)->update(['version' => 0]);
        $this->assertTrue(app(TutorialProgressService::class)->shouldOffer($first));
    }

    public function test_provisional_password_screen_does_not_offer_the_tour(): void
    {
        $user = User::factory()->create(['must_change_password' => true]);
        $this->login($user)->get(route('profile'))->assertOk()->assertDontSee('data-tour-auto');
        $this->assertDatabaseCount('user_tutorial_progress', 0);
    }

    public function test_tour_endpoints_require_an_active_authenticated_user(): void
    {
        $this->post(route('help.tour.complete'))->assertRedirect();
        $inactive = User::factory()->inactive()->create();
        $this->login($inactive)->post(route('help.tour.skip'))->assertRedirect(route('login'));
        $this->assertDatabaseCount('user_tutorial_progress', 0);
    }
}
