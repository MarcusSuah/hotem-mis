<?php

namespace App\Services\Website;

use Illuminate\Support\Str;

/**
 * Turns staff-authored Markdown into HTML that is safe to render publicly.
 * Raw HTML in the source is stripped and unsafe link schemes are refused,
 * so CMS content can never inject scripts into the website.
 */
class ContentRenderer
{
    public function toHtml(?string $markdown): string
    {
        if ($markdown === null || trim($markdown) === '') {
            return '';
        }

        return Str::markdown($markdown, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 20,
        ]);
    }

    /**
     * Plain-text excerpt for listings and meta descriptions.
     */
    public function excerpt(?string $markdown, int $limit = 160): string
    {
        $text = strip_tags($this->toHtml($markdown));

        return Str::limit(trim((string) preg_replace('/\s+/', ' ', html_entity_decode($text))), $limit);
    }
}
