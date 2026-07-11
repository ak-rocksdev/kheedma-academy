<?php

namespace Tests\Feature;

use App\Models\Program;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProgramThumbnailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        Storage::fake('public');
    }

    public function test_upload_replaces_and_deletes_old_file(): void
    {
        $admin = User::factory()->admin()->create();
        $program = Program::factory()->create();

        $first = $this->actingAs($admin)
            ->post("/api/admin/programs/{$program->id}/thumbnail", [
                'thumbnail' => UploadedFile::fake()->image('a.jpg', 640, 360),
            ])
            ->assertOk()
            ->json('program.thumbnail_url');
        $firstPath = $program->fresh()->thumbnail_path;
        Storage::disk('public')->assertExists($firstPath);
        $this->assertNotNull($first);

        $this->actingAs($admin)
            ->post("/api/admin/programs/{$program->id}/thumbnail", [
                'thumbnail' => UploadedFile::fake()->image('b.png', 640, 360),
            ])
            ->assertOk();

        Storage::disk('public')->assertMissing($firstPath);
        Storage::disk('public')->assertExists($program->fresh()->thumbnail_path);
    }

    public function test_wrong_type_and_oversize_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $program = Program::factory()->create();

        $this->actingAs($admin)
            ->post("/api/admin/programs/{$program->id}/thumbnail", [
                'thumbnail' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
            ], ['Accept' => 'application/json'])
            ->assertStatus(422);

        $this->actingAs($admin)
            ->post("/api/admin/programs/{$program->id}/thumbnail", [
                'thumbnail' => UploadedFile::fake()->image('big.jpg')->size(3000),
            ], ['Accept' => 'application/json'])
            ->assertStatus(422);
    }

    public function test_delete_clears_path_and_file(): void
    {
        $admin = User::factory()->admin()->create();
        $program = Program::factory()->create();
        $this->actingAs($admin)->post("/api/admin/programs/{$program->id}/thumbnail", [
            'thumbnail' => UploadedFile::fake()->image('a.jpg'),
        ])->assertOk();
        $path = $program->fresh()->thumbnail_path;

        $this->actingAs($admin)->deleteJson("/api/admin/programs/{$program->id}/thumbnail")->assertOk();

        $this->assertNull($program->fresh()->thumbnail_path);
        Storage::disk('public')->assertMissing($path);
    }
}
