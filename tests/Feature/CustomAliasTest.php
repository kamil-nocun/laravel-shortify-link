<?php

use App\Actions\CreateLink;
use App\Http\Controllers\LinkController;
use App\Http\Requests\Links\UpdateLinkRequest;
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

test('a link with an alias redirects from both the code and the alias', function (string $alias) {
    storeLink(userWithAlias(), ['alias' => $alias])->assertSessionHasNoErrors();

    $link = Link::sole();
    expect(strlen($link->code))->toBeGreaterThanOrEqual(LinkCodeGenerator::MIN_LENGTH)
        ->and($link->alias)->toBe($alias)
        ->and($link->alias_url)->toEndWith('/'.$alias);
    $this->get('/'.$link->code)->assertRedirect('https://example.com/x');
    $this->get('/'.$alias)->assertRedirect('https://example.com/x');
})->with(['promocja-lato', 'a_b-1']);

test('alias_url is null without an alias', function () {
    expect(Link::factory()->create()->alias_url)->toBeNull();
});

test('a value is taken per domain by either code or alias', function () {
    $domain = Domain::factory()->verified()->create();
    $byCode = Link::factory()->create(['code' => 'codeone', 'domain_id' => null]);
    Link::factory()->create(['code' => 'other11', 'alias' => 'aliasone', 'domain_id' => null]);

    expect(Link::isCodeOrAliasTaken(null, 'codeone'))->toBeTrue()
        ->and(Link::isCodeOrAliasTaken(null, 'aliasone'))->toBeTrue()
        ->and(Link::isCodeOrAliasTaken($domain->id, 'codeone'))->toBeFalse()
        ->and(Link::isCodeOrAliasTaken(null, 'codeone', $byCode->id))->toBeFalse();
});

test('the generator skips values taken as an alias', function () {
    Link::factory()->create(['code' => 'zzzzzz', 'alias' => 'AAAAAA', 'domain_id' => null]);
    $generator = new class extends LinkCodeGenerator
    {
        private array $queue = ['AAAAAA', 'BBBBBB'];

        protected function randomCode(int $length): string
        {
            return array_shift($this->queue);
        }
    };

    expect($generator->generate())->toBe('BBBBBB');
});

test('an alias on one domain does not redirect from another domain', function () {
    $a = Domain::factory()->verified()->create(['host' => 'a.example.test']);
    $b = Domain::factory()->verified()->create(['host' => 'b.example.test']);
    Link::factory()->create(['domain_id' => $a->id, 'alias' => 'shared', 'original_url' => 'https://a.test/x']);
    Link::factory()->create(['domain_id' => $b->id, 'code' => 'shared', 'original_url' => 'https://b.test/x']);

    $this->get('http://a.example.test/shared')->assertRedirect('https://a.test/x');
    $this->get('http://b.example.test/shared')->assertRedirect('https://b.test/x');
});

test('invalid aliases are rejected', function (string $alias) {
    $user = userWithAlias();
    Link::factory()->create(['code' => 'taken1', 'domain_id' => null]);
    Link::factory()->create(['alias' => 'aliased1', 'domain_id' => null]);

    storeLink($user, ['alias' => $alias])->assertSessionHasErrors('alias');
    expect(Link::count())->toBe(2);
})->with([
    'too short' => 'ab',
    'too long' => str_repeat('a', 51),
    'space' => 'ab c',
    'unicode' => 'zażółć',
    'reserved' => 'admin',
    'reserved case' => 'Admin',
    'reserved route' => 'dashboard',
    'duplicate' => 'taken1',
    'alias of another link' => 'aliased1',
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
    Link::factory()->create(['code' => 'other1', 'alias' => 'racing', 'domain_id' => null]);

    expect(fn () => app(CreateLink::class)($user, ['original_url' => 'https://example.com', 'alias' => 'racing']))
        ->toThrow(fn (ValidationException $e) => expect($e->errors())->toHaveKey('alias'));
});

test('an admin creating a link checks the client permission', function () {
    $admin = User::factory()->superAdmin()->create();
    $plain = User::factory()->create();
    $allowed = userWithAlias();
    $payload = ['original_url' => 'https://example.com', 'is_active' => true, 'alias' => 'fromadmin'];

    $this->actingAs($admin)->post(route('admin.users.links.store', $plain), $payload)->assertSessionHasErrors('alias');
    $this->actingAs($admin)->post(route('admin.users.links.store', $allowed), $payload)->assertSessionHasNoErrors();

    expect($allowed->links()->sole()->alias)->toBe('fromadmin');
});

test('update never changes the code', function () {
    $user = userWithAlias();
    $link = Link::factory()->for($user)->create(['code' => 'keepme']);

    $this->actingAs($user)->patch(route('links.update', $link), ['original_url' => 'https://example.com/n', 'is_active' => true, 'alias' => 'other'])
        ->assertSessionHasNoErrors();

    expect($link->fresh()->code)->toBe('keepme');
});

function updateLink(User $user, Link $link, array $extra = [])
{
    return test()->actingAs($user)->patch(route('links.update', $link), ['original_url' => 'https://example.com/n', 'is_active' => true, ...$extra]);
}

test('an owner with permission sets, changes and clears the alias', function () {
    $user = userWithAlias();
    $link = Link::factory()->for($user)->create(['code' => 'keepme', 'alias' => null, 'domain_id' => null]);

    updateLink($user, $link, ['alias' => 'first'])->assertSessionHasNoErrors();
    expect($link->fresh()->alias)->toBe('first');

    updateLink($user, $link, ['alias' => 'second'])->assertSessionHasNoErrors();
    $this->get('/first')->assertNotFound();
    $this->get('/second')->assertRedirect('https://example.com/n');
    $this->get('/keepme')->assertRedirect();

    updateLink($user, $link, ['alias' => ''])->assertSessionHasNoErrors();
    expect($link->fresh()->alias)->toBeNull();
    $this->get('/keepme')->assertRedirect();
});

test('resending the own alias passes', function () {
    $user = userWithAlias();
    $link = Link::factory()->for($user)->create(['alias' => 'mine123', 'domain_id' => null]);

    updateLink($user, $link, ['alias' => 'mine123', 'title' => 'New'])->assertSessionHasNoErrors();

    expect($link->fresh()->title)->toBe('New');
});

test('an edited alias cannot collide or be reserved', function (string $value) {
    $user = userWithAlias();
    $link = Link::factory()->for($user)->create(['domain_id' => null]);
    Link::factory()->create(['code' => 'theircode', 'alias' => 'theiralias', 'domain_id' => null]);

    updateLink($user, $link, ['alias' => $value])->assertSessionHasErrors('alias');
})->with(['code' => 'theircode', 'alias' => 'theiralias', 'reserved' => 'admin']);

test('a user without permission cannot change or clear an alias', function (string $value) {
    $user = User::factory()->create();
    $link = Link::factory()->for($user)->create(['alias' => 'kept123', 'domain_id' => null]);

    updateLink($user, $link, ['alias' => $value])->assertSessionHasErrors('alias');

    expect($link->fresh()->alias)->toBe('kept123');
})->with(['change' => 'changed', 'empty' => '']);

test('a user without permission can edit other fields', function () {
    $user = User::factory()->create();
    $link = Link::factory()->for($user)->create(['alias' => 'kept123', 'domain_id' => null]);
    $plain = Link::factory()->for($user)->create(['alias' => null, 'domain_id' => null]);

    updateLink($user, $link, ['alias' => 'kept123', 'title' => 'A'])->assertSessionHasNoErrors();
    updateLink($user, $link, ['title' => 'B'])->assertSessionHasNoErrors();
    updateLink($user, $plain, ['alias' => ''])->assertSessionHasNoErrors();

    expect($link->fresh())->title->toBe('B')->alias->toBe('kept123');
});

test('after losing the permission the title can still be edited', function () {
    $user = userWithAlias();
    $link = Link::factory()->for($user)->create(['alias' => 'lasting', 'domain_id' => null]);
    PackageAssignment::where('user_id', $user->id)->update(['allows_custom_alias' => false]);

    updateLink($user, $link, ['alias' => 'lasting', 'title' => 'T'])->assertSessionHasNoErrors();

    expect($link->fresh()->title)->toBe('T');
});

test('an alias race on update becomes a validation error on alias', function () {
    $user = userWithAlias();
    $link = Link::factory()->for($user)->create(['domain_id' => null]);
    Link::factory()->create(['alias' => 'racing', 'domain_id' => null]);
    $request = new class extends UpdateLinkRequest
    {
        public function validated($key = null, $default = null)
        {
            return ['alias' => 'racing'];
        }
    };

    expect(fn () => app(LinkController::class)->update($request, $link))
        ->toThrow(fn (ValidationException $e) => expect($e->errors())->toHaveKey('alias'));
});

test('the edit page exposes alias props', function () {
    $user = userWithAlias();
    $link = Link::factory()->for($user)->create();

    $this->actingAs($user)->get(route('links.edit', $link))
        ->assertInertia(fn ($page) => $page->where('canSetAlias', true)->has('mainHost'));
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

    $publicDirs = collect(glob(public_path('*'), GLOB_ONLYDIR))->map(fn ($dir) => basename($dir))
        ->reject(fn (string $name) => str_contains($name, '.') || ReservedAliases::isReserved($name))->values()->all();

    expect($missing)->toBe([])->and($publicDirs)->toBe([]);
});

test('the create pages expose whether the alias field is allowed', function () {
    $this->actingAs(userWithAlias())->get(route('links.create'))
        ->assertInertia(fn ($page) => $page->where('canSetAlias', true)->where('mainHost', parse_url(config('app.url'), PHP_URL_HOST).(parse_url(config('app.url'), PHP_URL_PORT) ? ':'.parse_url(config('app.url'), PHP_URL_PORT) : '')));
    $this->actingAs(User::factory()->create())->get(route('links.create'))
        ->assertInertia(fn ($page) => $page->where('canSetAlias', false));

    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin)->get(route('admin.users.links.create', userWithAlias()))
        ->assertInertia(fn ($page) => $page->where('canSetAlias', true));
    $this->actingAs($admin)->get(route('admin.users.links.create', User::factory()->create()))
        ->assertInertia(fn ($page) => $page->where('canSetAlias', false));
});

test('an inactive or expired link is not reachable via its alias', function (string $state) {
    Link::factory()->{$state}()->create(['alias' => 'hidden1', 'domain_id' => null]);

    $this->get('/hidden1')->assertNotFound();
})->with(['inactive', 'expired']);

test('an array alias is a validation error, not a server error', function () {
    $user = userWithAlias();
    $link = Link::factory()->for($user)->create(['domain_id' => null]);

    storeLink($user, ['alias' => ['x']])->assertSessionHasErrors('alias');
    updateLink($user, $link, ['alias' => ['x']])->assertSessionHasErrors('alias');
});

test('an unchanged alias stays editable after it became reserved', function () {
    $user = userWithAlias();
    $link = Link::factory()->for($user)->create(['alias' => 'admin', 'domain_id' => null]);

    updateLink($user, $link, ['alias' => 'admin', 'title' => 'Still ok'])->assertSessionHasNoErrors();

    expect($link->fresh()->title)->toBe('Still ok');
});

test('a user without permission cannot add an alias to a link without one', function () {
    $user = User::factory()->create();
    $link = Link::factory()->for($user)->create(['alias' => null, 'domain_id' => null]);

    updateLink($user, $link, ['alias' => 'newone'])->assertSessionHasErrors('alias');

    expect($link->fresh()->alias)->toBeNull();
});

test('domain_id is ignored on update', function () {
    $user = userWithAlias();
    $domain = Domain::factory()->verified()->create(['user_id' => $user->id]);
    $link = Link::factory()->for($user)->create(['domain_id' => null]);

    updateLink($user, $link, ['domain_id' => $domain->id])->assertSessionHasNoErrors();

    expect($link->fresh()->domain_id)->toBeNull();
});

test('an alias equal to the own code is accepted on update', function () {
    $user = userWithAlias();
    $link = Link::factory()->for($user)->create(['code' => 'owncode1', 'domain_id' => null]);

    updateLink($user, $link, ['alias' => 'owncode1'])->assertSessionHasNoErrors();

    expect($link->fresh()->alias)->toBe('owncode1');
});
