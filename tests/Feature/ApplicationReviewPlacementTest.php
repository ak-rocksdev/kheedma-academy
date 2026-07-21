<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Cohort;
use App\Models\Enrollment;
use App\Models\Person;
use App\Models\Program;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicationReviewPlacementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function person(): Person
    {
        return Person::create([
            'name' => 'Pendaftar '.fake()->unique()->numberBetween(1, 99999),
            'phone' => '+628'.fake()->unique()->numerify('##########'),
            'email' => fake()->unique()->safeEmail(),
        ]);
    }

    /** @return array{0: Application, 1: Cohort} */
    private function pendingApplication(): array
    {
        $program = Program::factory()->active()->create();
        $cohort = Cohort::factory()->create(['program_id' => $program->id]);
        $application = Application::create([
            'people_id' => $this->person()->id,
            'program_id' => $program->id,
            'cohort_id' => $cohort->id,
            'status' => 'pending',
        ]);

        return [$application, $cohort];
    }

    public function test_accepting_places_person_into_application_cohort(): void
    {
        [$application, $cohort] = $this->pendingApplication();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->patchJson("/api/admin/applications/{$application->id}", ['status' => 'accepted'])
            ->assertOk()
            ->assertJsonPath('application.status', 'accepted')
            ->assertJsonPath('application.cohort.name', $cohort->name)
            ->assertJsonPath('application.enrollment.cohort_id', $cohort->id)
            ->assertJsonPath('application.enrollment.cohort_name', $cohort->name);

        $enrollment = Enrollment::sole();
        $this->assertSame($application->people_id, $enrollment->people_id);
        $this->assertSame($cohort->id, $enrollment->cohort_id);
        $this->assertSame($application->id, $enrollment->application_id);
        $this->assertSame('accepted', $enrollment->latestStatusEvent->status);
        $this->assertSame($admin->id, $enrollment->latestStatusEvent->created_by);
    }

    public function test_accepting_provisions_a_login_for_an_account_less_person(): void
    {
        [$application, $cohort] = $this->pendingApplication();
        $person = $application->person;
        $this->assertNull($person->user_id);

        $this->actingAs($this->admin())
            ->patchJson("/api/admin/applications/{$application->id}", ['status' => 'accepted'])
            ->assertOk();

        $person->refresh();
        $this->assertNotNull($person->user_id, 'an accepted, enrolled person must have a login');
        $this->assertTrue($person->user->hasRole('participant'));
    }

    public function test_accepting_is_idempotent_when_already_enrolled_in_that_cohort(): void
    {
        [$application, $cohort] = $this->pendingApplication();
        Enrollment::create(['people_id' => $application->people_id, 'cohort_id' => $cohort->id]);

        $this->actingAs($this->admin())
            ->patchJson("/api/admin/applications/{$application->id}", ['status' => 'accepted'])
            ->assertOk();

        $this->assertSame(1, Enrollment::count());
    }

    public function test_accepting_twice_creates_one_enrollment(): void
    {
        [$application] = $this->pendingApplication();
        $admin = $this->admin();

        $this->actingAs($admin)->patchJson("/api/admin/applications/{$application->id}", ['status' => 'accepted'])->assertOk();
        $this->actingAs($admin)->patchJson("/api/admin/applications/{$application->id}", ['status' => 'accepted'])->assertOk();

        $this->assertSame(1, Enrollment::count());
    }

    public function test_accepting_legacy_application_without_cohort_creates_no_enrollment(): void
    {
        $program = Program::factory()->active()->create();
        $application = Application::create([
            'people_id' => $this->person()->id,
            'program_id' => $program->id,
            'status' => 'pending',
        ]);

        $this->actingAs($this->admin())
            ->patchJson("/api/admin/applications/{$application->id}", ['status' => 'accepted'])
            ->assertOk()
            ->assertJsonPath('application.enrollment', null);

        $this->assertSame(0, Enrollment::count());
    }

    public function test_rejecting_stores_review_note_without_enrollment(): void
    {
        [$application] = $this->pendingApplication();

        $this->actingAs($this->admin())
            ->patchJson("/api/admin/applications/{$application->id}", [
                'status' => 'rejected',
                'review_note' => 'Belum sesuai kriteria angkatan ini.',
            ])
            ->assertOk()
            ->assertJsonPath('application.review_note', 'Belum sesuai kriteria angkatan ini.');

        $application->refresh();
        $this->assertSame('Belum sesuai kriteria angkatan ini.', $application->review_note);
        $this->assertNotNull($application->reviewed_at);
        $this->assertSame(0, Enrollment::count());
    }

    public function test_review_note_is_limited_to_2000_characters(): void
    {
        [$application] = $this->pendingApplication();

        $this->actingAs($this->admin())
            ->patchJson("/api/admin/applications/{$application->id}", [
                'status' => 'rejected',
                'review_note' => str_repeat('a', 2001),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('review_note');
    }

    public function test_back_to_pending_clears_reviewed_at_and_keeps_enrollment(): void
    {
        [$application] = $this->pendingApplication();
        $admin = $this->admin();

        $this->actingAs($admin)->patchJson("/api/admin/applications/{$application->id}", ['status' => 'accepted'])->assertOk();
        $this->actingAs($admin)->patchJson("/api/admin/applications/{$application->id}", ['status' => 'pending'])->assertOk();

        $this->assertNull($application->fresh()->reviewed_at);
        // The placement survives a revert: undoing it is a manual roster action.
        $this->assertSame(1, Enrollment::count());
    }
}
