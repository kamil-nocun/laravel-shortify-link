<?php

use App\Models\LinkClick;

test('clicks without an entry become Code and recorded entries stay', function () {
    LinkClick::factory()->create(['entry' => null]);
    LinkClick::factory()->create(['entry' => 'Alias']);
    LinkClick::factory()->create(['entry' => 'Code']);

    (require database_path('migrations/2026_10_10_170000_backfill_entry_on_link_statistics_table.php'))->up();

    expect(LinkClick::whereNull('entry')->count())->toBe(0)
        ->and(LinkClick::where('entry', 'Code')->count())->toBe(2)
        ->and(LinkClick::where('entry', 'Alias')->count())->toBe(1);
});
