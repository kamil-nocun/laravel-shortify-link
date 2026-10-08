<?php

use App\Models\Link;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

test('a link belongs to a user and is listed in user links', function () {
    $user = User::factory()->create();
    $link = Link::factory()->for($user)->create();

    expect($link->user->is($user))->toBeTrue()
        ->and($user->links->pluck('id')->all())->toBe([$link->id])
        ->and($link->is_active)->toBeTrue()
        ->and($link->expires_at)->toBeNull();
});

test('short_url joins the app url and the code and is not stored', function () {
    config(['app.url' => 'http://example.test/']);
    $link = Link::factory()->create(['code' => 'aB3xY9']);

    expect($link->short_url)->toBe('http://example.test/aB3xY9')
        ->and($link->toArray())->toHaveKey('short_url')
        ->and(Schema::hasColumn('links', 'short_url'))->toBeFalse();
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
