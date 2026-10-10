<?php

use App\Models\LinkClick;

test('clicks without a source become Link and recorded sources stay', function () {
    LinkClick::factory()->create(['source' => null]);
    LinkClick::factory()->create(['source' => 'QR']);
    LinkClick::factory()->create(['source' => 'Link']);

    (require database_path('migrations/2026_10_10_190100_backfill_source_on_link_statistics_table.php'))->up();

    expect(LinkClick::whereNull('source')->count())->toBe(0)
        ->and(LinkClick::where('source', 'Link')->count())->toBe(2)
        ->and(LinkClick::where('source', 'QR')->count())->toBe(1);
});
