<?php

namespace Tests\Feature;

use App\Models\ContentSection;
use App\Models\Program;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentSectionPublicTest extends TestCase
{
    use RefreshDatabase;

    public function test_community_page_renders_sections_in_order(): void
    {
        ContentSection::factory()->create(['heading' => 'Kedua', 'sort_order' => 1]);
        ContentSection::factory()->create([
            'heading' => 'Pertama',
            'body' => '<p>Isi <strong>penting</strong></p>',
            'sort_order' => 0,
        ]);

        $this->get('/komunitas')
            ->assertOk()
            ->assertSeeInOrder(['Pertama', 'Kedua'])
            ->assertSee('<strong>penting</strong>', false);
    }

    public function test_community_page_still_works_with_zero_sections(): void
    {
        $this->get('/komunitas')->assertOk()->assertSee('Kheedma Affiliate Community.');
    }

    public function test_program_page_renders_sections_when_present(): void
    {
        $program = Program::factory()->create(['status' => 'active', 'description' => 'Deskripsi lama']);
        ContentSection::factory()->forProgram($program)->create([
            'heading' => 'Apa yang kamu pelajari',
            'body' => '<p>Materi lengkap</p>',
        ]);

        $this->get("/program/{$program->slug}")
            ->assertOk()
            ->assertSee('Apa yang kamu pelajari')
            ->assertSee('Materi lengkap')
            ->assertDontSee('Deskripsi lama');
    }

    public function test_program_page_falls_back_to_description(): void
    {
        $program = Program::factory()->create(['status' => 'active', 'description' => 'Deskripsi lama']);

        $this->get("/program/{$program->slug}")->assertOk()->assertSee('Deskripsi lama');
    }
}
