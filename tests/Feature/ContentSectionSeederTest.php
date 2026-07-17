<?php

namespace Tests\Feature;

use App\Models\ContentSection;
use Database\Seeders\ContentSectionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentSectionSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_three_community_sections_once(): void
    {
        $this->seed(ContentSectionSeeder::class);
        $this->seed(ContentSectionSeeder::class); // idempotent

        $sections = ContentSection::forCommunity()->get();
        $this->assertCount(3, $sections);
        $this->assertSame('Komunitas belajar, bukan sekadar kelas jualan.', $sections[0]->heading);
        $this->assertStringContainsString('Silabus program', $sections[1]->body);
        $this->assertStringContainsString('Komitmen dan etika belajar.', $sections[2]->heading);
    }
}
