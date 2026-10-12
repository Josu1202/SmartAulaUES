<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    public function test_guest_can_open_login_and_cannot_open_dashboard(): void
    {
        $this->get('/login')->assertOk()->assertSee('Bienvenido a SIAA');
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_active_user_can_login_and_logout(): void
    {
        $user = User::factory()->coordinator()->create();
        $this->post('/login', ['email' => $user->email, 'password' => 'Test-password-2026!'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_wrong_password_does_not_authenticate(): void
    {
        $user = User::factory()->create();
        $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_inactive_user_cannot_login(): void
    {
        $user = User::factory()->inactive()->create();
        $this->post('/login', ['email' => $user->email, 'password' => 'Test-password-2026!'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_deactivated_session_is_logged_out(): void
    {
        $user = User::factory()->inactive()->create();
        $this->actingAs($user)->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_public_registration_is_disabled(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', [])->assertNotFound();
    }

    public function test_login_is_rate_limited_after_five_attempts(): void
    {
        $user = User::factory()->create();
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertStatus(302);
        }
        $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertStatus(429);
    }

    public function test_responses_include_security_headers(): void
    {
        $this->get('/login')->assertHeader('X-Frame-Options', 'DENY')->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('Permissions-Policy', 'nfc=(self), camera=(), microphone=(), geolocation=()');
    }
}
