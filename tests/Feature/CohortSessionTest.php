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

    public function test_session_maps_urls_follow_the_coordinates(): void
    {
        $cohort = Cohort::factory()->create();
        $session = CohortSession::factory()->for($cohort)->atLocation()->create();

        $this->assertSame(
            'https://www.google.com/maps/search/?api=1&query=-7.5755,110.8317',
            $session->mapsUrl()
        );
        $this->assertStringContainsString('output=embed', $session->mapsEmbedUrl());
        $this->assertStringContainsString('maps/dir', $session->mapsDirectionsUrl());
    }

    public function test_session_without_coordinates_has_no_maps_urls(): void
    {
        $session = CohortSession::factory()->for(Cohort::factory())->create();

        $this->assertNull($session->mapsUrl());
        $this->assertNull($session->mapsEmbedUrl());
        $this->assertNull($session->mapsDirectionsUrl());
    }

    public function test_session_is_online_by_type(): void
    {
        $cohort = Cohort::factory()->create();

        $this->assertTrue(CohortSession::factory()->for($cohort)->online()->create()->isOnline());
        $this->assertFalse(CohortSession::factory()->for($cohort)->atLocation()->create()->isOnline());
    }

    public function test_session_google_calendar_url_needs_a_real_start_time(): void
    {
        $cohort = Cohort::factory()->create();
        // Single-word title: http_build_query encodes spaces as '+', so a
        // spaceless title keeps the containment assertion encoding-proof.
        $timed = CohortSession::factory()->for($cohort)->online()->create(['title' => 'Onboarding', 'scheduled_at' => '2026-08-08 09:30:00']);
        $midnight = CohortSession::factory()->for($cohort)->create(['scheduled_at' => '2026-08-08 00:00:00']);
        $bare = CohortSession::factory()->for($cohort)->create(['scheduled_at' => null]);

        $this->assertStringContainsString('calendar.google.com', $timed->googleCalendarUrl());
        $this->assertStringContainsString('Onboarding', $timed->googleCalendarUrl());
        $this->assertNull($midnight->googleCalendarUrl());
        $this->assertNull($bare->googleCalendarUrl());
    }

    public function test_session_scheduled_label_includes_time_when_not_midnight(): void
    {
        $cohort = Cohort::factory()->create();
        $session = CohortSession::factory()->for($cohort)->create(['scheduled_at' => '2026-08-08 09:30:00']);

        $this->assertSame('8 Agustus 2026 pukul 09.30 WIB', $session->scheduledLabel());
        $this->assertNull(CohortSession::factory()->for($cohort)->create(['scheduled_at' => null])->scheduledLabel());
    }
}
