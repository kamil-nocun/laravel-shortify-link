<?php

namespace App\Rules;

use App\Support\HtmlSanitizer;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class NonBlankHtml implements ValidationRule
{
    /**
     * Fail when the HTML, once sanitized, has neither visible text nor an image.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $visible = strip_tags(app(HtmlSanitizer::class)->sanitize((string) $value), '<img>');
        $visible = preg_replace('/[\s\x{00A0}]+/u', '', html_entity_decode($visible, ENT_QUOTES | ENT_HTML5)) ?? '';

        if ($visible === '') {
            $fail(__('The :attribute field must have some content.'));
        }
    }
}
