<?php

namespace Database\Factories;

use App\Models\AcademicCycle;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OfferingFactory extends Factory
{
    public function definition(): array
    {
        return ['cycle_id' => AcademicCycle::factory(), 'subject_id' => Subject::factory(), 'teacher_id' => User::factory()->teacher(), 'group_code' => '01', 'capacity' => 30];
    }
}
