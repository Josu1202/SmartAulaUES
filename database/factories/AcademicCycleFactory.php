<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class AcademicCycleFactory extends Factory
{
    public function definition(): array
    {
        return ['code' => fake()->unique()->bothify('TEST-????'), 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31', 'status' => 'active'];
    }
}
