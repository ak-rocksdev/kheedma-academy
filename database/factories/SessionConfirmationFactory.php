<?php

namespace Database\Factories;

use App\Models\SessionConfirmation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SessionConfirmation>
 */
class SessionConfirmationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'status' => 'attending',
            'note' => null,
        ];
    }

    public function cannotAttend(?string $note = null): static
    {
        return $this->state(fn () => ['status' => 'cannot_attend', 'note' => $note]);
    }
}
