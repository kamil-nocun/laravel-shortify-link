<?php

use App\Models\Domain;
use App\Models\Link;
use App\Models\User;
use App\Support\LinkCodeGenerator;
use Illuminate\Database\UniqueConstraintViolationException;

test('user creates a link on their active domain', function () {
    config(['app.url' => 'http://shortify.test']);
    $user = User::factory()->create();
    $domain = Domain::factory()->verified()->for($user)->create(['host' => 'go.example.com']);

    $this->actingAs($user)->post(route('links.store'), ['original_url' => 'https://a.test', 'domain_id' => $domain->id])
        ->assertSessionHasNoErrors();

    $link = $user->links()->firstOrFail();
    expect($link->domain_id)->toBe($domain->id)->and($link->short_url)->toStartWith('http://go.example.com/');
});

test('user cannot use someone else\'s, unverified or disabled domain', function (callable $make) {
    $user = User::factory()->create();
    $domain = $make($user);

    $this->actingAs($user)->post(route('links.store'), ['original_url' => 'https://a.test', 'domain_id' => $domain->id])
        ->assertSessionHasErrors('domain_id');

    expect(Link::count())->toBe(0);
})->with([
    'foreign' => fn () => Domain::factory()->verified()->create(),
    'unverified' => fn (User $u) => Domain::factory()->for($u)->create(),
    'disabled' => fn (User $u) => Domain::factory()->verified()->disabledByAdmin()->for($u)->create(),
]);

test('admin creating a link for a user can only pick that user\'s active domains', function () {
    $admin = User::factory()->superAdmin()->create();
    $user = User::factory()->create();
    $own = Domain::factory()->verified()->for($user)->create();
    $other = Domain::factory()->verified()->create();

    $this->actingAs($admin)->post(route('admin.users.links.store', $user), ['original_url' => 'https://a.test', 'domain_id' => $other->id])
        ->assertSessionHasErrors('domain_id');
    $this->actingAs($admin)->post(route('admin.users.links.store', $user), ['original_url' => 'https://a.test', 'domain_id' => $own->id])
        ->assertSessionHasNoErrors();

    expect($user->links()->value('domain_id'))->toBe($own->id);
});

test('generator allows the same code on two domains but not twice on one', function () {
    $a = Domain::factory()->verified()->create();
    $b = Domain::factory()->verified()->create();
    Link::factory()->create(['domain_id' => $a->id, 'code' => 'same12']);
    Link::factory()->create(['domain_id' => $b->id, 'code' => 'same12']);
    Link::factory()->create(['code' => 'same12']);

    expect(Link::count())->toBe(3);
    expect(fn () => Link::factory()->create(['domain_id' => $a->id, 'code' => 'same12']))->toThrow(UniqueConstraintViolationException::class);
    expect(fn () => Link::factory()->create(['code' => 'same12']))->toThrow(UniqueConstraintViolationException::class);

    $generator = new class extends LinkCodeGenerator
    {
        private int $i = 0;

        protected function randomCode(int $length): string
        {
            return ['same12', 'fresh2'][$this->i++];
        }
    };

    expect($generator->generate($a->id))->toBe('fresh2');
});

test('domain link creation still respects the link limit', function () {
    $user = User::factory()->create();
    $domain = Domain::factory()->verified()->for($user)->create();
    Link::factory()->count($user->linkLimit())->for($user)->create();

    $this->actingAs($user)->post(route('links.store'), ['original_url' => 'https://a.test', 'domain_id' => $domain->id])
        ->assertRedirect(route('links.index'));

    expect($user->links()->where('domain_id', $domain->id)->count())->toBe(0);
});
