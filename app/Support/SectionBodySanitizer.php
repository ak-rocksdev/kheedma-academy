<?php

namespace App\Support;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Sanitizes content-section bodies to the exact schema the admin Tiptap
 * editor produces. The editor constrains input client-side, but the API
 * must not trust the client. Image URLs stay relative so stored content
 * never bakes in APP_URL.
 */
class SectionBodySanitizer
{
    private HtmlSanitizer $sanitizer;

    public function __construct()
    {
        $config = (new HtmlSanitizerConfig)
            ->allowElement('p')
            ->allowElement('strong')
            ->allowElement('em')
            ->allowElement('ul')
            ->allowElement('ol')
            ->allowElement('li')
            ->allowElement('a', ['href'])
            ->allowElement('img', ['src', 'alt'])
            ->allowLinkSchemes(['http', 'https'])
            ->allowRelativeLinks()
            ->allowMediaSchemes([])
            ->allowRelativeMedias()
            ->withMaxInputLength(50000);

        $this->sanitizer = new HtmlSanitizer($config);
    }

    public function sanitize(string $html): string
    {
        return $this->sanitizer->sanitize($html);
    }
}
