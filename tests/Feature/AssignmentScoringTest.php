<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Cohort;
use App\Models\CohortSession;
use App\Models\Enrollment;
use App\Models\Person;
use App\Models\Program;
use App\Models\StatusEvent;
use App\Support\AssignmentScoring;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssignmentScoringTest extends TestCase
{
    use RefreshDatabase;

    private AssignmentScoring $scoring;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scoring = new AssignmentScoring;
    }

    private function makePerson(): Person
    {
        return Person::create([
            'name' => 'Peserta '.fake()->unique()->numberBetween(1, 99999),
            'phone' => '+628'.fake()->unique()->numerify('##########'),
            'email' => fake()->unique()->safeEmail(),
        ]);
    }

    /** @return array{Program, Cohort, Person, Enrollment} */
    private function programWithEnrollment(?int $threshold = 75): array
    {
        $program = Program::factory()->create(['min_average_score' => $threshold]);
        $cohort = Cohort::factory()->create(['program_id' => $program->id]);
        $person = $this->makePerson();
        $enrollment = Enrollment::create(['people_id' => $person->id, 'cohort_id' => $cohort->id]);

        return [$program, $cohort, $person, $enrollment];
    }

    private function assignmentIn(Cohort $cohort): Assignment
    {
        return Assignment::factory()
            ->for(CohortSession::factory()->for($cohort)->create(), 'session')
            ->create();
    }

    public function test_effective_score_is_the_latest_graded_submission(): void
    {
        [, $cohort, , $enrollment] = $this->programWithEnrollment();
        $assignment = $this->assignmentIn($cohort);

        AssignmentSubmission::factory()->graded(60)->create(['assignment_id' => $assignment->id, 'enrollment_id' => $enrollment->id]);
        AssignmentSubmission::factory()->graded(85)->create(['assignment_id' => $assignment->id, 'enrollment_id' => $enrollment->id]);
        // A newer, not-yet-graded retake must NOT erase the standing grade.
        AssignmentSubmission::factory()->create(['assignment_id' => $assignment->id, 'enrollment_id' => $enrollment->id]);

        $this->assertSame(85, $this->scoring->effectiveScore($assignment, $enrollment));
    }

    public function test_submission_state_derivation(): void
    {
        [, $cohort, , $enrollment] = $this->programWithEnrollment();
        $assignment = $this->assignmentIn($cohort);

        $this->assertSame('belum_dikerjakan', $this->scoring->submissionState($assignment, $enrollment));

        AssignmentSubmission::factory()->create(['assignment_id' => $assignment->id, 'enrollment_id' => $enrollment->id]);
        $this->assertSame('menunggu_dinilai', $this->scoring->submissionState($assignment, $enrollment));

        AssignmentSubmission::factory()->graded(70)->create(['assignment_id' => $assignment->id, 'enrollment_id' => $enrollment->id]);
        $this->assertSame('dinilai', $this->scoring->submissionState($assignment, $enrollment));
    }

    public function test_average_counts_missing_assignments_as_zero(): void
    {
        [$program, $cohort, $person, $enrollment] = $this->programWithEnrollment();
        $graded = $this->assignmentIn($cohort);
        $this->assignmentIn($cohort); // never submitted -> 0

        AssignmentSubmission::factory()->graded(80)->create(['assignment_id' => $graded->id, 'enrollment_id' => $enrollment->id]);

        $this->assertSame(40.0, $this->scoring->averageFor($person, $program));
    }

    public function test_average_spans_multiple_enrollments_of_the_program(): void
    {
        // Legacy shape: two single-class cohorts in one program.
        [$program, $cohortA, $person, $enrollmentA] = $this->programWithEnrollment();
        $cohortB = Cohort::factory()->create(['program_id' => $program->id]);
        $enrollmentB = Enrollment::create(['people_id' => $person->id, 'cohort_id' => $cohortB->id]);

        $a = $this->assignmentIn($cohortA);
        $b = $this->assignmentIn($cohortB);
        AssignmentSubmission::factory()->graded(70)->create(['assignment_id' => $a->id, 'enrollment_id' => $enrollmentA->id]);
        AssignmentSubmission::factory()->graded(90)->create(['assignment_id' => $b->id, 'enrollment_id' => $enrollmentB->id]);

        $this->assertSame(80.0, $this->scoring->averageFor($person, $program));
    }

    public function test_average_rounds_half_up_to_one_decimal(): void
    {
        [$program, $cohort, $person, $enrollment] = $this->programWithEnrollment();
        foreach ([74, 75, 75, 75] as $score) {
            $assignment = $this->assignmentIn($cohort);
            AssignmentSubmission::factory()->graded($score)->create(['assignment_id' => $assignment->id, 'enrollment_id' => $enrollment->id]);
        }

        // (74 + 75 + 75 + 75) / 4 = 74.75, which rounds half-up to 74.8 -
        // and must still fall short of a 75 gate.
        $this->assertSame(74.8, $this->scoring->averageFor($person, $program));
        $this->assertFalse($this->scoring->passes($person, $program));
    }

    public function test_below_threshold_average_does_not_pass(): void
    {
        [$program, $cohort, $person, $enrollment] = $this->programWithEnrollment(threshold: 75);
        $assignment = $this->assignmentIn($cohort);
        AssignmentSubmission::factory()->graded(74)->create(['assignment_id' => $assignment->id, 'enrollment_id' => $enrollment->id]);

        $this->assertSame(74.0, $this->scoring->averageFor($person, $program));
        $this->assertFalse($this->scoring->passes($person, $program));

        // Exactly at the threshold does pass.
        [$programAtBar, $cohortAtBar, $personAtBar, $enrollmentAtBar] = $this->programWithEnrollment(threshold: 75);
        $assignmentAtBar = $this->assignmentIn($cohortAtBar);
        AssignmentSubmission::factory()->graded(75)->create(['assignment_id' => $assignmentAtBar->id, 'enrollment_id' => $enrollmentAtBar->id]);

        $this->assertTrue($this->scoring->passes($personAtBar, $programAtBar));
    }

    public function test_dropped_enrollment_is_excluded(): void
    {
        [$program, $cohort, $person, $enrollment] = $this->programWithEnrollment();
        $assignment = $this->assignmentIn($cohort);
        AssignmentSubmission::factory()->graded(90)->create(['assignment_id' => $assignment->id, 'enrollment_id' => $enrollment->id]);
        StatusEvent::create(['enrollment_id' => $enrollment->id, 'status' => 'dropped', 'occurred_at' => now()]);

        $this->assertNull($this->scoring->averageFor($person, $program));
        $this->assertFalse($this->scoring->passes($person, $program));
    }

    public function test_passes_requires_threshold_and_assignments(): void
    {
        // No threshold -> never passes by score.
        [$programNoBar, $cohortNoBar, $personA, $enrollmentA] = $this->programWithEnrollment(threshold: null);
        $assignment = $this->assignmentIn($cohortNoBar);
        AssignmentSubmission::factory()->graded(100)->create(['assignment_id' => $assignment->id, 'enrollment_id' => $enrollmentA->id]);
        $this->assertFalse($this->scoring->passes($personA, $programNoBar));

        // Threshold but zero assignments -> average null -> no pass (fallback signal).
        [$programBar, , $personB] = $this->programWithEnrollment(threshold: 75);
        $this->assertNull($this->scoring->averageFor($personB, $programBar));
        $this->assertFalse($this->scoring->passes($personB, $programBar));
    }
}
