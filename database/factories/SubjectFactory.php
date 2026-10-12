<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class SubjectFactory extends Factory
{
    public function definition(): array
    {
        return ['code' => fake()->unique()->bothify('SUB-????'), 'name' => fake()->words(3, true), 'active' => true];
    }
}
