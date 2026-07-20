<?php

namespace Tests\Feature;

use App\Models\Cohort;
use App\Models\CohortSession;
use App\Models\Enrollment;
use App\Models\Person;
use App\Models\Program;
use App\Models\SessionConfirmation;
use App\Models\StatusEvent;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberSessionConfirmationTest extends TestCase
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

    private function upcomingSession(): CohortSession
    {
        $cohort = Cohort::factory()->create(['program_id' => Program::factory()->active()->create()->id]);

        return CohortSession::factory()->for($cohort)->create(['scheduled_at' => now()->addDays(2)]);
    }

    public function test_member_sets_then_changes_confirmation_in_one_row(): void
    {
        [$user, $person] = $this->member();
        $session = $this->upcomingSession();
        $enrollment = Enrollment::create(['people_id' => $person->id, 'cohort_id' => $session->cohort_id]);

        $this->actingAs($user)
            ->post(route('member.session.confirm', $session), ['status' => 'attending'])
            ->assertRedirect()
            ->assertSessionHas('toast');

        $this->assertSame(1, SessionConfirmation::count());
        $this->assertSame('attending', SessionConfirmation::sole()->status);

        $this->actingAs($user)
            ->post(route('member.session.confirm', $session), [
                'status' => 'cannot_attend',
                'note' => 'Ada acara keluarga.',
            ])
            ->assertRedirect();

        $this->assertSame(1, SessionConfirmation::count());
        $row = SessionConfirmation::sole();
        $this->assertSame('cannot_attend', $row->status);
        $this->assertSame('Ada acara keluarga.', $row->note);
        $this->assertSame($enrollment->id, $row->enrollment_id);
    }

    public function test_switching_back_to_attending_clears_the_note(): void
    {
        [$user, $person] = $this->member();
        $session = $this->upcomingSession();
        $enrollment = Enrollment::create(['people_id' => $person->id, 'cohort_id' => $session->cohort_id]);
        SessionConfirmation::factory()->cannotAttend('Bentrok kerja.')->create([
            'cohort_session_id' => $session->id,
            'enrollment_id' => $enrollment->id,
        ]);

        $this->actingAs($user)
            ->post(route('member.session.confirm', $session), ['status' => 'attending'])
            ->assertRedirect();

        $row = SessionConfirmation::sole();
        $this->assertSame('attending', $row->status);
        $this->assertNull($row->note);
    }

    public function test_confirmation_freezes_once_the_class_started(): void
    {
        [$user, $person] = $this->member();
        $cohort = Cohort::factory()->create(['program_id' => Program::factory()->active()->create()->id]);
        $session = CohortSession::factory()->for($cohort)->create(['scheduled_at' => now()->subHour()]);
        Enrollment::create(['people_id' => $person->id, 'cohort_id' => $cohort->id]);

        $this->actingAs($user)
            ->post(route('member.session.confirm', $session), ['status' => 'attending'])
            ->assertSessionHasErrors('status');

        $this->assertSame(0, SessionConfirmation::count());
    }

    public function test_unscheduled_class_stays_confirmable(): void
    {
        [$user, $person] = $this->member();
        $cohort = Cohort::factory()->create(['program_id' => Program::factory()->active()->create()->id]);
        $session = CohortSession::factory()->for($cohort)->create(['scheduled_at' => null]);
        Enrollment::create(['people_id' => $person->id, 'cohort_id' => $cohort->id]);

        $this->actingAs($user)
            ->post(route('member.session.confirm', $session), ['status' => 'attending'])
            ->assertRedirect()
            ->assertSessionHas('toast');

        $this->assertSame(1, SessionConfirmation::count());
    }

    public function test_member_without_enrollment_gets_404(): void
    {
        [$user] = $this->member();
        $session = $this->upcomingSession();

        $this->actingAs($user)
            ->post(route('member.session.confirm', $session), ['status' => 'attending'])
            ->assertNotFound();
    }

    public function test_dropped_enrollment_cannot_confirm(): void
    {
        [$user, $person] = $this->member();
        $session = $this->upcomingSession();
        $enrollment = Enrollment::create(['people_id' => $person->id, 'cohort_id' => $session->cohort_id]);
        StatusEvent::create(['enrollment_id' => $enrollment->id, 'status' => 'dropped', 'occurred_at' => now()]);

        $this->actingAs($user)
            ->post(route('member.session.confirm', $session), ['status' => 'attending'])
            ->assertNotFound();
    }

    public function test_status_must_be_a_known_value(): void
    {
        [$user, $person] = $this->member();
        $session = $this->upcomingSession();
        Enrollment::create(['people_id' => $person->id, 'cohort_id' => $session->cohort_id]);

        $this->actingAs($user)
            ->post(route('member.session.confirm', $session), ['status' => 'maybe'])
            ->assertSessionHasErrors('status');
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $session = $this->upcomingSession();

        $this->post(route('member.session.confirm', $session), ['status' => 'attending'])
            ->assertRedirect(route('member.login'));
    }
}
