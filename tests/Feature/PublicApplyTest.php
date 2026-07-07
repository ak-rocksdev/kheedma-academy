<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Cohort;
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
            'password' => 'rahasia-kuat',
            'province_code' => '32',
            'city_code' => '3273',
            'birth_date' => '2000-01-15',
            'motivation' => 'Ingin serius belajar affiliate.',
            'referral_source' => 'instagram',
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
        $this->assertSame('instagram', $application->referral_source);
        $this->assertSame('pending', $application->status);
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
            ->assertRedirect(route('daftar.thankyou'));

        $this->assertSame(1, Application::count());
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
}
