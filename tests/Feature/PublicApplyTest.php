<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Cohort;
use App\Models\Program;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PublicApplyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
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
            'province_code' => '32',
            'city_code' => '3273',
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
}
