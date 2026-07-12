<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Cohort;
use App\Models\Enrollment;
use App\Models\Person;
use App\Models\Program;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicationStateTest extends TestCase
{
    use RefreshDatabase;

    private function person(): Person
    {
        return Person::create([
            'name' => 'Peserta '.fake()->unique()->numberBetween(1, 99999),
            'phone' => '+628'.fake()->unique()->numerify('##########'),
            'email' => fake()->unique()->safeEmail(),
        ]);
    }

    public function test_none_without_any_history(): void
    {
        $program = Program::factory()->active()->create();

        $this->assertSame('none', $this->person()->applicationStateFor($program));
    }

    public function test_follows_latest_application_status(): void
    {
        $program = Program::factory()->active()->create();
        $person = $this->person();

        $application = Application::create(['people_id' => $person->id, 'program_id' => $program->id, 'status' => 'pending']);
        $this->assertSame('pending', $person->applicationStateFor($program));

        $application->update(['status' => 'accepted']);
        $this->assertSame('accepted', $person->applicationStateFor($program));

        $application->update(['status' => 'rejected']);
        $this->assertSame('rejected', $person->applicationStateFor($program));
    }

    public function test_enrolled_beats_application_status(): void
    {
        $program = Program::factory()->active()->create();
        $cohort = Cohort::factory()->create(['program_id' => $program->id]);
        $person = $this->person();

        Application::create(['people_id' => $person->id, 'program_id' => $program->id, 'status' => 'accepted']);
        Enrollment::create(['people_id' => $person->id, 'cohort_id' => $cohort->id]);

        $this->assertSame('enrolled', $person->applicationStateFor($program));
    }

    public function test_application_carries_cohort_and_review_note(): void
    {
        $program = Program::factory()->active()->create();
        $cohort = Cohort::factory()->create(['program_id' => $program->id]);

        $application = Application::create([
            'people_id' => $this->person()->id,
            'program_id' => $program->id,
            'cohort_id' => $cohort->id,
            'status' => 'rejected',
            'review_note' => 'Belum memenuhi syarat.',
        ]);

        $this->assertTrue($application->cohort->is($cohort));
        $this->assertSame('Belum memenuhi syarat.', $application->fresh()->review_note);
        $this->assertTrue($cohort->applications->first()->is($application));
    }

    public function test_state_is_scoped_per_program(): void
    {
        $applied = Program::factory()->active()->create();
        $other = Program::factory()->active()->create();
        $person = $this->person();

        Application::create(['people_id' => $person->id, 'program_id' => $applied->id, 'status' => 'pending']);

        $this->assertSame('pending', $person->applicationStateFor($applied));
        $this->assertSame('none', $person->applicationStateFor($other));
    }
}
