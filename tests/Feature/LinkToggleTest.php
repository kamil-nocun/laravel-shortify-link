<?php

use App\Models\Link;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('owner toggles a link off and on again', function () {
    $link = Link::factory()->for($this->user)->create(['is_active' => true]);

    $this->actingAs($this->user)->patch(route('links.toggle', $link))->assertRedirect();
    expect($link->fresh()->is_active)->toBeFalse();

    $this->actingAs($this->user)->patch(route('links.toggle', $link));
    expect($link->fresh()->is_active)->toBeTrue();
});

test('another user cannot toggle a link', function () {
    $link = Link::factory()->create(['is_active' => true]);

    $this->actingAs($this->user)->patch(route('links.toggle', $link));

    expect($link->fresh()->is_active)->toBeTrue();
});

test('a link disabled by an admin cannot be toggled', function () {
    $link = Link::factory()->for($this->user)->disabledByAdmin()->create();
    $before = $link->fresh();

    $this->actingAs($this->user)->patch(route('links.toggle', $link));

    $after = $link->fresh();
    expect($after->is_active)->toBe($before->is_active)
        ->and($after->disabled_by_admin_at->equalTo($before->disabled_by_admin_at))->toBeTrue();
});

test('guests are redirected to login', function () {
    $this->patch(route('links.toggle', Link::factory()->create()))->assertRedirect(route('login'));
});
