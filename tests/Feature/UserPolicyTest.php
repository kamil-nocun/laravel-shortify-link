<?php

use App\Models\Link;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

dataset('moderation', [
    'admin -> user' => ['admin', 'user', true],
    'admin -> admin' => ['admin', 'admin', false],
    'admin -> super admin' => ['admin', 'superAdmin', false],
    'super admin -> user' => ['superAdmin', 'user', true],
    'super admin -> admin' => ['superAdmin', 'admin', true],
    'user -> user' => ['user', 'user', false],
    'user -> admin' => ['user', 'admin', false],
]);

function makeUserWithRole(string $role): User
{
    return $role === 'user' ? User::factory()->create() : User::factory()->{$role}()->create();
}

test('block and delete follow the role hierarchy', function (string $actorRole, string $targetRole, bool $expected) {
    $actor = makeUserWithRole($actorRole);
    $target = makeUserWithRole($targetRole);
    $target->assignedAdmins()->attach($actor);

    expect(Gate::forUser($actor)->allows('block', $target))->toBe($expected)
        ->and(Gate::forUser($actor)->allows('update', $target))->toBe($expected)
        ->and(Gate::forUser($actor)->allows('delete', $target))->toBe($expected);
})->with('moderation');

test('nobody can block, delete or change the role of themselves', function (string $role) {
    $user = makeUserWithRole($role);

    expect(Gate::forUser($user)->allows('block', $user))->toBeFalse()
        ->and(Gate::forUser($user)->allows('delete', $user))->toBeFalse()
        ->and(Gate::forUser($user)->allows('changeRole', $user))->toBeFalse();
})->with(['user', 'admin', 'superAdmin']);

test('only the super admin changes roles of others', function (string $actorRole, string $targetRole, bool $expected) {
    $actor = makeUserWithRole($actorRole);
    $target = makeUserWithRole($targetRole);

    expect(Gate::forUser($actor)->allows('changeRole', $target))->toBe($expected);
})->with([
    ['superAdmin', 'user', true],
    ['superAdmin', 'admin', true],
    ['superAdmin', 'superAdmin', false],
    ['admin', 'user', false],
    ['user', 'user', false],
]);

test('admin can view but not update or delete a foreign link', function () {
    $admin = User::factory()->admin()->create();
    $link = Link::factory()->create();

    expect(Gate::forUser($admin)->allows('view', $link))->toBeTrue()
        ->and(Gate::forUser($admin)->allows('update', $link))->toBeFalse()
        ->and(Gate::forUser($admin)->allows('delete', $link))->toBeFalse();
});

test('regular user cannot view a foreign link', function () {
    expect(Gate::forUser(User::factory()->create())->allows('view', Link::factory()->create()))->toBeFalse();
});

test('toggle requires ownership and no admin disable', function () {
    $owner = User::factory()->create();

    expect(Gate::forUser($owner)->allows('toggle', Link::factory()->for($owner)->create()))->toBeTrue()
        ->and(Gate::forUser($owner)->allows('toggle', Link::factory()->for($owner)->disabledByAdmin()->create()))->toBeFalse()
        ->and(Gate::forUser(User::factory()->create())->allows('toggle', Link::factory()->for($owner)->create()))->toBeFalse();
});
