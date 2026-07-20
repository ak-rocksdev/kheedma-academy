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

    /** Sending requires the mentor to have marked this enrollment hadir. */
    private function attend(Assignment $assignment, Enrollment $enrollment): void
    {
        Attendance::create([
            'cohort_session_id' => $assignment->session->id,
            'enrollment_id' => $enrollment->id,
        ]);
    }

    public function test_waiting_submission_blocks_new_rows_until_graded(): void
    {
        [$user, $person] = $this->member();
        $assignment = $this->assignment();
        $enrollment = Enrollment::create(['people_id' => $person->id, 'cohort_id' => $assignment->session->cohort_id]);
        $this->attend($assignment, $enrollment);

        $this->actingAs($user)
            ->post(route('member.assignment.submit', $assignment), [
                'url' => 'https://drive.google.com/jawaban-1',
                'note' => 'Versi pertama.',
            ])
            ->assertRedirect()
            ->assertSessionHas('toast');

        // Spam-guard: while ungraded, a second SEND is rejected (edit instead).
        $this->actingAs($user)
            ->post(route('member.assignment.submit', $assignment), ['url' => 'https://drive.google.com/jawaban-2'])
            ->assertSessionHasErrors('url');
        $this->assertSame(1, AssignmentSubmission::count());

        // Once graded, a retake appends a NEW row again.
        $first = AssignmentSubmission::sole();
        $first->score = 60;
        $first->graded_at = now();
        $first->save();

        $this->actingAs($user)
            ->post(route('member.assignment.submit', $assignment), ['url' => 'https://drive.google.com/jawaban-2'])
            ->assertRedirect();

        $this->assertSame(2, AssignmentSubmission::count());
        $this->assertSame('https://drive.google.com/jawaban-2', AssignmentSubmission::latest('id')->first()->url);
    }

    public function test_member_edits_waiting_submission_in_place(): void
    {
        [$user, $person] = $this->member();
        $assignment = $this->assignment();
        $enrollment = Enrollment::create(['people_id' => $person->id, 'cohort_id' => $assignment->session->cohort_id]);
        $submission = AssignmentSubmission::factory()->create([
            'assignment_id' => $assignment->id,
            'enrollment_id' => $enrollment->id,
            'url' => 'https://drive.google.com/salah',
        ]);

        $this->actingAs($user)
            ->patch(route('member.submission.update', $submission), [
                'url' => 'drive.google.com/benar',
                'note' => 'Link sudah dibetulkan.',
            ])
            ->assertRedirect()
            ->assertSessionHas('toast');

        $this->assertSame(1, AssignmentSubmission::count());
        $fresh = $submission->fresh();
        $this->assertSame('https://drive.google.com/benar', $fresh->url);
        $this->assertSame('Link sudah dibetulkan.', $fresh->note);
    }

    public function test_edit_locks_after_the_window(): void
    {
        [$user, $person] = $this->member();
        $assignment = $this->assignment();
        $enrollment = Enrollment::create(['people_id' => $person->id, 'cohort_id' => $assignment->session->cohort_id]);
        $submission = AssignmentSubmission::factory()->create([
            'assignment_id' => $assignment->id,
            'enrollment_id' => $enrollment->id,
            'url' => 'https://drive.google.com/awal',
            'created_at' => now()->subHours(7),
        ]);

        $this->actingAs($user)
            ->patch(route('member.submission.update', $submission), ['url' => 'drive.google.com/telat'])
            ->assertSessionHasErrors('url');

        $this->assertSame('https://drive.google.com/awal', $submission->fresh()->url);
    }

    public function test_cannot_edit_graded_or_foreign_submission(): void
    {
        [$user, $person] = $this->member();
        $assignment = $this->assignment();
        $enrollment = Enrollment::create(['people_id' => $person->id, 'cohort_id' => $assignment->session->cohort_id]);
        $graded = AssignmentSubmission::factory()->graded(70)->create([
            'assignment_id' => $assignment->id,
            'enrollment_id' => $enrollment->id,
        ]);

        $this->actingAs($user)
            ->patch(route('member.submission.update', $graded), ['url' => 'drive.google.com/x'])
            ->assertNotFound();

        [$stranger] = $this->member();
        $pending = AssignmentSubmission::factory()->create([
            'assignment_id' => $assignment->id,
            'enrollment_id' => $enrollment->id,
        ]);

        $this->actingAs($stranger)
            ->patch(route('member.submission.update', $pending), ['url' => 'drive.google.com/x'])
            ->assertNotFound();
    }

    public function test_client_cannot_smuggle_grade_fields_into_a_submission(): void
    {
        [$user, $person] = $this->member();
        $assignment = $this->assignment();
        $enrollment = Enrollment::create(['people_id' => $person->id, 'cohort_id' => $assignment->session->cohort_id]);
        $this->attend($assignment, $enrollment);

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

    public function test_submission_requires_attendance(): void
    {
        [$user, $person] = $this->member();
        $assignment = $this->assignment();
        Enrollment::create(['people_id' => $person->id, 'cohort_id' => $assignment->session->cohort_id]);

        // Enrolled but never marked hadir: the assignment stays locked.
        $this->actingAs($user)
            ->post(route('member.assignment.submit', $assignment), ['url' => 'https://drive.google.com/jawaban'])
            ->assertSessionHasErrors('url');

        $this->assertSame(0, AssignmentSubmission::count());
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

    public function test_bare_link_without_scheme_is_normalized_to_https(): void
    {
        [$user, $person] = $this->member();
        $assignment = $this->assignment();
        $enrollment = Enrollment::create(['people_id' => $person->id, 'cohort_id' => $assignment->session->cohort_id]);
        $this->attend($assignment, $enrollment);

        // The form shows a fixed https:// prefix, so members type just the link.
        $this->actingAs($user)
            ->post(route('member.assignment.submit', $assignment), ['url' => 'drive.google.com/jawaban-polos'])
            ->assertRedirect()
            ->assertSessionHas('toast');

        $this->assertSame('https://drive.google.com/jawaban-polos', AssignmentSubmission::sole()->url);
    }

    public function test_url_host_needs_a_real_domain(): void
    {
        [$user, $person] = $this->member();
        $assignment = $this->assignment();
        $enrollment = Enrollment::create(['people_id' => $person->id, 'cohort_id' => $assignment->session->cohort_id]);
        $this->attend($assignment, $enrollment);

        // Normalization turns 'bukan-link' into https://bukan-link, which is a
        // syntactically valid URL - the dotted-host rule must still reject it.
        $this->actingAs($user)
            ->post(route('member.assignment.submit', $assignment), ['url' => 'bukan-link'])
            ->assertSessionHasErrors('url');

        $this->assertSame(0, AssignmentSubmission::count());
    }

    public function test_url_must_be_a_valid_https_link(): void
    {
        [$user, $person] = $this->member();
        $assignment = $this->assignment();
        $enrollment = Enrollment::create(['people_id' => $person->id, 'cohort_id' => $assignment->session->cohort_id]);
        $this->attend($assignment, $enrollment);

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
