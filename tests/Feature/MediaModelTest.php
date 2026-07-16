<?php

namespace Tests\Feature;

use App\Models\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MediaModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_url_is_relative(): void
    {
        $media = Media::factory()->create(['path' => 'media/foto.jpg']);

        $this->assertSame('/storage/media/foto.jpg', $media->url());
    }

    public function test_is_image_by_mime_type(): void
    {
        $this->assertTrue(Media::factory()->create()->isImage());
        $this->assertFalse(Media::factory()->pdf()->create()->isImage());
    }
}
