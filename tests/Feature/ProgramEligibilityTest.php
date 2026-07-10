<?php

namespace Tests\Feature;

use App\Models\Cohort;
use App\Models\Enrollment;
use App\Models\Person;
use App\Models\Program;
use App\Support\ProgramEligibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    private function completeProgram(Person $person, Program $program): void
    {
        $cohort = Cohort::factory()->create(['program_id' => $program->id]);
        $enrollment = Enrollment::create([
            'people_id' => $person->id,
            'cohort_id' => $cohort->id,
        ]);
        DB::table('status_events')->insert([
            'enrollment_id' => $enrollment->id,
            'status' => 'completed',
            'occurred_at' => now(),
            'created_at' => now(),
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

    public function test_member_without_completion_needs_general(): void
    {
        $eligibility = app(ProgramEligibility::class);
        $level1 = Program::factory()->affiliate(1)->active()->create();
        $person = $this->makePerson();

        $this->assertFalse($eligibility->canAccess($person, $level1));
        $this->assertSame('needs_general', $eligibility->lockReason($person, $level1));
    }

    public function test_completed_general_unlocks_level_1_but_not_level_2(): void
    {
        $eligibility = app(ProgramEligibility::class);
        $general = Program::factory()->active()->create();
        $level1 = Program::factory()->affiliate(1)->active()->create();
        $level2 = Program::factory()->affiliate(2)->active()->create();

        $person = $this->makePerson();
        $this->completeProgram($person, $general);

        $this->assertTrue($eligibility->canAccess($person, $level1));
        $this->assertNull($eligibility->lockReason($person, $level1));
        $this->assertFalse($eligibility->canAccess($person, $level2));
        $this->assertSame('needs_previous_level', $eligibility->lockReason($person, $level2));
    }

    public function test_completed_level_1_unlocks_level_2(): void
    {
        $eligibility = app(ProgramEligibility::class);
        $level1 = Program::factory()->affiliate(1)->active()->create();
        $level2 = Program::factory()->affiliate(2)->active()->create();

        $person = $this->makePerson();
        $this->completeProgram($person, $level1);

        $this->assertTrue($eligibility->canAccess($person, $level2));
    }

    public function test_incomplete_enrollment_does_not_count(): void
    {
        $eligibility = app(ProgramEligibility::class);
        $general = Program::factory()->active()->create();
        $level1 = Program::factory()->affiliate(1)->active()->create();

        $person = $this->makePerson();
        // Enrollment exists but no 'completed' status event.
        $cohort = Cohort::factory()->create(['program_id' => $general->id]);
        Enrollment::create(['people_id' => $person->id, 'cohort_id' => $cohort->id]);

        $this->assertFalse($eligibility->canAccess($person, $level1));
        $this->assertSame('needs_general', $eligibility->lockReason($person, $level1));
    }
}
