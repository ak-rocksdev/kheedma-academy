<?php

namespace Tests\Feature;

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

class CohortSessionTest extends TestCase
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

    private function cohortWithEnrollment(): array
    {
        $cohort = Cohort::factory()->create(['program_id' => Program::factory()->active()->create()->id]);
        $person = Person::create([
            'name' => 'Peserta Uji',
            'phone' => '+628'.fake()->unique()->numerify('##########'),
            'email' => fake()->unique()->safeEmail(),
        ]);
        $enrollment = Enrollment::create(['people_id' => $person->id, 'cohort_id' => $cohort->id]);

        return [$cohort, $enrollment];
    }

    public function test_admin_can_manage_sessions(): void
    {
        [$cohort] = $this->cohortWithEnrollment();

        $created = $this->actingAs($this->admin())
            ->postJson("/api/admin/cohorts/{$cohort->id}/sessions", ['title' => 'Sesi 1: Dasar', 'position' => 1])
            ->assertCreated()
            ->json('session.id');

        $this->actingAs($this->admin())
            ->patchJson("/api/admin/sessions/{$created}", ['title' => 'Sesi 1: Dasar Affiliate'])
            ->assertOk()
            ->assertJsonPath('session.title', 'Sesi 1: Dasar Affiliate');

        $this->actingAs($this->admin())->deleteJson("/api/admin/sessions/{$created}")->assertNoContent();
    }

    public function test_cohort_detail_returns_sessions_and_roster(): void
    {
        [$cohort, $enrollment] = $this->cohortWithEnrollment();
        $session = CohortSession::factory()->create(['cohort_id' => $cohort->id]);
        Attendance::create(['cohort_session_id' => $session->id, 'enrollment_id' => $enrollment->id]);

        $this->actingAs($this->admin())
            ->getJson("/api/admin/cohorts/{$cohort->id}")
            ->assertOk()
            ->assertJsonPath('sessions.0.id', $session->id)
            ->assertJsonPath('roster.0.hadir', 1)
            ->assertJsonPath('roster.0.attended_session_ids.0', $session->id);
    }

    public function test_deleting_a_session_cascades_its_attendance(): void
    {
        [$cohort, $enrollment] = $this->cohortWithEnrollment();
        $session = CohortSession::factory()->create(['cohort_id' => $cohort->id]);
        Attendance::create(['cohort_session_id' => $session->id, 'enrollment_id' => $enrollment->id]);

        $this->actingAs($this->admin())->deleteJson("/api/admin/sessions/{$session->id}")->assertNoContent();

        $this->assertSame(0, Attendance::count());
    }
}
