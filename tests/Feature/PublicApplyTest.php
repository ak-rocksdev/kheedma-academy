<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Cohort;
use App\Models\Enrollment;
use App\Models\Person;
use App\Models\Program;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PublicApplyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        // Minimal region fixtures (laravolt tables) for the address validation.
        DB::table('indonesia_provinces')->insert([
            'code' => '32', 'name' => 'JAWA BARAT', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('indonesia_cities')->insert([
            'code' => '3273', 'province_code' => '32', 'name' => 'KOTA BANDUNG', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** Active program with an open-window Angkatan: open for registration. */
    private function openProgram(): Program
    {
        $program = Program::factory()->active()->create();
        Cohort::factory()->openWindow()->create(['program_id' => $program->id]);

        return $program;
    }

    /** @return array<string, string> */
    private function validPayload(): array
    {
        return [
            'name' => 'Budi Santoso',
            'phone' => '081234567890',
            'email' => 'budi@example.test',
            'password' => '246810',
            'province_code' => '32',
            'city_code' => '3273',
            'birth_date' => '2000-01-15',
            'gender' => 'male',
            'motivation' => 'Ingin serius belajar affiliate.',
            'referral_source' => 'instagram',
            'followed_socials' => 1,
        ];
    }

    public function test_form_renders_for_open_program(): void
    {
        $program = $this->openProgram();

        $this->get("/program/{$program->slug}/daftar")
            ->assertOk()
            ->assertSee($program->name);
    }

    public function test_form_redirects_to_landing_when_closed(): void
    {
        $program = Program::factory()->inactive()->create();

        $this->get("/program/{$program->slug}/daftar")
            ->assertRedirect("/program/{$program->slug}");
    }

    public function test_draft_program_is_not_found(): void
    {
        $program = Program::factory()->draft()->create();

        $this->get("/program/{$program->slug}/daftar")->assertNotFound();
        $this->get("/program/{$program->slug}")->assertNotFound();
    }

    public function test_submission_links_program_and_referral_source(): void
    {
        $program = $this->openProgram();

        $this->post("/program/{$program->slug}/daftar", $this->validPayload())
            ->assertRedirect(route('daftar.thankyou'));

        $application = Application::sole();
        $this->assertSame($program->id, $application->program_id);
        $this->assertSame($program->openCohort()->id, $application->cohort_id);
        $this->assertSame('instagram', $application->referral_source);
        $this->assertSame('pending', $application->status);
    }

    public function test_program_without_any_cohort_cannot_receive_registrations(): void
    {
        $program = Program::factory()->active()->create();

        $this->get("/program/{$program->slug}/daftar")
            ->assertRedirect("/program/{$program->slug}");

        $this->post("/program/{$program->slug}/daftar", $this->validPayload())
            ->assertRedirect("/program/{$program->slug}");

        $this->assertSame(0, Application::count());
    }

    public function test_submission_attaches_nearest_starting_open_cohort(): void
    {
        $program = Program::factory()->active()->create();
        Cohort::factory()->openWindow()->create(['program_id' => $program->id, 'start_date' => now()->addMonth()]);
        $nearest = Cohort::factory()->openWindow()->create(['program_id' => $program->id, 'start_date' => now()->addWeek()]);

        $this->post("/program/{$program->slug}/daftar", $this->validPayload())
            ->assertRedirect(route('daftar.thankyou'));

        $this->assertSame($nearest->id, Application::sole()->cohort_id);
    }

    public function test_referral_source_is_required_and_validated(): void
    {
        $program = $this->openProgram();

        $this->from("/program/{$program->slug}/daftar")
            ->post("/program/{$program->slug}/daftar", [...$this->validPayload(), 'referral_source' => 'radio'])
            ->assertSessionHasErrors('referral_source');
    }

    public function test_submission_rejected_when_program_closed(): void
    {
        $program = Program::factory()->active()->create();
        Cohort::factory()->closedWindow()->create(['program_id' => $program->id]);

        $this->post("/program/{$program->slug}/daftar", $this->validPayload())
            ->assertRedirect("/program/{$program->slug}");

        $this->assertSame(0, Application::count());
    }

    /** @return array<string, string> */
    private function authenticatedPayload(): array
    {
        $payload = $this->validPayload();
        unset($payload['password']);

        return $payload;
    }

    public function test_duplicate_pending_submission_does_not_create_a_second_application(): void
    {
        $program = $this->openProgram();

        $this->post("/program/{$program->slug}/daftar", $this->validPayload());
        // The client is now the logged-in participant created by the first post.
        $this->post("/program/{$program->slug}/daftar", $this->authenticatedPayload())
            ->assertRedirect(route('program.show', $program))
            ->assertSessionHas('application_notice');

        $this->assertSame(1, Application::count());
    }

    public function test_accepted_application_blocks_reapply_with_honest_notice(): void
    {
        $program = $this->openProgram();
        $this->post("/program/{$program->slug}/daftar", $this->validPayload());
        Application::query()->update(['status' => 'accepted']);

        $this->post("/program/{$program->slug}/daftar", $this->authenticatedPayload())
            ->assertRedirect(route('program.show', $program))
            ->assertSessionHas('application_notice');

        $this->assertSame(1, Application::count());
    }

    public function test_enrolled_person_cannot_reapply_even_after_rejection(): void
    {
        $program = $this->openProgram();
        $this->post("/program/{$program->slug}/daftar", $this->validPayload());
        $application = Application::first();
        $application->update(['status' => 'rejected']);
        $cohort = Cohort::factory()->create(['program_id' => $program->id]);
        Enrollment::create(['people_id' => $application->people_id, 'cohort_id' => $cohort->id]);

        $this->post("/program/{$program->slug}/daftar", $this->authenticatedPayload())
            ->assertRedirect(route('program.show', $program))
            ->assertSessionHas('application_notice');

        $this->assertSame(1, Application::count());
    }

    public function test_guest_phone_with_active_application_gets_program_aware_message(): void
    {
        $program = $this->openProgram();
        $this->post("/program/{$program->slug}/daftar", $this->validPayload());
        auth()->logout();

        $this->post("/program/{$program->slug}/daftar", $this->validPayload())
            ->assertSessionHasErrors(['phone' => 'Nomor ini sudah terdaftar di program ini. Masuk untuk melihat status.']);
    }

    public function test_guest_phone_with_account_elsewhere_keeps_generic_message(): void
    {
        $first = $this->openProgram();
        $other = $this->openProgram();
        $this->post("/program/{$first->slug}/daftar", $this->validPayload());
        auth()->logout();

        $this->post("/program/{$other->slug}/daftar", $this->validPayload())
            ->assertSessionHasErrors(['phone' => 'Nomor ini sudah punya akun. Silakan masuk.']);
    }

    public function test_rejected_applicant_can_reapply(): void
    {
        $program = $this->openProgram();

        $this->post("/program/{$program->slug}/daftar", $this->validPayload());
        Application::sole()->update(['status' => 'rejected']);

        $this->post("/program/{$program->slug}/daftar", $this->authenticatedPayload())
            ->assertRedirect(route('daftar.thankyou'));

        $this->assertSame(2, Application::count());
    }

    public function test_pending_application_elsewhere_does_not_block_another_program(): void
    {
        $first = $this->openProgram();
        $second = $this->openProgram();

        $this->post("/program/{$first->slug}/daftar", $this->validPayload());
        // The client is now the logged-in participant created by the first post.
        $this->post("/program/{$second->slug}/daftar", $this->authenticatedPayload());

        $this->assertSame(2, Application::count());
    }

    public function test_birth_date_and_motivation_are_required(): void
    {
        $program = $this->openProgram();

        $this->from("/program/{$program->slug}/daftar")
            ->post("/program/{$program->slug}/daftar", [
                ...$this->validPayload(),
                'birth_date' => '',
                'motivation' => '',
            ])
            ->assertSessionHasErrors(['birth_date', 'motivation']);
    }

    public function test_gender_and_social_follow_are_required(): void
    {
        $program = $this->openProgram();

        $this->from("/program/{$program->slug}/daftar")
            ->post("/program/{$program->slug}/daftar", [
                ...$this->validPayload(),
                'gender' => '',
                'followed_socials' => '',
            ])
            ->assertSessionHasErrors(['gender', 'followed_socials']);
    }

    public function test_affiliate_chain_requires_followers_when_tiktok_given(): void
    {
        $program = $this->openProgram();

        $this->from("/program/{$program->slug}/daftar")
            ->post("/program/{$program->slug}/daftar", [
                ...$this->validPayload(),
                'tiktok_username' => 'budi.tiktok',
            ])
            ->assertSessionHasErrors('tiktok_followers');
    }

    public function test_affiliate_dependents_are_nulled_without_tiktok(): void
    {
        $program = $this->openProgram();

        $this->post("/program/{$program->slug}/daftar", [
            ...$this->validPayload(),
            'tiktok_username' => '',
            'tiktok_followers' => 1500,
            'has_started_affiliate' => 1,
            'affiliate_level' => 3,
            'affiliate_gmv_range' => '0-50',
        ])->assertRedirect(route('daftar.thankyou'));

        $person = Person::sole();
        $this->assertNull($person->tiktok_followers);
        $this->assertNull($person->has_started_affiliate);
        $this->assertNull($person->affiliate_level);
        $this->assertNull($person->affiliate_gmv_range);
    }

    public function test_guest_cannot_open_affiliate_apply_form(): void
    {
        $program = Program::factory()->affiliate(1)->active()->create();
        Cohort::factory()->openWindow()->create(['program_id' => $program->id]);

        $this->get(route('program.apply', $program))
            ->assertRedirect(route('program.show', $program));
    }

    public function test_guest_post_to_affiliate_apply_is_rejected(): void
    {
        $program = Program::factory()->affiliate(1)->active()->create();
        Cohort::factory()->openWindow()->create(['program_id' => $program->id]);

        // A valid payload keeps the FormRequest's own auto-validation (which
        // runs before the controller body) out of the way, so this actually
        // exercises the eligibility guard rather than the validator.
        $this->post(route('program.apply.store', $program), $this->validPayload())
            ->assertRedirect(route('program.show', $program));

        $this->assertSame(0, Application::count());
    }
}
