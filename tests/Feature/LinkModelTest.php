<?php

use App\Models\Link;
use App\Models\User;
use Illuminate\Database\QueryException;

test('a link belongs to a user and is listed in user links', function () {
    $user = User::factory()->create();
    $link = Link::factory()->for($user)->create();

    expect($link->user->is($user))->toBeTrue()
        ->and($user->links->pluck('id')->all())->toBe([$link->id])
        ->and($link->is_active)->toBeTrue()
        ->and($link->expires_at)->toBeNull();
});

test('factory states set inactive and expired links', function () {
    expect(Link::factory()->inactive()->make()->is_active)->toBeFalse()
        ->and(Link::factory()->expired()->make()->expires_at->isPast())->toBeTrue();
});

test('code is case sensitive unique', function () {
    Link::factory()->create(['code' => 'abc123']);
    Link::factory()->create(['code' => 'ABC123']);

    expect(fn () => Link::factory()->create(['code' => 'abc123']))->toThrow(QueryException::class);
});
