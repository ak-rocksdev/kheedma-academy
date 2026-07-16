<?php

namespace Tests\Feature;

use App\Support\SectionBodySanitizer;
use Tests\TestCase;

class SectionBodySanitizerTest extends TestCase
{
    private SectionBodySanitizer $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sanitizer = new SectionBodySanitizer;
    }

    public function test_allowed_markup_is_kept(): void
    {
        $html = '<p>Halo <strong>kuat</strong> dan <em>miring</em></p><ul><li>Satu</li></ul><ol><li>Dua</li></ol>';

        $this->assertSame($html, $this->sanitizer->sanitize($html));
    }

    public function test_scripts_styles_and_event_handlers_are_stripped(): void
    {
        $dirty = '<p onclick="x()" style="color:red">Aman</p><script>alert(1)</script><h1>Judul</h1>';
        $clean = $this->sanitizer->sanitize($dirty);

        $this->assertStringNotContainsString('<script', $clean);
        $this->assertStringNotContainsString('onclick', $clean);
        $this->assertStringNotContainsString('style=', $clean);
        $this->assertStringNotContainsString('<h1', $clean);
        $this->assertStringContainsString('Aman', $clean);
    }

    public function test_relative_img_src_kept_absolute_dropped(): void
    {
        $clean = $this->sanitizer->sanitize('<img src="/storage/media/a.jpg" alt="Foto kelas">');
        $this->assertStringContainsString('src="/storage/media/a.jpg"', $clean);
        $this->assertStringContainsString('alt="Foto kelas"', $clean);

        $external = $this->sanitizer->sanitize('<img src="https://evil.example/a.jpg">');
        $this->assertStringNotContainsString('evil.example', $external);
    }

    public function test_links_allow_https_and_relative_but_not_javascript(): void
    {
        $this->assertStringContainsString(
            'href="https://kheedma.id"',
            $this->sanitizer->sanitize('<a href="https://kheedma.id">situs</a>')
        );
        $this->assertStringContainsString(
            'href="/storage/media/panduan.pdf"',
            $this->sanitizer->sanitize('<a href="/storage/media/panduan.pdf">panduan</a>')
        );
        $this->assertStringNotContainsString(
            'javascript',
            $this->sanitizer->sanitize('<a href="javascript:alert(1)">x</a>')
        );
    }
}
