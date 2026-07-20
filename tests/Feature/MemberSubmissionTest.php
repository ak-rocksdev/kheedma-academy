<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Cohort;
use App\Models\CohortSession;
use App\Models\Enrollment;
use App\Models\Person;
use App\Models\Program;
use App\Models\StatusEvent;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberSubmissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    /** @return array{0: User, 1: Person} */
    private function member(): array
    {
        $user = User::factory()->create();
        $user->assignRole('participant');
        $person = Person::create([
            'name' => 'Member '.fake()->unique()->numberBetween(1, 99999),
            'phone' => '+628'.fake()->unique()->numerify('##########'),
            'email' => fake()->unique()->safeEmail(),
        ]);
        $person->user()->associate($user);
        $person->save();

        return [$user, $person];
    }

    private function assignment(): Assignment
    {
        $cohort = Cohort::factory()->create(['program_id' => Program::factory()->active()->create()->id]);
        $session = CohortSession::factory()->for($cohort)->create();

        return Assignment::factory()->for($session, 'session')->create();
    }

    public function test_enrolled_member_submits_and_resubmits(): void
    {
        [$user, $person] = $this->member();
        $assignment = $this->assignment();
        Enrollment::create(['people_id' => $person->id, 'cohort_id' => $assignment->session->cohort_id]);

        $this->actingAs($user)
            ->post(route('member.assignment.submit', $assignment), [
                'url' => 'https://drive.google.com/jawaban-1',
                'note' => 'Versi pertama.',
            ])
            ->assertRedirect()
            ->assertSessionHas('tugas_terkirim', $assignment->id);

        // Resubmission while still ungraded is allowed: append, never update.
        $this->actingAs($user)
            ->post(route('member.assignment.submit', $assignment), ['url' => 'https://drive.google.com/jawaban-2'])
            ->assertRedirect();

        $this->assertSame(2, AssignmentSubmission::count());
        $this->assertSame('https://drive.google.com/jawaban-2', AssignmentSubmission::latest('id')->first()->url);
    }

    public function test_client_cannot_smuggle_grade_fields_into_a_submission(): void
    {
        [$user, $person] = $this->member();
        $assignment = $this->assignment();
        Enrollment::create(['people_id' => $person->id, 'cohort_id' => $assignment->session->cohort_id]);

        $this->actingAs($user)
            ->post(route('member.assignment.submit', $assignment), [
                'url' => 'https://drive.google.com/jawaban',
                'score' => 100,
                'graded_by' => 1,
            ])
            ->assertRedirect();

        $submission = AssignmentSubmission::sole();
        $this->assertNull($submission->score);
        $this->assertNull($submission->graded_by);
    }

    public function test_member_without_enrollment_gets_404(): void
    {
        [$user] = $this->member();
        $assignment = $this->assignment();

        $this->actingAs($user)
            ->post(route('member.assignment.submit', $assignment), ['url' => 'https://x.example/a'])
            ->assertNotFound();
    }

    public function test_dropped_enrollment_cannot_submit(): void
    {
        [$user, $person] = $this->member();
        $assignment = $this->assignment();
        $enrollment = Enrollment::create(['people_id' => $person->id, 'cohort_id' => $assignment->session->cohort_id]);
        StatusEvent::create(['enrollment_id' => $enrollment->id, 'status' => 'dropped', 'occurred_at' => now()]);

        $this->actingAs($user)
            ->post(route('member.assignment.submit', $assignment), ['url' => 'https://x.example/a'])
            ->assertNotFound();
    }

    public function test_url_must_be_a_valid_https_link(): void
    {
        [$user, $person] = $this->member();
        $assignment = $this->assignment();
        Enrollment::create(['people_id' => $person->id, 'cohort_id' => $assignment->session->cohort_id]);

        $this->actingAs($user)
            ->from('/akun?bagian=kelas')
            ->post(route('member.assignment.submit', $assignment), ['url' => 'bukan-link'])
            ->assertRedirect('/akun?bagian=kelas')
            ->assertSessionHasErrors('url');
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $assignment = $this->assignment();

        // If the app's guest redirect targets a different named route, mirror
        // whatever assertion MemberAreaTest uses for guests hitting /akun.
        $this->post(route('member.assignment.submit', $assignment), ['url' => 'https://x.example/a'])
            ->assertRedirect(route('member.login'));
    }
}
