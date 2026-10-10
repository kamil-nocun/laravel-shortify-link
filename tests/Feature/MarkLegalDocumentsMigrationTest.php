<?php

use App\Models\Page;

test('the existing legal pages are flagged and other pages are not', function () {
    foreach (['terms', 'privacy-policy', 'cookies-policy', 'about'] as $slug) {
        Page::factory()->create(['slug' => $slug, 'is_legal_document' => false]);
    }

    (require database_path('migrations/2026_10_11_000500_flag_existing_legal_pages.php'))->up();

    expect(Page::where('is_legal_document', true)->pluck('slug')->all())->toEqualCanonicalizing(['terms', 'privacy-policy', 'cookies-policy']);
});
