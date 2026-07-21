<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Attendance;
use App\Models\Cohort;
use App\Models\CohortSession;
use App\Models\CommunityMembership;
use App\Models\Enrollment;
use App\Models\Person;
use App\Models\Program;
use App\Support\ProgramEligibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgramEligibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_program_segmentation_fields_round_trip(): void
    {
        $general = Program::factory()->create();
        $affiliate = Program::factory()->affiliate(2)->create(['locked_message' => 'Khusus lulusan Level 1.']);

        $this->assertSame('general', $general->fresh()->type);
        $this->assertFalse($general->isAffiliate());

        $fresh = $affiliate->fresh();
        $this->assertSame('affiliate_community', $fresh->type);
        $this->assertSame(2, (int) $fresh->level);
        $this->assertSame('Khusus lulusan Level 1.', $fresh->locked_message);
        $this->assertTrue($fresh->isAffiliate());

        $this->assertNotSame('', (string) config('kheedma.default_locked_message'));
    }

    private function makePerson(): Person
    {
        return Person::create([
            'name' => 'Peserta '.fake()->unique()->numberBetween(1, 99999),
            'phone' => '+628'.fake()->unique()->numerify('##########'),
            'email' => fake()->unique()->safeEmail(),
        ]);
    }

    private function makeMember(): Person
    {
        $person = $this->makePerson();
        CommunityMembership::create(['people_id' => $person->id]);

        return $person;
    }

    /** "Pernah diikuti": enrollment + satu kehadiran di kelas program itu. */
    private function attendProgram(Person $person, Program $program): void
    {
        $cohort = Cohort::factory()->create(['program_id' => $program->id]);
        $enrollment = Enrollment::create([
            'people_id' => $person->id,
            'cohort_id' => $cohort->id,
        ]);
        $session = CohortSession::factory()->create(['cohort_id' => $cohort->id]);
        Attendance::create([
            'cohort_session_id' => $session->id,
            'enrollment_id' => $enrollment->id,
        ]);
    }

    public function test_general_program_is_open_to_everyone(): void
    {
        $eligibility = app(ProgramEligibility::class);
        $general = Program::factory()->active()->create();

        $this->assertTrue($eligibility->canAccess(null, $general));
        $this->assertNull($eligibility->lockReason(null, $general));
        $this->assertTrue($eligibility->canAccess($this->makePerson(), $general));
    }

    public function test_guest_is_locked_out_of_affiliate(): void
    {
        $eligibility = app(ProgramEligibility::class);
        $level1 = Program::factory()->affiliate(1)->active()->create();

        $this->assertFalse($eligibility->canAccess(null, $level1));
        $this->assertSame('guest', $eligibility->lockReason(null, $level1));
    }

    public function test_non_member_needs_membership_for_level_1(): void
    {
        $eligibility = app(ProgramEligibility::class);
        $level1 = Program::factory()->affiliate(1)->active()->create();
        $person = $this->makePerson();

        $this->assertFalse($eligibility->canAccess($person, $level1));
        $this->assertSame('needs_membership', $eligibility->lockReason($person, $level1));
    }

    public function test_membership_unlocks_level_1_but_not_level_2(): void
    {
        $eligibility = app(ProgramEligibility::class);
        $level1 = Program::factory()->affiliate(1)->active()->create();
        $level2 = Program::factory()->affiliate(2)->active()->create();
        $person = $this->makeMember();

        $this->assertNull($eligibility->lockReason($person, $level1));
        $this->assertSame('needs_previous_level', $eligibility->lockReason($person, $level2));
    }

    public function test_completing_level_1_unlocks_level_2_for_a_member(): void
    {
        $eligibility = app(ProgramEligibility::class);
        $level1 = Program::factory()->affiliate(1)->active()->create();
        $level2 = Program::factory()->affiliate(2)->active()->create();
        $person = $this->makeMember();
        $this->attendProgram($person, $level1); // completes a Level-1 intake

        $this->assertNull($eligibility->lockReason($person, $level2));
    }

    public function test_score_gate_applies_between_community_levels(): void
    {
        $level1 = Program::factory()->affiliate(1)->active()->create(['min_average_score' => 70]);
        $level2 = Program::factory()->affiliate(2)->active()->create();
        $person = $this->makeMember();
        $cohort = Cohort::factory()->create(['program_id' => $level1->id]);
        $enrollment = Enrollment::create(['people_id' => $person->id, 'cohort_id' => $cohort->id]);
        $session = CohortSession::factory()->for($cohort)->create();
        $assignment = Assignment::factory()->for($session, 'session')->create();
        AssignmentSubmission::factory()->graded(72)->create(['assignment_id' => $assignment->id, 'enrollment_id' => $enrollment->id]);
        Attendance::create(['cohort_session_id' => $session->id, 'enrollment_id' => $enrollment->id]);

        $this->assertNull(app(ProgramEligibility::class)->lockReason($person, $level2));
    }

    public function test_guest_cannot_join_community(): void
    {
        $this->assertFalse(app(ProgramEligibility::class)->canJoinCommunity(null));
        $this->assertSame('guest', app(ProgramEligibility::class)->joinLockReason(null));
    }

    public function test_graduate_of_general_can_join_community(): void
    {
        $general = Program::factory()->active()->create();
        $person = $this->makePerson();
        $this->attendProgram($person, $general);

        $this->assertTrue(app(ProgramEligibility::class)->canJoinCommunity($person));
        $this->assertNull(app(ProgramEligibility::class)->joinLockReason($person));
    }

    public function test_partial_general_attendance_cannot_join_community(): void
    {
        $general = Program::factory()->active()->create();
        $person = $this->makePerson();
        $cohort = Cohort::factory()->create(['program_id' => $general->id]);
        $enrollment = Enrollment::create(['people_id' => $person->id, 'cohort_id' => $cohort->id]);
        $sessions = CohortSession::factory()->count(3)->for($cohort)->create();
        Attendance::create(['cohort_session_id' => $sessions[0]->id, 'enrollment_id' => $enrollment->id]);

        $this->assertSame('needs_general', app(ProgramEligibility::class)->joinLockReason($person));
    }

    public function test_threshold_without_soal_still_lets_a_graduate_join(): void
    {
        // Misconfiguration guard: a score bar is set but no soal exist yet, so
        // the average is null and completion alone governs the join.
        $general = Program::factory()->active()->create(['min_average_score' => 75]);
        $person = $this->makePerson();
        $cohort = Cohort::factory()->create(['program_id' => $general->id]);
        $enrollment = Enrollment::create(['people_id' => $person->id, 'cohort_id' => $cohort->id]);
        $session = CohortSession::factory()->for($cohort)->create();
        Attendance::create(['cohort_session_id' => $session->id, 'enrollment_id' => $enrollment->id]);

        $this->assertNull(app(ProgramEligibility::class)->joinLockReason($person));
    }

    public function test_below_the_score_bar_cannot_join_community(): void
    {
        $general = Program::factory()->active()->create(['min_average_score' => 75]);
        $person = $this->makePerson();
        $cohort = Cohort::factory()->create(['program_id' => $general->id]);
        $enrollment = Enrollment::create(['people_id' => $person->id, 'cohort_id' => $cohort->id]);
        $session = CohortSession::factory()->for($cohort)->create();
        $assignment = Assignment::factory()->for($session, 'session')->create();
        AssignmentSubmission::factory()->graded(60)->create(['assignment_id' => $assignment->id, 'enrollment_id' => $enrollment->id]);
        Attendance::create(['cohort_session_id' => $session->id, 'enrollment_id' => $enrollment->id]);

        $this->assertSame('needs_general', app(ProgramEligibility::class)->joinLockReason($person));
    }
}
