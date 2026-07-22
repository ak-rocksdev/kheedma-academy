<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Attendance;
use App\Models\Cohort;
use App\Models\CohortSession;
use App\Models\Enrollment;
use App\Models\Person;
use App\Models\Program;
use App\Models\SessionConfirmation;
use App\Models\StatusEvent;
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
            ->postJson("/api/admin/cohorts/{$cohort->id}/sessions", ['title' => 'Sesi 1: Dasar', 'position' => 1, 'type' => 'online'])
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

    public function test_cohort_detail_orders_sessions_by_schedule_not_position(): void
    {
        [$cohort] = $this->cohortWithEnrollment();
        // The prod shape that surfaced the bug: the legacy backfilled session
        // carries position 1 while admin-created ones default to 0, so a
        // position-first sort banishes the earliest class to the end.
        $first = CohortSession::factory()->create(['cohort_id' => $cohort->id, 'title' => 'Kelas 1', 'scheduled_at' => now()->addDays(1), 'position' => 1]);
        $unscheduled = CohortSession::factory()->create(['cohort_id' => $cohort->id, 'title' => 'Kelas TBA', 'scheduled_at' => null, 'position' => 0]);
        $second = CohortSession::factory()->create(['cohort_id' => $cohort->id, 'title' => 'Kelas 2', 'scheduled_at' => now()->addDays(8), 'position' => 0]);

        $this->actingAs($this->admin())
            ->getJson("/api/admin/cohorts/{$cohort->id}")
            ->assertOk()
            ->assertJsonPath('sessions.0.id', $first->id)
            ->assertJsonPath('sessions.1.id', $second->id)
            ->assertJsonPath('sessions.2.id', $unscheduled->id);
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

    public function test_offline_session_requires_location_fields(): void
    {
        $cohort = Cohort::factory()->create();

        $this->actingAs($this->admin())->postJson("/api/admin/cohorts/{$cohort->id}/sessions", [
            'title' => 'Kelas 1: Riset Produk',
            'type' => 'offline',
        ])->assertUnprocessable()->assertJsonValidationErrors(['location_address', 'location_lat', 'location_lng']);
    }

    public function test_online_session_stores_venue_and_returns_it(): void
    {
        $cohort = Cohort::factory()->create();

        $res = $this->actingAs($this->admin())->postJson("/api/admin/cohorts/{$cohort->id}/sessions", [
            'title' => 'Kelas 2: Konten',
            'scheduled_at' => '2026-08-15T09:30',
            'type' => 'online',
            'meeting_url' => 'https://meet.google.com/abc-defg-hij',
        ])->assertCreated();

        $res->assertJsonPath('session.type', 'online')
            ->assertJsonPath('session.meeting_url', 'https://meet.google.com/abc-defg-hij');
    }

    public function test_session_meeting_url_must_be_https(): void
    {
        $cohort = Cohort::factory()->create();

        $this->actingAs($this->admin())->postJson("/api/admin/cohorts/{$cohort->id}/sessions", [
            'title' => 'Kelas',
            'type' => 'online',
            'meeting_url' => 'http://insecure.example',
        ])->assertUnprocessable()->assertJsonValidationErrors(['meeting_url']);
    }

    public function test_legacy_session_without_location_can_update_title_alone(): void
    {
        $cohort = Cohort::factory()->create();
        $session = CohortSession::factory()->for($cohort)->create(['type' => 'offline']);

        $this->actingAs($this->admin())->patchJson("/api/admin/sessions/{$session->id}", ['title' => 'Judul Baru'])
            ->assertOk()->assertJsonPath('session.title', 'Judul Baru');
    }

    public function test_cohort_detail_carries_assignment_states_and_recap(): void
    {
        $program = Program::factory()->active()->create(['min_average_score' => 75]);
        $cohort = Cohort::factory()->create(['program_id' => $program->id]);
        $session = CohortSession::factory()->create(['cohort_id' => $cohort->id]);
        $assignment = Assignment::factory()->for($session, 'session')->create(['title' => 'Tugas Riset']);
        $person = Person::create([
            'name' => 'Peserta Rekap',
            'phone' => '+628'.fake()->unique()->numerify('##########'),
            'email' => fake()->unique()->safeEmail(),
        ]);
        $enrollment = Enrollment::create(['people_id' => $person->id, 'cohort_id' => $cohort->id]);
        AssignmentSubmission::factory()->graded(80)->create([
            'assignment_id' => $assignment->id,
            'enrollment_id' => $enrollment->id,
        ]);

        $this->actingAs($this->admin())
            ->getJson("/api/admin/cohorts/{$cohort->id}")
            ->assertOk()
            ->assertJsonPath('cohort.min_average_score', 75)
            ->assertJsonPath('sessions.0.assignment.title', 'Tugas Riset')
            ->assertJsonPath('sessions.0.assignment.pending_count', 0)
            ->assertJsonPath("roster.0.assignment_states.{$assignment->id}.state", 'dinilai')
            ->assertJsonPath("roster.0.assignment_states.{$assignment->id}.score", 80)
            ->assertJsonPath('roster.0.average', 80.0)
            ->assertJsonPath('roster.0.qualifies', true);
    }

    public function test_recap_is_null_without_a_threshold_and_assignment_null_without_soal(): void
    {
        $cohort = Cohort::factory()->create(['program_id' => Program::factory()->active()->create()->id]);
        CohortSession::factory()->create(['cohort_id' => $cohort->id]);
        $person = Person::create([
            'name' => 'Peserta Polos',
            'phone' => '+628'.fake()->unique()->numerify('##########'),
            'email' => fake()->unique()->safeEmail(),
        ]);
        Enrollment::create(['people_id' => $person->id, 'cohort_id' => $cohort->id]);

        $this->actingAs($this->admin())
            ->getJson("/api/admin/cohorts/{$cohort->id}")
            ->assertOk()
            ->assertJsonPath('cohort.min_average_score', null)
            ->assertJsonPath('sessions.0.assignment', null)
            ->assertJsonPath('roster.0.average', null)
            ->assertJsonPath('roster.0.qualifies', null);
    }

    public function test_cohort_detail_carries_confirmation_recap_per_session(): void
    {
        $cohort = Cohort::factory()->create();
        $session = CohortSession::factory()->for($cohort)->create();
        $personA = Person::create(['name' => 'Aisyah Uji', 'phone' => '+62811111111', 'email' => fake()->unique()->safeEmail()]);
        $personB = Person::create(['name' => 'Budi Uji', 'phone' => '+62822222222', 'email' => fake()->unique()->safeEmail()]);
        $enrollA = Enrollment::create(['people_id' => $personA->id, 'cohort_id' => $cohort->id]);
        $enrollB = Enrollment::create(['people_id' => $personB->id, 'cohort_id' => $cohort->id]);
        SessionConfirmation::factory()->create(['cohort_session_id' => $session->id, 'enrollment_id' => $enrollA->id]);
        SessionConfirmation::factory()->cannotAttend('Bentrok kerja.')->create(['cohort_session_id' => $session->id, 'enrollment_id' => $enrollB->id]);

        // Dropped after confirming: the stale intent must not inflate the
        // recap or leak the person's name into the entries list.
        $personC = Person::create(['name' => 'Citra Uji', 'phone' => '+62833333333', 'email' => fake()->unique()->safeEmail()]);
        $enrollC = Enrollment::create(['people_id' => $personC->id, 'cohort_id' => $cohort->id]);
        SessionConfirmation::factory()->create(['cohort_session_id' => $session->id, 'enrollment_id' => $enrollC->id]);
        StatusEvent::create(['enrollment_id' => $enrollC->id, 'status' => 'dropped', 'occurred_at' => now()]);

        $res = $this->actingAs($this->admin())->getJson("/api/admin/cohorts/{$cohort->id}")->assertOk();

        $row = collect($res->json('sessions'))->firstWhere('id', $session->id);
        $this->assertSame(1, $row['confirmations']['attending']);
        $this->assertSame(1, $row['confirmations']['cannot_attend']);
        $names = collect($row['confirmations']['entries'])->pluck('name');
        $this->assertTrue($names->contains('Aisyah Uji'));
        $this->assertFalse($names->contains('Citra Uji'));
        $this->assertSame('Bentrok kerja.', collect($row['confirmations']['entries'])->firstWhere('name', 'Budi Uji')['note']);
    }

    public function test_mentor_cannot_manage_classes(): void
    {
        // Class CRUD sits under cohorts.manage (admin); mentors only record
        // attendance (spec 2026-07-18). Roles are already seeded by setUp().
        $mentor = User::factory()->create();
        $mentor->assignRole('mentor');
        $cohort = Cohort::factory()->create();

        $this->actingAs($mentor)
            ->postJson("/api/admin/cohorts/{$cohort->id}/sessions", ['title' => 'Kelas', 'type' => 'online'])
            ->assertForbidden();
    }
}
