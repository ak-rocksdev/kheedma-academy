<?php

namespace Database\Factories;

use App\Models\AssignmentSubmission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssignmentSubmission>
 */
class AssignmentSubmissionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'url' => 'https://drive.google.com/'.fake()->uuid(),
        ];
    }

    /** Graded by an unspecified admin; pass score via create() to control it. */
    public function graded(int $score): static
    {
        return $this->state(fn () => [
            'score' => $score,
            'graded_at' => now(),
        ]);
    }
}
