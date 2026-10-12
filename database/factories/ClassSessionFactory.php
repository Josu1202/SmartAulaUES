<?php

namespace Database\Factories;

use App\Models\AssessmentPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClassSessionFactory extends Factory
{
    public function definition(): array
    {
        return ['period_id' => AssessmentPeriod::factory(), 'offering_id' => fn (array $attributes) => AssessmentPeriod::findOrFail($attributes['period_id'])->offering_id, 'starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addMinutes(100), 'state' => 'scheduled', 'modality' => 'in_person'];
    }
}
