<?php

namespace Tests\Feature;

use App\Models\ContentSection;
use App\Models\Program;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentSectionModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_community_scope_returns_only_community_sections_in_order(): void
    {
        $program = Program::factory()->create();
        ContentSection::factory()->forProgram($program)->create();
        $second = ContentSection::factory()->create(['sort_order' => 2]);
        $first = ContentSection::factory()->create(['sort_order' => 1]);

        $sections = ContentSection::forCommunity()->get();

        $this->assertSame([$first->id, $second->id], $sections->pluck('id')->all());
    }

    public function test_program_sections_relation_is_ordered(): void
    {
        $program = Program::factory()->create();
        $b = ContentSection::factory()->forProgram($program)->create(['sort_order' => 2]);
        $a = ContentSection::factory()->forProgram($program)->create(['sort_order' => 1]);

        $this->assertSame([$a->id, $b->id], $program->sections()->pluck('id')->all());
    }

    public function test_deleting_program_cascades_to_sections(): void
    {
        $program = Program::factory()->create();
        $section = ContentSection::factory()->forProgram($program)->create();

        $program->delete();

        $this->assertDatabaseMissing('content_sections', ['id' => $section->id]);
    }
}
