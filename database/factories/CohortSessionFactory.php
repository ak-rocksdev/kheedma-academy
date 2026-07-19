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

    public function online(): static
    {
        return $this->state(fn () => [
            'type' => 'online',
            'meeting_url' => 'https://meet.google.com/'.fake()->lexify('???-????-???'),
        ]);
    }

    public function atLocation(): static
    {
        return $this->state(fn () => [
            'type' => 'offline',
            'location_name' => 'Kantor Kheedma Indonesia',
            'location_address' => 'Jl. Kapten Mulyadi, Pasar Kliwon, Surakarta',
            'location_lat' => -7.5755,
            'location_lng' => 110.8317,
        ]);
    }
}
