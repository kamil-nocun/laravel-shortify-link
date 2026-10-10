<?php

use App\Models\Domain;
use App\Models\Link;
use App\Models\Package;
use App\Models\PackageAssignment;
use App\Models\User;
use App\Support\LinkCodeGenerator;
use App\Support\ReservedAliases;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;

function userWithAlias(): User
{
    $user = User::factory()->create();
    PackageAssignment::factory()->for($user)->for(Package::factory()->withCustomAlias()->create())->create();

    return $user;
}

function storeLink(User $user, array $extra = [])
{
    return test()->actingAs($user)->post(route('links.store'), ['original_url' => 'https://example.com/x', 'is_active' => true, ...$extra]);
}

test('a valid alias becomes the code and redirects', function (string $alias) {
    storeLink(userWithAlias(), ['alias' => $alias])->assertSessionHasNoErrors();

    $link = Link::sole();
    expect($link->code)->toBe($alias);
    $this->get('/'.$alias)->assertRedirect('https://example.com/x');
})->with(['promocja-lato', 'a_b-1']);

test('invalid aliases are rejected', function (string $alias) {
    $user = userWithAlias();
    Link::factory()->create(['code' => 'taken1', 'domain_id' => null]);

    storeLink($user, ['alias' => $alias])->assertSessionHasErrors('alias');
    expect(Link::count())->toBe(1);
})->with([
    'too short' => 'ab',
    'too long' => str_repeat('a', 51),
    'space' => 'ab c',
    'unicode' => 'zażółć',
    'reserved' => 'admin',
    'reserved case' => 'Admin',
    'reserved route' => 'dashboard',
    'duplicate' => 'taken1',
]);

test('the same alias is allowed on different domains and case differs', function () {
    $user = userWithAlias();
    $domain = Domain::factory()->verified()->create(['user_id' => $user->id]);

    storeLink($user, ['alias' => 'promo'])->assertSessionHasNoErrors();
    storeLink($user, ['alias' => 'promo', 'domain_id' => $domain->id])->assertSessionHasNoErrors();
    storeLink($user, ['alias' => 'Promo'])->assertSessionHasNoErrors();

    expect(Link::count())->toBe(3);
});

test('a user without permission cannot send an alias', function () {
    storeLink(User::factory()->create(), ['alias' => 'promo'])->assertSessionHasErrors('alias');

    expect(Link::count())->toBe(0);
});

test('without an alias a code is generated', function () {
    storeLink(User::factory()->create())->assertSessionHasNoErrors();

    expect(strlen(Link::sole()->code))->toBeGreaterThanOrEqual(LinkCodeGenerator::MIN_LENGTH);
});

test('a race-condition collision becomes a validation error on alias', function () {
    $user = userWithAlias();
    Link::factory()->create(['code' => 'racing', 'domain_id' => null]);

    expect(fn () => app(LinkCodeGenerator::class)->createFor($user, ['original_url' => 'https://example.com', 'alias' => 'racing']))
        ->toThrow(ValidationException::class);
});

test('an admin creating a link checks the client permission', function () {
    $admin = User::factory()->superAdmin()->create();
    $plain = User::factory()->create();
    $allowed = userWithAlias();
    $payload = ['original_url' => 'https://example.com', 'is_active' => true, 'alias' => 'fromadmin'];

    $this->actingAs($admin)->post(route('admin.users.links.store', $plain), $payload)->assertSessionHasErrors('alias');
    $this->actingAs($admin)->post(route('admin.users.links.store', $allowed), $payload)->assertSessionHasNoErrors();

    expect($allowed->links()->sole()->code)->toBe('fromadmin');
});

test('update never changes the code', function () {
    $user = userWithAlias();
    $link = Link::factory()->for($user)->create(['code' => 'keepme']);

    $this->actingAs($user)->patch(route('links.update', $link), ['original_url' => 'https://example.com/n', 'is_active' => true, 'alias' => 'other'])
        ->assertSessionHasNoErrors();

    expect($link->fresh()->code)->toBe('keepme');
});

test('an alias keeps redirecting after the permission is lost', function () {
    $user = userWithAlias();
    storeLink($user, ['alias' => 'lasting']);
    PackageAssignment::where('user_id', $user->id)->update(['allows_custom_alias' => false]);

    $this->get('/lasting')->assertRedirect('https://example.com/x');
});

test('every static route prefix is reserved', function () {
    $missing = collect(Route::getRoutes()->getRoutes())
        ->map(fn ($route) => explode('/', ltrim($route->uri(), '/'))[0])
        ->filter(fn (string $segment) => $segment !== '' && ! str_contains($segment, '{'))
        ->unique()
        ->reject(fn (string $segment) => ReservedAliases::isReserved($segment))
        ->values()->all();

    expect($missing)->toBe([]);
});

test('the create pages expose whether the alias field is allowed', function () {
    $this->actingAs(userWithAlias())->get(route('links.create'))
        ->assertInertia(fn ($page) => $page->where('canSetAlias', true));
    $this->actingAs(User::factory()->create())->get(route('links.create'))
        ->assertInertia(fn ($page) => $page->where('canSetAlias', false));

    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin)->get(route('admin.users.links.create', userWithAlias()))
        ->assertInertia(fn ($page) => $page->where('canSetAlias', true));
    $this->actingAs($admin)->get(route('admin.users.links.create', User::factory()->create()))
        ->assertInertia(fn ($page) => $page->where('canSetAlias', false));
});
