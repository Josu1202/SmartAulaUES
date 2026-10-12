<?php

namespace Database\Factories;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => fake()->name(), 'email' => fake()->unique()->safeEmail(), 'password' => 'Test-password-2026!', 'role' => Role::Student, 'active' => true, 'remember_token' => Str::random(10)];
    }

    public function coordinator(): static
    {
        return $this->state(fn () => ['role' => Role::Coordinator]);
    }

    public function teacher(): static
    {
        return $this->state(fn () => ['role' => Role::Teacher]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['active' => false]);
    }
}
