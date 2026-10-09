<?php

use App\Models\Link;
use App\Models\User;

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

test('active scope requires every condition', function (bool $isActive, bool $disabledByAdmin, bool $expired, bool $ownerBlocked, bool $expected) {
    $link = Link::factory()
        ->for($ownerBlocked ? User::factory()->blocked() : User::factory())
        ->create([
            'is_active' => $isActive,
            'expires_at' => $expired ? now()->subDay() : null,
            'disabled_by_admin_at' => $disabledByAdmin ? now() : null,
        ]);

    expect(Link::active()->whereKey($link->id)->exists())->toBe($expected);
})->with([
    'all fine' => [true, false, false, false, true],
    'inactive' => [false, false, false, false, false],
    'disabled by admin' => [true, true, false, false, false],
    'expired' => [true, false, true, false, false],
    'owner blocked' => [true, false, false, true, false],
]);
