<?php

namespace Database\Factories;

use App\Models\Cohort;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cohort>
 */
class CohortFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Cohort '.fake()->unique()->numberBetween(1, 999),
            'start_date' => now()->addWeek()->toDateString(),
            'end_date' => now()->addMonths(2)->toDateString(),
            'mentor_id' => null,
        ];
    }
}
