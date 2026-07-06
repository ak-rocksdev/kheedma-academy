<?php

namespace Database\Factories;

use App\Models\Program;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Program>
 */
class ProgramFactory extends Factory
{
    public function definition(): array
    {
        $name = 'Program '.fake()->unique()->words(2, true);

        return [
            'slug' => Str::slug($name),
            'name' => $name,
            'tagline' => fake()->sentence(6),
            'description' => fake()->paragraph(),
            'status' => 'draft',
            'registration_opens_at' => null,
            'registration_closes_at' => null,
            'selection_mode' => 'selective',
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => ['status' => 'active']);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => 'inactive']);
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => 'draft']);
    }

    /** Active but its registration window has already closed. */
    public function windowClosed(): static
    {
        return $this->state(fn () => [
            'status' => 'active',
            'registration_opens_at' => now()->subMonth(),
            'registration_closes_at' => now()->subDay(),
        ]);
    }
}
