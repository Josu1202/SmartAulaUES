<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InitialCoordinatorTest extends TestCase
{
    public function test_first_account_is_explicit_and_audited_without_recording_its_password(): void
    {
        $this->artisan('app:create-coordinator', ['--name' => 'Coordinadora de carrera', '--email' => 'coordinacion@example.test'])
            ->expectsQuestion('Contraseña (mínimo 12 caracteres)', 'Private-first-password!')
            ->expectsQuestion('Confirma la contraseña', 'Private-first-password!')->assertSuccessful();
        $user = User::sole();
        $this->assertSame('coordinator', $user->role->value);
        $this->assertTrue(Hash::check('Private-first-password!', $user->password));
        $this->assertSame($user->id, AuditLog::sole()->actor_id);
        $this->assertStringNotContainsString('Private-first-password!', AuditLog::sole()->toJson());
        $this->artisan('app:create-coordinator')->assertFailed();
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_initial_password_confirmation_must_match(): void
    {
        $this->artisan('app:create-coordinator', ['--name' => 'Coordinadora', '--email' => 'coordinacion@example.test'])
            ->expectsQuestion('Contraseña (mínimo 12 caracteres)', 'Private-first-password!')
            ->expectsQuestion('Confirma la contraseña', 'Other-first-password!')->assertFailed();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_initial_password_keeps_the_same_safe_length_limit(): void
    {
        $this->expectException(ValidationException::class);
        try {
            $password = str_repeat('ñ', 37);
            $this->artisan('app:create-coordinator', ['--name' => 'Coordinadora', '--email' => 'coordinacion@example.test'])
                ->expectsQuestion('Contraseña (mínimo 12 caracteres)', $password)
                ->expectsQuestion('Confirma la contraseña', $password)->run();
        } finally {
            $this->assertDatabaseCount('users', 0);
        }
    }
}
