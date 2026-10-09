<?php

use App\Enums\UserRole;
use App\Models\Link;
use App\Models\User;

test('new user has the user role', function () {
    expect(User::factory()->create()->refresh()->role)->toBe(UserRole::User);
});

test('only admin roles are admins', function (UserRole $role, bool $expected) {
    expect($role->isAdmin())->toBe($expected);
})->with([
    [UserRole::User, false],
    [UserRole::Admin, true],
    [UserRole::SuperAdmin, true],
]);

test('factory states set the block and role columns', function () {
    expect(User::factory()->blocked()->create()->isBlocked())->toBeTrue()
        ->and(User::factory()->create()->isBlocked())->toBeFalse()
        ->and(User::factory()->admin()->create()->isAdmin())->toBeTrue()
        ->and(User::factory()->superAdmin()->create()->isSuperAdmin())->toBeTrue()
        ->and(Link::factory()->disabledByAdmin()->create()->isDisabledByAdmin())->toBeTrue()
        ->and(Link::factory()->create()->isDisabledByAdmin())->toBeFalse();
});
