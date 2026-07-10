<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Attendance;
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

class EnrollmentManagementTest extends TestCase
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
            'name' => 'Peserta '.fake()->unique()->numberBetween(1, 99999),
            'phone' => '+628'.fake()->unique()->numerify('##########'),
            'email' => fake()->unique()->safeEmail(),
        ]);
    }

    public function test_enroll_from_accepted_application(): void
    {
        $program = Program::factory()->active()->create();
        $cohort = Cohort::factory()->create(['program_id' => $program->id]);
        $person = $this->person();
        $application = Application::create([
            'people_id' => $person->id,
            'program_id' => $program->id,
            'status' => 'accepted',
        ]);

        $this->actingAs($this->admin())
            ->postJson('/api/admin/enrollments', [
                'cohort_id' => $cohort->id,
                'application_id' => $application->id,
            ])
            ->assertCreated()
            ->assertJsonPath('enrollment.person.id', $person->id);

        $enrollment = Enrollment::first();
        $this->assertSame($application->id, $enrollment->application_id);
        $this->assertSame('accepted', $enrollment->latestStatusEvent->status);
        $this->assertNotNull($enrollment->latestStatusEvent->created_by);
    }

    public function test_application_must_be_accepted_and_cohort_must_match_program(): void
    {
        $program = Program::factory()->active()->create();
        $otherProgram = Program::factory()->active()->create();
        $cohort = Cohort::factory()->create(['program_id' => $otherProgram->id]);
        $person = $this->person();
        $pending = Application::create(['people_id' => $person->id, 'program_id' => $program->id, 'status' => 'pending']);

        // Pending application rejected.
        $this->actingAs($this->admin())
            ->postJson('/api/admin/enrollments', ['cohort_id' => $cohort->id, 'application_id' => $pending->id])
            ->assertStatus(422);

        // Accepted but cohort belongs to another program: rejected.
        $pending->update(['status' => 'accepted']);
        $this->actingAs($this->admin())
            ->postJson('/api/admin/enrollments', ['cohort_id' => $cohort->id, 'application_id' => $pending->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('cohort_id');
    }

    public function test_duplicate_enrollment_is_rejected(): void
    {
        $cohort = Cohort::factory()->create(['program_id' => Program::factory()->active()->create()->id]);
        $person = $this->person();
        Enrollment::create(['people_id' => $person->id, 'cohort_id' => $cohort->id]);

        $this->actingAs($this->admin())
            ->postJson('/api/admin/enrollments', ['cohort_id' => $cohort->id, 'people_id' => $person->id])
            ->assertStatus(422);
    }

    public function test_unenroll_only_while_clean(): void
    {
        $cohort = Cohort::factory()->create(['program_id' => Program::factory()->active()->create()->id]);
        $session = CohortSession::factory()->create(['cohort_id' => $cohort->id]);
        $clean = Enrollment::create(['people_id' => $this->person()->id, 'cohort_id' => $cohort->id]);
        $dirty = Enrollment::create(['people_id' => $this->person()->id, 'cohort_id' => $cohort->id]);
        Attendance::create(['cohort_session_id' => $session->id, 'enrollment_id' => $dirty->id]);

        $this->actingAs($this->admin())->deleteJson("/api/admin/enrollments/{$clean->id}")->assertNoContent();
        $this->actingAs($this->admin())->deleteJson("/api/admin/enrollments/{$dirty->id}")->assertStatus(422);
    }

    public function test_drop_requires_note_and_writes_event(): void
    {
        $cohort = Cohort::factory()->create(['program_id' => Program::factory()->active()->create()->id]);
        $enrollment = Enrollment::create(['people_id' => $this->person()->id, 'cohort_id' => $cohort->id]);

        $this->actingAs($this->admin())
            ->postJson("/api/admin/enrollments/{$enrollment->id}/drop", [])
            ->assertStatus(422);

        $this->actingAs($this->admin())
            ->postJson("/api/admin/enrollments/{$enrollment->id}/drop", ['note' => 'Sibuk kerja'])
            ->assertOk();

        $this->assertSame('dropped', $enrollment->fresh()->latestStatusEvent->status);
    }

    public function test_mentor_cannot_manage_enrollments(): void
    {
        $mentor = User::factory()->mentor()->create();
        $this->actingAs($mentor)->postJson('/api/admin/enrollments', [])->assertForbidden();
    }

    public function test_person_detail_includes_hadir_count(): void
    {
        $cohort = Cohort::factory()->create(['program_id' => Program::factory()->active()->create()->id]);
        $session = CohortSession::factory()->create(['cohort_id' => $cohort->id]);
        $person = $this->person();
        $enrollment = Enrollment::create(['people_id' => $person->id, 'cohort_id' => $cohort->id]);
        Attendance::create(['cohort_session_id' => $session->id, 'enrollment_id' => $enrollment->id]);

        $this->actingAs($this->admin())
            ->getJson("/api/admin/people/{$person->id}")
            ->assertOk()
            ->assertJsonPath('person.enrollments.0.hadir', 1);
    }

    public function test_person_detail_lists_classes_with_attendance_state(): void
    {
        $cohort = Cohort::factory()->create(['program_id' => Program::factory()->active()->create()->id]);
        $attendedClass = CohortSession::factory()->create(['cohort_id' => $cohort->id]);
        $missedClass = CohortSession::factory()->create(['cohort_id' => $cohort->id]);
        $person = $this->person();
        $enrollment = Enrollment::create(['people_id' => $person->id, 'cohort_id' => $cohort->id]);
        Attendance::create(['cohort_session_id' => $attendedClass->id, 'enrollment_id' => $enrollment->id]);

        $res = $this->actingAs($this->admin())
            ->getJson("/api/admin/people/{$person->id}")
            ->assertOk();

        $classes = collect($res->json('person.enrollments.0.classes'))->keyBy('id');
        $this->assertTrue($classes->get($attendedClass->id)['attended']);
        $this->assertNotNull($classes->get($attendedClass->id)['attended_at']);
        $this->assertFalse($classes->get($missedClass->id)['attended']);
    }
}
