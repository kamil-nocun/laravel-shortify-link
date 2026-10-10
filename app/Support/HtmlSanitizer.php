<?php

namespace App\Support;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer as SymfonyHtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

class HtmlSanitizer
{
    private readonly SymfonyHtmlSanitizer $sanitizer;

    public function __construct()
    {
        $config = (new HtmlSanitizerConfig)
            ->allowSafeElements()
            ->allowLinkSchemes(['http', 'https', 'mailto'])
            ->allowRelativeLinks()
            ->allowMediaSchemes(['https'])
            ->allowRelativeMedias(false)
            ->allowAttribute('style', ['p', 'h1', 'h2', 'h3', 'h4', 'th', 'td'])
            ->withAttributeSanitizer(new TextAlignStyleSanitizer)
            ->allowElement('hr')
            ->allowElement('u')
            ->allowElement('s')
            ->allowElement('code')
            ->allowElement('pre');

        $this->sanitizer = new SymfonyHtmlSanitizer($config);
    }

    /**
     * Strip everything but the formatting the page editor produces.
     */
    public function sanitize(string $html): string
    {
        return $this->sanitizer->sanitize($html);
    }
}
