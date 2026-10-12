<?php

namespace Database\Factories;

use App\Models\Offering;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class EnrollmentFactory extends Factory
{
    public function definition(): array
    {
        return ['offering_id' => Offering::factory(), 'student_id' => User::factory(), 'active_from' => '2026-01-01', 'active_to' => null];
    }
}
