<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Cohort;
use App\Models\CohortSession;
use App\Models\Enrollment;
use App\Models\Person;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssignmentModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_assignment_relations_round_trip(): void
    {
        $session = CohortSession::factory()->for(Cohort::factory())->create();
        $assignment = Assignment::factory()->for($session, 'session')->create();
        // No Person/Enrollment factories exist in this project — tests build
        // them with create(), same as ProgramEligibilityTest.
        $person = Person::create([
            'name' => 'Peserta '.fake()->unique()->numberBetween(1, 99999),
            'phone' => '+628'.fake()->unique()->numerify('##########'),
            'email' => fake()->unique()->safeEmail(),
        ]);
        $enrollment = Enrollment::create(['people_id' => $person->id, 'cohort_id' => $session->cohort_id]);
        $submission = AssignmentSubmission::factory()->create([
            'assignment_id' => $assignment->id,
            'enrollment_id' => $enrollment->id,
        ]);

        $this->assertTrue($session->assignment->is($assignment));
        $this->assertTrue($assignment->session->is($session));
        $this->assertTrue($submission->assignment->is($assignment));
        $this->assertTrue($enrollment->assignmentSubmissions->first()->is($submission));
    }

    public function test_one_assignment_per_session(): void
    {
        $session = CohortSession::factory()->for(Cohort::factory())->create();
        Assignment::factory()->for($session, 'session')->create();

        $this->expectException(QueryException::class);
        Assignment::factory()->for($session, 'session')->create();
    }
}
