<?php

namespace Database\Factories;

use App\Models\Offering;
use Illuminate\Database\Eloquent\Factories\Factory;

class AssessmentPeriodFactory extends Factory
{
    public function definition(): array
    {
        return ['offering_id' => Offering::factory(), 'number' => 1, 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31', 'state' => 'open', 'policy_version' => 'demo-2026-v1'];
    }
}
