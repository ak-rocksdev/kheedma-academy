<?php

namespace Tests\Feature;

use App\Models\ContentSection;
use App\Models\Media;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        Storage::fake('public');
    }

    public function test_requires_content_manage_permission(): void
    {
        $mentor = User::factory()->mentor()->create();

        $this->actingAs($mentor)->getJson('/api/admin/media')->assertForbidden();
    }

    public function test_upload_stores_file_and_metadata(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post('/api/admin/media', [
            'file' => UploadedFile::fake()->image('kelas.jpg', 800, 600),
        ], ['Accept' => 'application/json'])->assertCreated();

        $media = Media::sole();
        Storage::disk('public')->assertExists($media->path);
        $this->assertSame('kelas.jpg', $media->original_name);
        $this->assertSame($admin->id, $media->uploaded_by);
        $this->assertStringStartsWith('/storage/media/', $response->json('media.url'));
    }

    public function test_pdf_allowed_but_docx_and_oversize_rejected(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/api/admin/media', [
            'file' => UploadedFile::fake()->create('panduan.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $this->actingAs($admin)->post('/api/admin/media', [
            'file' => UploadedFile::fake()->create('doc.docx', 100, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
        ], ['Accept' => 'application/json'])->assertStatus(422);

        $this->actingAs($admin)->post('/api/admin/media', [
            'file' => UploadedFile::fake()->image('besar.jpg')->size(6000),
        ], ['Accept' => 'application/json'])->assertStatus(422);
    }

    public function test_list_filters_images_and_searches_by_name(): void
    {
        $admin = User::factory()->admin()->create();
        $image = Media::factory()->create(['original_name' => 'foto-kelas.jpg']);
        Media::factory()->pdf()->create(['original_name' => 'panduan.pdf']);

        $this->actingAs($admin)->getJson('/api/admin/media?type=image')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $image->id);

        $this->actingAs($admin)->getJson('/api/admin/media?search=panduan')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_update_alt_text(): void
    {
        $admin = User::factory()->admin()->create();
        $media = Media::factory()->create();

        $this->actingAs($admin)->patchJson("/api/admin/media/{$media->id}", [
            'alt_text' => 'Suasana kelas offline',
        ])->assertOk();

        $this->assertSame('Suasana kelas offline', $media->fresh()->alt_text);
    }

    public function test_show_lists_sections_using_the_file(): void
    {
        $admin = User::factory()->admin()->create();
        $media = Media::factory()->create(['path' => 'media/dipakai.jpg']);
        ContentSection::factory()->create([
            'heading' => 'Belajar daring dan luring.',
            'body' => '<img src="/storage/media/dipakai.jpg" alt="">',
        ]);
        ContentSection::factory()->forProgram()->create([
            'heading' => null,
            'body' => '<p>Unduh: <a href="/storage/media/dipakai.jpg">foto</a></p>',
        ]);

        $this->actingAs($admin)->getJson("/api/admin/media/{$media->id}")
            ->assertOk()
            ->assertJsonPath('media.id', $media->id)
            ->assertJsonPath('media.used_in', ['Belajar daring dan luring.', 'Program']);
    }

    public function test_show_returns_empty_used_in_for_unreferenced_file(): void
    {
        $admin = User::factory()->admin()->create();
        $media = Media::factory()->create();

        $this->actingAs($admin)->getJson("/api/admin/media/{$media->id}")
            ->assertOk()
            ->assertJsonPath('media.used_in', []);
    }

    public function test_delete_blocked_while_referenced_by_a_section(): void
    {
        $admin = User::factory()->admin()->create();
        $media = Media::factory()->create(['path' => 'media/dipakai.jpg']);
        Storage::disk('public')->put($media->path, 'x');
        ContentSection::factory()->create([
            'heading' => 'Belajar daring dan luring.',
            'body' => '<p>Lihat:</p><img src="/storage/media/dipakai.jpg" alt="">',
        ]);

        $this->actingAs($admin)->deleteJson("/api/admin/media/{$media->id}")
            ->assertStatus(422)
            ->assertJsonPath('message', fn (string $m) => str_contains($m, 'Belajar daring dan luring.'));

        $this->assertModelExists($media);
        Storage::disk('public')->assertExists($media->path);
    }

    public function test_delete_removes_unreferenced_file_and_row(): void
    {
        $admin = User::factory()->admin()->create();
        $media = Media::factory()->create();
        Storage::disk('public')->put($media->path, 'x');

        $this->actingAs($admin)->deleteJson("/api/admin/media/{$media->id}")->assertOk();

        $this->assertDatabaseMissing('media', ['id' => $media->id]);
        Storage::disk('public')->assertMissing($media->path);
    }

    public function test_delete_succeeds_even_when_file_already_missing(): void
    {
        $admin = User::factory()->admin()->create();
        $media = Media::factory()->create();

        $this->actingAs($admin)->deleteJson("/api/admin/media/{$media->id}")
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertDatabaseMissing('media', ['id' => $media->id]);
    }
}
