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

test('keeps everything the full editor toolbar produces', function () {
    $html = '<h1>a</h1><h3>b</h3><h4>c</h4><p><u>u</u> <s>s</s> <code>c</code></p><ol><li>x</li></ol><pre><code>code</code></pre><hr />';

    expect((new HtmlSanitizer)->sanitize($html))->toBe($html);
});

test('keeps tables with spans', function () {
    $html = (new HtmlSanitizer)->sanitize('<table><tbody><tr><th colspan="2" rowspan="1">h</th></tr><tr><td>a</td><td>b</td></tr></tbody></table>');

    expect($html)->toContain('<table>', '<th colspan="2" rowspan="1">h</th>', '<td>a</td>');
});

test('keeps only text-align from style attributes', function () {
    $html = (new HtmlSanitizer)->sanitize('<p style="text-align: center; position: fixed; background: url(https://x/y)">a</p><h2 style="color: red">b</h2><p style="text-align: evil">c</p>');

    expect($html)->toContain('<p style="text-align: center">a</p>')
        ->and($html)->not->toContain('position')->not->toContain('url(')->not->toContain('color')->not->toContain('evil');
});

test('keeps https images and drops data, http and javascript sources', function () {
    $sanitizer = new HtmlSanitizer;

    expect($sanitizer->sanitize('<img src="https://example.com/a.png" alt="a" title="t">'))->toContain('src="https://example.com/a.png"', 'alt="a"')
        ->and($sanitizer->sanitize('<img src="data:image/png;base64,AAAA">'))->not->toContain('data:')
        ->and($sanitizer->sanitize('<img src="http://example.com/a.png">'))->not->toContain('http://')
        ->and($sanitizer->sanitize('<img src="javascript:alert(1)" onerror="x()">'))->not->toContain('javascript')->not->toContain('onerror');
});
