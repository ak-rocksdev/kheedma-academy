<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Attendance;
use App\Models\Cohort;
use App\Models\CohortSession;
use App\Models\CommunityMembership;
use App\Models\Enrollment;
use App\Models\Person;
use App\Models\Program;
use App\Models\User;
use App\Support\AttendanceCompletion;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardStatsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    public function test_stats_counts(): void
    {
        $program = Program::factory()->active()->create();
        $cohort = Cohort::factory()->create([
            'program_id' => $program->id,
            'start_date' => now()->subWeek()->toDateString(),
            'end_date' => now()->addWeek()->toDateString(),
            'required_attendance' => 1,
        ]);
        $session = CohortSession::factory()->create(['cohort_id' => $cohort->id]);

        $person = fn () => Person::create([
            'name' => 'P'.fake()->unique()->numberBetween(1, 99999),
            'phone' => '+628'.fake()->unique()->numerify('##########'),
            'email' => fake()->unique()->safeEmail(),
        ]);

        Application::create(['people_id' => $person()->id, 'program_id' => $program->id, 'status' => 'pending']);
        CommunityMembership::create(['people_id' => $person()->id]);

        $active = Enrollment::create(['people_id' => $person()->id, 'cohort_id' => $cohort->id]);
        $active->statusEvents()->create(['status' => 'accepted', 'occurred_at' => now()]);

        $graduate = Enrollment::create(['people_id' => $person()->id, 'cohort_id' => $cohort->id]);
        Attendance::create(['cohort_session_id' => $session->id, 'enrollment_id' => $graduate->id]);
        app(AttendanceCompletion::class)->sync($graduate);

        $this->actingAs(User::factory()->admin()->create())
            ->getJson('/api/admin/stats')
            ->assertOk()
            ->assertJsonPath('stats.pending_applications', 1)
            ->assertJsonPath('stats.community_members', 1)
            ->assertJsonPath('stats.active_cohorts', 1)
            ->assertJsonPath('stats.active_participants', 1)
            ->assertJsonPath('stats.graduates', 1);
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/admin/stats')->assertUnauthorized();
    }

    public function test_participant_cannot_view_stats(): void
    {
        $participant = User::factory()->create();
        $participant->assignRole('participant');

        $this->actingAs($participant)->getJson('/api/admin/stats')->assertForbidden();
    }
}
