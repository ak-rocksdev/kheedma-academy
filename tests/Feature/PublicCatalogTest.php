<?php

namespace Tests\Feature;

use App\Models\Program;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_chooser_lists_only_open_programs(): void
    {
        $open = Program::factory()->active()->create(['name' => 'Program Terbuka']);
        Program::factory()->inactive()->create(['name' => 'Program Tertutup']);
        Program::factory()->draft()->create(['name' => 'Program Draf']);
        Program::factory()->windowClosed()->create(['name' => 'Program Kedaluwarsa']);

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
        $program = Program::factory()->active()->create();

        $this->get("/program/{$program->slug}")
            ->assertOk()
            ->assertSee($program->name)
            ->assertSee(route('program.apply', $program), false);
    }

    public function test_landing_shows_closed_state_when_inactive(): void
    {
        $program = Program::factory()->inactive()->create();

        $this->get("/program/{$program->slug}")
            ->assertOk()
            ->assertSee('Pendaftaran ditutup')
            ->assertDontSee(route('program.apply', $program), false);
    }
}
