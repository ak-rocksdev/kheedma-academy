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

    /** Intake window currently open (registration accepted right now). */
    public function openWindow(): static
    {
        return $this->state(fn () => [
            'registration_opens_at' => now()->subWeek(),
            'registration_closes_at' => now()->addWeek(),
        ]);
    }

    /** Intake window already closed. */
    public function closedWindow(): static
    {
        return $this->state(fn () => [
            'registration_opens_at' => now()->subMonth(),
            'registration_closes_at' => now()->subDay(),
        ]);
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
