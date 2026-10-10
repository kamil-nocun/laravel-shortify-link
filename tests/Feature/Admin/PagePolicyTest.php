<?php

use App\Enums\Permission;
use App\Models\Page;
use App\Models\PermissionRecord;
use App\Models\User;

test('super admin has pages.manage', function () {
    expect(User::factory()->superAdmin()->create()->hasPermission(Permission::PagesManage))->toBeTrue();
});

test('administrator role does not have pages.manage', function () {
    expect(User::factory()->admin()->create()->hasPermission('pages.manage'))->toBeFalse();
});

test('permission record exists after migration', function () {
    expect(PermissionRecord::where('key', 'pages.manage')->value('group'))->toBe('Pages');
});

test('page policy allows only holders of pages.manage', function (string $role, bool $allowed) {
    $user = User::factory()->{$role}()->create();
    $page = Page::factory()->create();

    expect($user->can('viewAny', Page::class))->toBe($allowed)
        ->and($user->can('create', Page::class))->toBe($allowed)
        ->and($user->can('update', $page))->toBe($allowed);
})->with([['superAdmin', true], ['admin', false]]);
