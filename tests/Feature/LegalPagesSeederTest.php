<?php

use App\Actions\SavePageVersion;
use App\Enums\PageStatus;
use App\Models\Page;
use Database\Seeders\LegalPagesSeeder;

test('seeds published privacy, cookies and terms pages', function () {
    $this->seed(LegalPagesSeeder::class);

    foreach (['privacy-policy' => 'Privacy policy', 'cookies-policy' => 'Cookies policy', 'terms' => 'Terms of service'] as $slug => $title) {
        $page = Page::firstWhere('slug', $slug);

        expect($page->status)->toBe(PageStatus::Published)
            ->and($page->currentVersion->title)->toBe($title)
            ->and($page->currentVersion->body)->toContain('Lorem ipsum');

        $this->get(route('content.show', $slug))->assertOk();
    }
});

test('seeding twice neither duplicates pages nor overwrites edited content', function () {
    $this->seed(LegalPagesSeeder::class);
    $page = Page::firstWhere('slug', 'privacy-policy');
    app(SavePageVersion::class)($page, 'Edited', '<p>Custom</p>', null);

    $this->seed(LegalPagesSeeder::class);

    expect(Page::count())->toBe(3)
        ->and($page->fresh()->versions()->count())->toBe(2)
        ->and($page->fresh()->currentVersion->title)->toBe('Edited');
});

test('seeded documents are as long and structured as real legal texts', function (string $slug, int $sections, int $characters) {
    $this->seed(LegalPagesSeeder::class);
    $body = Page::firstWhere('slug', $slug)->currentVersion->body;

    expect(substr_count($body, '<h2>'))->toBeGreaterThanOrEqual($sections)
        ->and(mb_strlen(strip_tags($body)))->toBeGreaterThanOrEqual($characters);
})->with([
    'privacy policy' => ['privacy-policy', 10, 6000],
    'cookies policy' => ['cookies-policy', 6, 3000],
    'terms' => ['terms', 12, 7000],
]);

test('cookies policy lists cookies in a table and policies use lists', function () {
    $this->seed(LegalPagesSeeder::class);

    expect(Page::firstWhere('slug', 'cookies-policy')->currentVersion->body)->toContain('<table>', '<th>')
        ->and(Page::firstWhere('slug', 'privacy-policy')->currentVersion->body)->toContain('<ul>', '<li>');
});

test('seeded documents are marked as legal documents', function () {
    $this->seed(LegalPagesSeeder::class);

    expect(Page::where('is_legal_document', true)->pluck('slug')->all())->toEqualCanonicalizing(['privacy-policy', 'cookies-policy', 'terms']);
});
