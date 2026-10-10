<?php

use App\Models\Link;
use App\Models\User;
use App\Support\PermissionCatalog;

test('new user gets the default role and is a client', function () {
    $user = User::create(['name' => 'New', 'email' => 'new@example.com', 'password' => 'password']);

    expect($user->refresh()->role->name)->toBe(PermissionCatalog::CLIENT_ROLE)
        ->and($user->isClient())->toBeTrue()
        ->and($user->isAdmin())->toBeFalse()
        ->and($user->isSuperAdmin())->toBeFalse();
});

test('admin state is an administrator but not the super admin', function () {
    $admin = User::factory()->admin()->create();

    expect($admin->isAdmin())->toBeTrue()
        ->and($admin->isSuperAdmin())->toBeFalse()
        ->and($admin->isClient())->toBeFalse();
});

test('super admin state is the super admin and an administrator', function () {
    $super = User::factory()->superAdmin()->create();

    expect($super->isSuperAdmin())->toBeTrue()
        ->and($super->isAdmin())->toBeTrue()
        ->and($super->isClient())->toBeFalse();
});

test('clients and administrators scopes split users by role kind', function () {
    $client = User::factory()->create();
    $admin = User::factory()->admin()->create();
    User::factory()->superAdmin()->create();

    expect(User::clients()->pluck('id')->all())->toBe([$client->id])
        ->and(User::administrators()->pluck('id')->all())->toBe([$admin->id]);
});

test('factory states set the block and disabled columns', function () {
    expect(User::factory()->blocked()->create()->isBlocked())->toBeTrue()
        ->and(User::factory()->create()->isBlocked())->toBeFalse()
        ->and(Link::factory()->disabledByAdmin()->create()->isDisabledByAdmin())->toBeTrue()
        ->and(Link::factory()->create()->isDisabledByAdmin())->toBeFalse();
});
