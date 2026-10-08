<?php

use App\Models\Link;

test('active scope returns only active, non-expired links', function () {
    $this->freezeTime();

    $active = Link::factory()->create();
    Link::factory()->inactive()->create();
    Link::factory()->expired()->create();
    $expiresLater = Link::factory()->create(['expires_at' => now()->addDay()]);
    Link::factory()->create(['expires_at' => now()]);

    expect(Link::active()->pluck('id')->all())
        ->toEqualCanonicalizing([$active->id, $expiresLater->id]);
});
