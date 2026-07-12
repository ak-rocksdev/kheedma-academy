<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Cohort;
use App\Models\Enrollment;
use App\Models\Person;
use App\Models\Program;
use App\Models\StatusEvent;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgramDetailTest extends TestCase
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

    public function test_show_returns_program_cohorts_applications_and_stats(): void
    {
        $program = Program::factory()->active()->create();
        $mentor = User::factory()->mentor()->create();
        $withMentor = Cohort::factory()->create(['program_id' => $program->id, 'mentor_id' => $mentor->id]);
        Cohort::factory()->create(['program_id' => $program->id, 'mentor_id' => null]);

        $pendingPerson = $this->person();
        Application::create(['people_id' => $pendingPerson->id, 'program_id' => $program->id, 'cohort_id' => $withMentor->id, 'status' => 'pending']);

        $enrolledPerson = $this->person();
        $accepted = Application::create(['people_id' => $enrolledPerson->id, 'program_id' => $program->id, 'status' => 'accepted']);
        Enrollment::create(['people_id' => $enrolledPerson->id, 'cohort_id' => $withMentor->id, 'application_id' => $accepted->id]);

        $rejectedPerson = $this->person();
        Application::create(['people_id' => $rejectedPerson->id, 'program_id' => $program->id, 'status' => 'rejected']);

        $res = $this->actingAs($this->admin())
            ->getJson("/api/admin/programs/{$program->id}")
            ->assertOk()
            ->assertJsonPath('program.id', $program->id)
            ->assertJsonPath('program.slug', $program->slug)
            ->assertJsonPath('program.cohorts_count', 2)
            ->assertJsonPath('program.applications_count', 3)
            ->assertJsonPath('stats.pending', 1)
            ->assertJsonPath('stats.accepted', 1)
            ->assertJsonPath('stats.rejected', 1)
            ->assertJsonPath('stats.active_participants', 1);

        $res->assertJsonStructure([
            'program' => ['id', 'slug', 'name', 'tagline', 'description', 'status', 'selection_mode', 'type', 'level', 'locked_message', 'thumbnail_url', 'is_open', 'cohorts_count', 'applications_count'],
            'cohorts' => [['id', 'name', 'start_date', 'end_date', 'status', 'mentor', 'enrollments_count', 'registration_opens_at', 'registration_closes_at', 'registration_open']],
            'applications' => [['id', 'status', 'created_at', 'reviewed_at', 'motivation', 'referral_source', 'review_note', 'cohort_id', 'person', 'enrollment']],
        ]);

        $cohorts = collect($res->json('cohorts'))->keyBy('id');
        $this->assertSame($mentor->name, $cohorts->get($withMentor->id)['mentor']['name']);
        $this->assertSame(1, $cohorts->get($withMentor->id)['enrollments_count']);

        $applications = collect($res->json('applications'))->keyBy(fn (array $a) => $a['person']['id']);
        $this->assertSame(
            ['cohort_id' => $withMentor->id, 'cohort_name' => $withMentor->name],
            $applications->get($enrolledPerson->id)['enrollment'],
        );
        $this->assertNull($applications->get($pendingPerson->id)['enrollment']);
        $this->assertSame($withMentor->id, $applications->get($pendingPerson->id)['cohort_id']);
        $this->assertSame($pendingPerson->phone, $applications->get($pendingPerson->id)['person']['phone']);
    }

    public function test_show_with_empty_program_returns_empty_lists_and_zero_stats(): void
    {
        $program = Program::factory()->create();

        $res = $this->actingAs($this->admin())
            ->getJson("/api/admin/programs/{$program->id}")
            ->assertOk()
            ->assertJsonCount(0, 'cohorts')
            ->assertJsonCount(0, 'applications')
            ->assertJsonPath('stats', ['pending' => 0, 'accepted' => 0, 'rejected' => 0, 'active_participants' => 0]);

        // Empty collections must serialize as [] (arrays), not null/objects.
        $this->assertSame([], $res->json('cohorts'));
        $this->assertSame([], $res->json('applications'));
    }

    public function test_active_participants_excludes_dropped_enrollments(): void
    {
        $program = Program::factory()->active()->create();
        $cohort = Cohort::factory()->create(['program_id' => $program->id]);

        $active = Enrollment::create(['people_id' => $this->person()->id, 'cohort_id' => $cohort->id]);
        StatusEvent::create(['enrollment_id' => $active->id, 'status' => 'accepted', 'occurred_at' => now()]);

        $dropped = Enrollment::create(['people_id' => $this->person()->id, 'cohort_id' => $cohort->id]);
        StatusEvent::create(['enrollment_id' => $dropped->id, 'status' => 'dropped', 'occurred_at' => now()]);

        // Enrollment in another program's cohort must not leak into this program's stats.
        $otherCohort = Cohort::factory()->create(['program_id' => Program::factory()->active()->create()->id]);
        Enrollment::create(['people_id' => $this->person()->id, 'cohort_id' => $otherCohort->id]);

        $this->actingAs($this->admin())
            ->getJson("/api/admin/programs/{$program->id}")
            ->assertOk()
            ->assertJsonPath('stats.active_participants', 1);
    }

    public function test_show_survives_soft_deleted_applicant(): void
    {
        $program = Program::factory()->active()->create();
        $person = $this->person();
        Application::create(['people_id' => $person->id, 'program_id' => $program->id, 'status' => 'pending']);
        $person->delete();

        $this->actingAs($this->admin())
            ->getJson("/api/admin/programs/{$program->id}")
            ->assertOk()
            ->assertJsonPath('applications.0.person', null);
    }

    public function test_mentor_cannot_view_program_detail(): void
    {
        $program = Program::factory()->create();

        $this->actingAs(User::factory()->mentor()->create())
            ->getJson("/api/admin/programs/{$program->id}")
            ->assertForbidden();
    }

    public function test_unknown_program_returns_404(): void
    {
        $this->actingAs($this->admin())
            ->getJson('/api/admin/programs/999999')
            ->assertNotFound();
    }
}
