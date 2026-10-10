<?php

use App\Models\User;
use App\Notifications\AccountCreatedNotification;
use App\Support\PermissionCatalog;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

test('super admin lists only administrators', function () {
    $super = User::factory()->superAdmin()->create();
    User::factory()->admin()->create(['name' => 'Ada']);
    User::factory()->create();

    $this->actingAs($super)->get(route('admin.administrators.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/administrators/Index')
            ->has('administrators.data', 1)
            ->where('administrators.data.0.name', 'Ada')
            ->where('administrators.data.0.can', ['block' => true, 'update' => true]));
});

test('super admin creates administrator and e-mails password link', function () {
    Notification::fake();
    $super = User::factory()->superAdmin()->create();

    $this->actingAs($super)->post(route('admin.administrators.store'), ['name' => 'Ada', 'email' => 'ada@example.com'])
        ->assertRedirect(route('admin.administrators.index'));

    $created = User::where('email', 'ada@example.com')->firstOrFail();
    expect($created->isAdmin())->toBeTrue()
        ->and($created->isSuperAdmin())->toBeFalse();
    Notification::assertSentTo($created, AccountCreatedNotification::class);
});

test('super admin can block an administrator', function () {
    $super = User::factory()->superAdmin()->create();
    $admin = User::factory()->admin()->create();

    $this->actingAs($super)->patch(route('admin.users.block', $admin))->assertRedirect();

    expect($admin->fresh()->blocked_at)->not->toBeNull();
});

test('admin and user cannot manage administrators', function (User $actor) {
    $this->actingAs($actor)->get(route('admin.administrators.index'))->assertForbidden();
    $this->actingAs($actor)->get(route('admin.administrators.create'))->assertForbidden();
    $this->actingAs($actor)->post(route('admin.administrators.store'), ['name' => 'X', 'email' => 'x@example.com'])->assertForbidden();
    expect(User::where('email', 'x@example.com')->exists())->toBeFalse();
})->with([
    'admin' => fn () => User::factory()->admin()->create(),
    'user' => fn () => User::factory()->create(),
]);

test('administrator role must grant admin access and not be super', function (string $roleId) {
    $super = User::factory()->superAdmin()->create();

    $this->actingAs($super)->post(route('admin.administrators.store'), ['name' => 'Ada', 'email' => 'ada@example.com', 'role_id' => $roleId])
        ->assertSessionHasErrors('role_id');
    expect(User::where('email', 'ada@example.com')->exists())->toBeFalse();
})->with([
    'client role' => fn () => roleId(PermissionCatalog::CLIENT_ROLE),
    'super role' => fn () => roleId(PermissionCatalog::SUPER_ADMIN_ROLE),
]);

test('administrator create page offers administrator roles', function () {
    $super = User::factory()->superAdmin()->create();

    $this->actingAs($super)->get(route('admin.administrators.create'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('roles', fn ($roles) => collect($roles)->pluck('name')->all() === [PermissionCatalog::ADMINISTRATOR_ROLE])
            ->where('defaultRoleId', roleId(PermissionCatalog::ADMINISTRATOR_ROLE)));
});

test('administrators index lists the role name', function () {
    $super = User::factory()->superAdmin()->create();
    User::factory()->admin()->create();

    $this->actingAs($super)->get(route('admin.administrators.index'))
        ->assertInertia(fn (Assert $page) => $page->where('administrators.data.0.role', PermissionCatalog::ADMINISTRATOR_ROLE));
});
