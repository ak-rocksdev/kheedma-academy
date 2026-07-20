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

class AssignmentAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    public function mentor(): User
    {
        $user = User::factory()->create();
        $user->assignRole('mentor');

        return $user;
    }

    public function cohortSession(): CohortSession
    {
        $cohort = Cohort::factory()->create(['program_id' => Program::factory()->active()->create()->id]);

        return CohortSession::factory()->for($cohort)->create();
    }

    public function test_mentor_can_create_and_update_the_assignment(): void
    {
        $session = $this->cohortSession();
        $mentor = $this->mentor();

        $this->actingAs($mentor)
            ->putJson("/api/admin/sessions/{$session->id}/assignment", [
                'title' => 'Tugas Riset Produk',
                'body' => 'Cari 3 produk winning dan tulis alasannya.',
            ])
            ->assertOk()
            ->assertJsonPath('assignment.title', 'Tugas Riset Produk')
            ->assertJsonPath('assignment.updated_by', $mentor->name)
            ->assertJsonPath('assignment.pending_count', 0);

        $editor = $this->mentor();
        $this->actingAs($editor)
            ->putJson("/api/admin/sessions/{$session->id}/assignment", [
                'title' => 'Tugas Riset Produk v2',
                'body' => 'Cari 5 produk winning.',
            ])
            ->assertOk()
            ->assertJsonPath('assignment.title', 'Tugas Riset Produk v2');

        $assignment = Assignment::sole();
        $this->assertSame($mentor->id, $assignment->created_by, 'creator must survive edits');
        $this->assertSame($editor->id, $assignment->updated_by);
    }

    public function test_client_cannot_spoof_authorship_fields(): void
    {
        $session = $this->cohortSession();
        $mentor = $this->mentor();

        $this->actingAs($mentor)
            ->putJson("/api/admin/sessions/{$session->id}/assignment", [
                'title' => 'Tugas',
                'body' => 'Isi tugas.',
                'created_by' => 999,
                'updated_by' => 999,
            ])
            ->assertOk();

        $this->assertSame($mentor->id, Assignment::sole()->created_by);
        $this->assertSame($mentor->id, Assignment::sole()->updated_by);
    }

    public function test_body_is_sanitized_to_the_allowlist(): void
    {
        $session = $this->cohortSession();

        $this->actingAs($this->mentor())
            ->putJson("/api/admin/sessions/{$session->id}/assignment", [
                'title' => 'Tugas HTML',
                'body' => '<p>Langkah <strong>satu</strong></p><script>alert(1)</script>',
            ])
            ->assertOk();

        $body = Assignment::sole()->body;
        $this->assertStringContainsString('<strong>satu</strong>', $body);
        $this->assertStringNotContainsString('<script', $body);
    }

    public function test_title_and_body_are_required(): void
    {
        $session = $this->cohortSession();

        $this->actingAs($this->mentor())
            ->putJson("/api/admin/sessions/{$session->id}/assignment", ['title' => '', 'body' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'body']);
    }

    public function test_participant_cannot_manage_assignments(): void
    {
        $session = $this->cohortSession();
        $user = User::factory()->create();
        $user->assignRole('participant');

        $this->actingAs($user)
            ->putJson("/api/admin/sessions/{$session->id}/assignment", ['title' => 'X', 'body' => 'Y'])
            ->assertForbidden();
    }

    public function test_pending_count_follows_the_latest_submission_per_enrollment(): void
    {
        $session = $this->cohortSession();
        $assignment = Assignment::factory()->for($session, 'session')->create();

        $makeEnrollment = function () use ($session) {
            $person = Person::create([
                'name' => 'Peserta '.fake()->unique()->numberBetween(1, 99999),
                'phone' => '+628'.fake()->unique()->numerify('##########'),
                'email' => fake()->unique()->safeEmail(),
            ]);

            return Enrollment::create(['people_id' => $person->id, 'cohort_id' => $session->cohort_id]);
        };

        // A: graded then resubmitted (latest ungraded) -> counts as pending.
        $a = $makeEnrollment();
        AssignmentSubmission::factory()->graded(80)->create(['assignment_id' => $assignment->id, 'enrollment_id' => $a->id]);
        AssignmentSubmission::factory()->create(['assignment_id' => $assignment->id, 'enrollment_id' => $a->id]);

        // B: submitted then graded (latest graded) -> NOT pending.
        $b = $makeEnrollment();
        AssignmentSubmission::factory()->create(['assignment_id' => $assignment->id, 'enrollment_id' => $b->id]);
        AssignmentSubmission::factory()->graded(90)->create(['assignment_id' => $assignment->id, 'enrollment_id' => $b->id]);

        $this->actingAs($this->mentor())
            ->putJson("/api/admin/sessions/{$session->id}/assignment", ['title' => $assignment->title, 'body' => $assignment->body])
            ->assertOk()
            ->assertJsonPath('assignment.pending_count', 1);
    }
}
