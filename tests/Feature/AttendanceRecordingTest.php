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

class AttendanceRecordingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    /** @return array{0: CohortSession, 1: Enrollment, 2: Enrollment} */
    private function sessionWithTwoEnrollments(): array
    {
        $cohort = Cohort::factory()->create([
            'program_id' => Program::factory()->active()->create()->id,
            'required_attendance' => 1,
        ]);
        $session = CohortSession::factory()->create(['cohort_id' => $cohort->id]);

        $make = function () use ($cohort) {
            $person = Person::create([
                'name' => 'Peserta '.fake()->unique()->numberBetween(1, 99999),
                'phone' => '+628'.fake()->unique()->numerify('##########'),
                'email' => fake()->unique()->safeEmail(),
            ]);

            return Enrollment::create(['people_id' => $person->id, 'cohort_id' => $cohort->id]);
        };

        return [$session, $make(), $make()];
    }

    public function test_declarative_set_adds_and_removes(): void
    {
        [$session, $a, $b] = $this->sessionWithTwoEnrollments();
        $admin = User::factory()->admin()->create();

        // Mark both hadir.
        $this->actingAs($admin)
            ->putJson("/api/admin/sessions/{$session->id}/attendance", ['enrollment_ids' => [$a->id, $b->id]])
            ->assertOk();
        $this->assertSame(2, Attendance::count());
        $this->assertSame($admin->id, Attendance::first()->marked_by);

        // Correct: only A hadir. B's row removed, B's auto-completion retracted.
        $this->actingAs($admin)
            ->putJson("/api/admin/sessions/{$session->id}/attendance", ['enrollment_ids' => [$a->id]])
            ->assertOk();
        $this->assertSame(1, Attendance::count());
        $this->assertSame('completed', $a->fresh()->latestStatusEvent->status);
        $this->assertNull($b->fresh()->latestStatusEvent);
    }

    public function test_enrollments_must_belong_to_the_sessions_cohort(): void
    {
        [$session] = $this->sessionWithTwoEnrollments();
        $otherCohort = Cohort::factory()->create(['program_id' => Program::factory()->active()->create()->id]);
        $foreign = Enrollment::create([
            'people_id' => Person::create([
                'name' => 'Asing', 'phone' => '+628999999999', 'email' => 'asing@example.test',
            ])->id,
            'cohort_id' => $otherCohort->id,
        ]);

        $this->actingAs(User::factory()->admin()->create())
            ->putJson("/api/admin/sessions/{$session->id}/attendance", ['enrollment_ids' => [$foreign->id]])
            ->assertStatus(422);
    }

    public function test_mentor_can_record_attendance_but_participant_cannot(): void
    {
        [$session, $a] = $this->sessionWithTwoEnrollments();

        $mentor = User::factory()->mentor()->create();
        $this->actingAs($mentor)
            ->putJson("/api/admin/sessions/{$session->id}/attendance", ['enrollment_ids' => [$a->id]])
            ->assertOk();

        $participant = User::factory()->create();
        $participant->assignRole('participant');
        $this->actingAs($participant)
            ->putJson("/api/admin/sessions/{$session->id}/attendance", ['enrollment_ids' => []])
            ->assertForbidden();
    }
}
