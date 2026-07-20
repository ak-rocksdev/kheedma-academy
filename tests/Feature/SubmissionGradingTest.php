<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Cohort;
use App\Models\CohortSession;
use App\Models\Enrollment;
use App\Models\Person;
use App\Models\Program;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubmissionGradingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    private function mentor(): User
    {
        $user = User::factory()->create();
        $user->assignRole('mentor');

        return $user;
    }

    /** @return array{Assignment, Enrollment} */
    private function assignmentWithEnrollment(): array
    {
        $cohort = Cohort::factory()->create(['program_id' => Program::factory()->active()->create()->id]);
        $session = CohortSession::factory()->for($cohort)->create();
        $assignment = Assignment::factory()->for($session, 'session')->create();
        $person = Person::create([
            'name' => 'Peserta '.fake()->unique()->numberBetween(1, 99999),
            'phone' => '+628'.fake()->unique()->numerify('##########'),
            'email' => fake()->unique()->safeEmail(),
        ]);
        $enrollment = Enrollment::create(['people_id' => $person->id, 'cohort_id' => $cohort->id]);

        return [$assignment, $enrollment];
    }

    public function test_mentor_grades_a_specific_submission(): void
    {
        [$assignment, $enrollment] = $this->assignmentWithEnrollment();
        $submission = AssignmentSubmission::factory()->create([
            'assignment_id' => $assignment->id,
            'enrollment_id' => $enrollment->id,
        ]);
        $mentor = $this->mentor();

        $this->actingAs($mentor)
            ->patchJson("/api/admin/submissions/{$submission->id}/grade", [
                'score' => 85,
                'feedback' => 'Riset produknya tajam, lanjutkan.',
            ])
            ->assertOk()
            ->assertJsonPath('submission.score', 85)
            ->assertJsonPath('submission.graded_by', $mentor->name);

        $fresh = $submission->fresh();
        $this->assertSame($mentor->id, $fresh->graded_by);
        $this->assertNotNull($fresh->graded_at);
    }

    public function test_grade_ignores_spoofed_grader_fields(): void
    {
        [$assignment, $enrollment] = $this->assignmentWithEnrollment();
        $submission = AssignmentSubmission::factory()->create([
            'assignment_id' => $assignment->id,
            'enrollment_id' => $enrollment->id,
        ]);
        $mentor = $this->mentor();

        $this->actingAs($mentor)
            ->patchJson("/api/admin/submissions/{$submission->id}/grade", [
                'score' => 70,
                'graded_by' => 999,
                'graded_at' => '2000-01-01 00:00:00',
                'url' => 'https://spoofed.example',
            ])
            ->assertOk();

        $fresh = $submission->fresh();
        $this->assertSame($mentor->id, $fresh->graded_by);
        $this->assertNotSame('https://spoofed.example', $fresh->url);
        $this->assertTrue($fresh->graded_at->isAfter(now()->subMinute()));
    }

    public function test_grading_an_older_row_leaves_the_newer_retake_pending(): void
    {
        [$assignment, $enrollment] = $this->assignmentWithEnrollment();
        $old = AssignmentSubmission::factory()->create(['assignment_id' => $assignment->id, 'enrollment_id' => $enrollment->id]);
        AssignmentSubmission::factory()->create(['assignment_id' => $assignment->id, 'enrollment_id' => $enrollment->id]);

        $this->actingAs($this->mentor())
            ->patchJson("/api/admin/submissions/{$old->id}/grade", ['score' => 60])
            ->assertOk();

        // Latest row is still ungraded -> the pair still reads as waiting.
        $this->actingAs($this->mentor())
            ->getJson("/api/admin/assignments/{$assignment->id}/enrollments/{$enrollment->id}/submissions")
            ->assertOk()
            ->assertJsonPath('state', 'menunggu_dinilai')
            ->assertJsonPath('effective_score', 60)
            ->assertJsonCount(2, 'submissions')
            ->assertJsonPath('submissions.0.score', null);
    }

    public function test_score_must_be_an_integer_between_0_and_100(): void
    {
        [$assignment, $enrollment] = $this->assignmentWithEnrollment();
        $submission = AssignmentSubmission::factory()->create([
            'assignment_id' => $assignment->id,
            'enrollment_id' => $enrollment->id,
        ]);

        $this->actingAs($this->mentor())
            ->patchJson("/api/admin/submissions/{$submission->id}/grade", ['score' => 101])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['score']);
    }

    public function test_history_rejects_mismatched_enrollment(): void
    {
        [$assignment] = $this->assignmentWithEnrollment();
        [, $otherEnrollment] = $this->assignmentWithEnrollment(); // different cohort entirely

        $this->actingAs($this->mentor())
            ->getJson("/api/admin/assignments/{$assignment->id}/enrollments/{$otherEnrollment->id}/submissions")
            ->assertNotFound();
    }

    public function test_participant_cannot_grade(): void
    {
        [$assignment, $enrollment] = $this->assignmentWithEnrollment();
        $submission = AssignmentSubmission::factory()->create([
            'assignment_id' => $assignment->id,
            'enrollment_id' => $enrollment->id,
        ]);
        $user = User::factory()->create();
        $user->assignRole('participant');

        $this->actingAs($user)
            ->patchJson("/api/admin/submissions/{$submission->id}/grade", ['score' => 90])
            ->assertForbidden();
    }

    public function test_regrading_without_feedback_key_preserves_stored_feedback(): void
    {
        [$assignment, $enrollment] = $this->assignmentWithEnrollment();
        $submission = AssignmentSubmission::factory()->create([
            'assignment_id' => $assignment->id,
            'enrollment_id' => $enrollment->id,
        ]);

        $this->actingAs($this->mentor())
            ->patchJson("/api/admin/submissions/{$submission->id}/grade", ['score' => 80, 'feedback' => 'Catatan penting.'])
            ->assertOk();

        // Score-only correction must not wipe the feedback...
        $this->actingAs($this->mentor())
            ->patchJson("/api/admin/submissions/{$submission->id}/grade", ['score' => 85])
            ->assertOk();
        $this->assertSame('Catatan penting.', $submission->fresh()->feedback);

        // ...while an explicit null still clears it.
        $this->actingAs($this->mentor())
            ->patchJson("/api/admin/submissions/{$submission->id}/grade", ['score' => 85, 'feedback' => null])
            ->assertOk();
        $this->assertNull($submission->fresh()->feedback);
    }
}
