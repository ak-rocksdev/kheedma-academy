<?php

namespace Database\Factories;

use App\Models\Assignment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Assignment>
 */
class AssignmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => 'Tugas '.fake()->unique()->numberBetween(1, 999),
            'body' => fake()->paragraph(),
        ];
    }
}
