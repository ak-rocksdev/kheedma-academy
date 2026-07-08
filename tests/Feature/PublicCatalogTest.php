<?php

namespace Tests\Feature;

use App\Models\Cohort;
use App\Models\Program;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicCatalogTest extends TestCase
{
    use RefreshDatabase;

    /** Active program with an open-window Angkatan: open for registration. */
    private function openProgram(): Program
    {
        $program = Program::factory()->active()->create();
        Cohort::factory()->openWindow()->create(['program_id' => $program->id]);

        return $program;
    }

    public function test_chooser_lists_only_open_programs(): void
    {
        $open = $this->openProgram();
        $open->update(['name' => 'Program Terbuka']);

        $inactive = Program::factory()->inactive()->create(['name' => 'Program Tertutup']);
        Cohort::factory()->openWindow()->create(['program_id' => $inactive->id]);

        Program::factory()->draft()->create(['name' => 'Program Draf']);

        $expired = Program::factory()->active()->create(['name' => 'Program Kedaluwarsa']);
        Cohort::factory()->closedWindow()->create(['program_id' => $expired->id]);

        $this->get('/daftar')
            ->assertOk()
            ->assertSee('Program Terbuka')
            ->assertDontSee('Program Tertutup')
            ->assertDontSee('Program Draf')
            ->assertDontSee('Program Kedaluwarsa')
            ->assertSee('Komunitas');
    }

    public function test_chooser_without_programs_still_offers_community(): void
    {
        $this->get('/daftar')
            ->assertOk()
            ->assertSee('Komunitas');
    }

    public function test_landing_shows_cta_when_open(): void
    {
        $program = $this->openProgram();

        $this->get("/program/{$program->slug}")
            ->assertOk()
            ->assertSee($program->name)
            ->assertSee(route('program.apply', $program), false);
    }

    public function test_landing_shows_closed_state_when_inactive(): void
    {
        $program = Program::factory()->inactive()->create();
        Cohort::factory()->openWindow()->create(['program_id' => $program->id]);

        $this->get("/program/{$program->slug}")
            ->assertOk()
            ->assertSee('Pendaftaran ditutup')
            ->assertDontSee(route('program.apply', $program), false);
    }

    public function test_landing_shows_class_start_date_when_open(): void
    {
        $program = Program::factory()->active()->create();
        Cohort::factory()->openWindow()->create([
            'program_id' => $program->id,
            'start_date' => '2026-08-01',
        ]);

        $this->get("/program/{$program->slug}")
            ->assertOk()
            ->assertSee('Kelas dimulai')
            ->assertSee('1 Agustus 2026');
    }

    public function test_chooser_shows_affiliate_section_with_locked_teaser(): void
    {
        Program::factory()->affiliate(1)->active()->create(['name' => 'Affiliate Kelas Satu']);

        $this->get('/daftar')
            ->assertOk()
            ->assertSee('Kheedma Affiliate Community')
            ->assertSee('Affiliate Kelas Satu')
            ->assertSee('Terkunci')
            ->assertSee('data-lock-trigger', false);
    }

    public function test_chooser_hides_inactive_affiliate_classes(): void
    {
        Program::factory()->affiliate(1)->draft()->create(['name' => 'Affiliate Rahasia']);

        $this->get('/daftar')
            ->assertOk()
            ->assertDontSee('Affiliate Rahasia');
    }

    public function test_affiliate_landing_shows_locked_state_not_apply_cta(): void
    {
        $program = Program::factory()->affiliate(1)->active()->create();
        Cohort::factory()->create([
            'program_id' => $program->id,
            'registration_opens_at' => now()->subDay(),
            'registration_closes_at' => now()->addWeek(),
        ]);

        $this->get(route('program.show', $program))
            ->assertOk()
            ->assertSee('Terkunci')
            ->assertDontSee(route('program.apply', $program), false);
    }

    public function test_locked_message_falls_back_to_config_default(): void
    {
        Program::factory()->affiliate(1)->active()->create(['locked_message' => null]);

        $this->get('/daftar')
            ->assertOk()
            ->assertSee(e(config('kheedma.default_locked_message')), false);
    }
}
