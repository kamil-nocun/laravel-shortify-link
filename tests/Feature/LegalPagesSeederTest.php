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
