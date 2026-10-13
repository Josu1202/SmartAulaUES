<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    public function test_demo_has_expected_counts_and_can_be_seeded_twice(): void
    {
        config(['smartaulaues.demo_enabled' => true, 'smartaulaues.demo_password' => 'Seed-password-2026!']);
        $this->seed(DemoSeeder::class);
        $this->seed(DemoSeeder::class);
        $this->assertDatabaseCount('users', 23);
        $this->assertDatabaseCount('subjects', 2);
        $this->assertDatabaseCount('offerings', 2);
        $this->assertDatabaseCount('enrollments', 40);
        $this->assertDatabaseCount('class_sessions', 8);
        $this->assertDatabaseCount('attendances', 0);
        $this->assertDatabaseCount('nfc_credentials', 0);
    }

    public function test_reseeding_does_not_overwrite_existing_passwords(): void
    {
        config(['smartaulaues.demo_enabled' => true, 'smartaulaues.demo_password' => 'Seed-password-2026!']);
        $this->seed(DemoSeeder::class);
        $user = User::where('email', 'coordinacion@siaa.test')->firstOrFail();
        $user->update(['password' => 'Changed-password-2026!']);
        $this->seed(DemoSeeder::class);
        $this->assertTrue(Hash::check('Changed-password-2026!', $user->fresh()->password));
    }

    public function test_demo_cannot_run_in_production(): void
    {
        config(['smartaulaues.demo_enabled' => true, 'smartaulaues.demo_password' => 'Seed-password-2026!']);
        app()->instance('env', 'production');
        $this->expectException(RuntimeException::class);
        app(DemoSeeder::class)->run();
    }

    public function test_demo_requires_an_explicit_password(): void
    {
        config(['smartaulaues.demo_enabled' => true, 'smartaulaues.demo_password' => '']);
        $this->expectException(RuntimeException::class);
        app(DemoSeeder::class)->run();
    }
}
