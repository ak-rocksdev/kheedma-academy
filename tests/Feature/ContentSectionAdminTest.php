<?php

namespace Tests\Feature;

use App\Models\ContentSection;
use App\Models\Program;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentSectionAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    public function test_requires_content_manage_permission(): void
    {
        $mentor = User::factory()->mentor()->create();

        $this->actingAs($mentor)->getJson('/api/admin/content-sections?page=community')->assertForbidden();
        $this->actingAs($mentor)->postJson('/api/admin/content-sections', [])->assertForbidden();
    }

    public function test_lists_sections_of_a_page_in_order(): void
    {
        $admin = User::factory()->admin()->create();
        $b = ContentSection::factory()->create(['sort_order' => 2]);
        $a = ContentSection::factory()->create(['sort_order' => 1]);
        ContentSection::factory()->forProgram()->create(); // other page — excluded

        $this->actingAs($admin)->getJson('/api/admin/content-sections?page=community')
            ->assertOk()
            ->assertJsonPath('sections.0.id', $a->id)
            ->assertJsonPath('sections.1.id', $b->id)
            ->assertJsonCount(2, 'sections');
    }

    public function test_create_appends_and_sanitizes_body(): void
    {
        $admin = User::factory()->admin()->create();
        ContentSection::factory()->create(['sort_order' => 0]);

        $response = $this->actingAs($admin)->postJson('/api/admin/content-sections', [
            'page' => 'community',
            'heading' => 'Jadwal belajar',
            'body' => '<p>Aman</p><script>alert(1)</script>',
        ])->assertCreated();

        $response->assertJsonPath('section.sort_order', 1);
        $this->assertStringNotContainsString('<script', $response->json('section.body'));
        $this->assertStringContainsString('Aman', $response->json('section.body'));
    }

    public function test_program_id_required_for_program_page_and_prohibited_for_community(): void
    {
        $admin = User::factory()->admin()->create();
        $program = Program::factory()->create();

        $this->actingAs($admin)->postJson('/api/admin/content-sections', [
            'page' => 'program', 'body' => '<p>x</p>',
        ])->assertStatus(422)->assertJsonValidationErrors('program_id');

        $this->actingAs($admin)->postJson('/api/admin/content-sections', [
            'page' => 'community', 'program_id' => $program->id, 'body' => '<p>x</p>',
        ])->assertStatus(422)->assertJsonValidationErrors('program_id');

        $this->actingAs($admin)->postJson('/api/admin/content-sections', [
            'page' => 'program', 'program_id' => $program->id, 'body' => '<p>x</p>',
        ])->assertCreated();
    }

    public function test_update_sanitizes_and_saves(): void
    {
        $admin = User::factory()->admin()->create();
        $section = ContentSection::factory()->create();

        $this->actingAs($admin)->patchJson("/api/admin/content-sections/{$section->id}", [
            'heading' => 'Baru',
            'body' => '<p onclick="x()">Bersih</p>',
        ])->assertOk();

        $section->refresh();
        $this->assertSame('Baru', $section->heading);
        $this->assertStringNotContainsString('onclick', $section->body);
    }

    public function test_delete_removes_section(): void
    {
        $admin = User::factory()->admin()->create();
        $section = ContentSection::factory()->create();

        $this->actingAs($admin)->deleteJson("/api/admin/content-sections/{$section->id}")->assertOk();

        $this->assertDatabaseMissing('content_sections', ['id' => $section->id]);
    }

    public function test_reorder_persists_new_order(): void
    {
        $admin = User::factory()->admin()->create();
        $a = ContentSection::factory()->create(['sort_order' => 0]);
        $b = ContentSection::factory()->create(['sort_order' => 1]);

        $this->actingAs($admin)->patchJson('/api/admin/content-sections-order', [
            'page' => 'community',
            'ids' => [$b->id, $a->id],
        ])->assertOk();

        $this->assertSame(0, $b->fresh()->sort_order);
        $this->assertSame(1, $a->fresh()->sort_order);
    }

    public function test_reorder_rejects_id_set_mismatch(): void
    {
        $admin = User::factory()->admin()->create();
        $a = ContentSection::factory()->create();
        $other = ContentSection::factory()->forProgram()->create();

        $this->actingAs($admin)->patchJson('/api/admin/content-sections-order', [
            'page' => 'community',
            'ids' => [$a->id, $other->id],
        ])->assertStatus(422);
    }
}
