<?php

use App\Support\HtmlSanitizer;

test('strips script tags and event handler attributes', function () {
    expect((new HtmlSanitizer)->sanitize('<p onclick="x()">a</p><script>alert(1)</script>'))->toBe('<p>a</p>');
});

test('drops javascript urls', function () {
    expect((new HtmlSanitizer)->sanitize('<a href="javascript:alert(1)">x</a>'))->not->toContain('javascript:');
});

test('drops iframe, style and onerror', function () {
    $html = (new HtmlSanitizer)->sanitize('<iframe src="https://x"></iframe><style>p{}</style><img src="x" onerror="y()">');

    expect($html)->not->toContain('iframe')->not->toContain('style')->not->toContain('onerror');
});

test('keeps allowed formatting', function () {
    $html = (new HtmlSanitizer)->sanitize('<h2>T</h2><p><strong>a</strong> <em>b</em> <a href="https://example.com">l</a></p><ul><li>x</li></ul><blockquote>q</blockquote><hr>');

    expect($html)->toContain('<h2>T</h2>', '<strong>a</strong>', '<em>b</em>', 'href="https://example.com"', '<ul><li>x</li></ul>', '<blockquote>q</blockquote>', '<hr />');
});

test('repairs unclosed tags', function () {
    expect((new HtmlSanitizer)->sanitize('<p><strong>a'))->toBe('<p><strong>a</strong></p>');
});

test('keeps relative links to other pages', function () {
    expect((new HtmlSanitizer)->sanitize('<p><a href="/content/cookies">c</a></p>'))->toContain('href="/content/cookies"');
});
