<?php

namespace Database\Factories;

use App\Models\CohortSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CohortSession>
 */
class CohortSessionFactory extends Factory
{
    public function definition(): array
    {
        static $counter = 0;
        $counter++;

        return [
            'title' => 'Sesi '.$counter,
            'scheduled_at' => now()->addWeeks($counter),
            'position' => $counter,
        ];
    }
}
