<?php

namespace Tests\Feature;

use App\Models\Cohort;
use App\Models\CohortSession;
use App\Models\Enrollment;
use App\Models\Person;
use App\Models\SessionConfirmation;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SessionConfirmationModelTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: CohortSession, 1: Enrollment} */
    private function sessionAndEnrollment(): array
    {
        $cohort = Cohort::factory()->create();
        $session = CohortSession::factory()->for($cohort)->create();
        $person = Person::create([
            'name' => 'Peserta Uji',
            'phone' => '+628'.fake()->unique()->numerify('##########'),
            'email' => fake()->unique()->safeEmail(),
        ]);
        $enrollment = Enrollment::create(['people_id' => $person->id, 'cohort_id' => $cohort->id]);

        return [$session, $enrollment];
    }

    public function test_confirmation_belongs_to_session_and_enrollment(): void
    {
        [$session, $enrollment] = $this->sessionAndEnrollment();
        $confirmation = SessionConfirmation::factory()->create([
            'cohort_session_id' => $session->id,
            'enrollment_id' => $enrollment->id,
        ]);

        $this->assertTrue($confirmation->session->is($session));
        $this->assertTrue($confirmation->enrollment->is($enrollment));
        $this->assertTrue($session->confirmations()->first()->is($confirmation));
        $this->assertTrue($enrollment->sessionConfirmations()->first()->is($confirmation));
    }

    public function test_one_row_per_class_and_enrollment(): void
    {
        [$session, $enrollment] = $this->sessionAndEnrollment();
        SessionConfirmation::factory()->create([
            'cohort_session_id' => $session->id,
            'enrollment_id' => $enrollment->id,
        ]);

        $this->expectException(QueryException::class);
        SessionConfirmation::factory()->create([
            'cohort_session_id' => $session->id,
            'enrollment_id' => $enrollment->id,
        ]);
    }
}
