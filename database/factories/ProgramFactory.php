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
            'type' => 'general',
            'status' => 'draft',
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

    public function affiliate(int $level = 1): static
    {
        return $this->state(fn () => ['type' => 'affiliate_community', 'level' => $level]);
    }
}
