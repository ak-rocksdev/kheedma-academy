<?php

namespace Database\Factories;

use App\Models\Program;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContentSectionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'page' => 'community',
            'program_id' => null,
            'heading' => fake()->sentence(3),
            'body' => '<p>'.fake()->paragraph().'</p>',
            'sort_order' => 0,
        ];
    }

    public function forProgram(?Program $program = null): static
    {
        return $this->state(fn () => [
            'page' => 'program',
            'program_id' => $program?->id ?? Program::factory(),
        ]);
    }
}
