<?php

use App\Models\Package;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the home page renders for guests and signed-in users', function () {
    $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page->component('FrontApp'));
    $this->actingAs(User::factory()->create())->get('/')->assertOk();
});

test('the home page lists the free package first and the purchasable ones by price', function () {
    $free = Package::where('is_default', true)->firstOrFail();
    Package::factory()->create(['name' => 'Pro', 'link_limit' => 500, 'domain_limit' => 3, 'allows_custom_alias' => true, 'price_monthly' => 4900, 'price_yearly' => 49000]);
    Package::factory()->create(['name' => 'Starter', 'link_limit' => 100, 'domain_limit' => 0, 'price_monthly' => 1900]);
    Package::factory()->create(['name' => 'Unpriced', 'price_monthly' => null, 'price_quarterly' => null, 'price_yearly' => null, 'price_one_time' => null]);
    Package::factory()->create(['name' => 'Retired', 'price_monthly' => 100])->delete();

    $this->get('/')->assertInertia(fn (Assert $page) => $page
        ->where('packages.0.name', $free->name)
        ->where('packages.0.price', null)
        ->where('packages.1.name', 'Starter')
        ->where('packages.1.price', 1900)
        ->where('packages.1.period', 'monthly')
        ->where('packages.2.name', 'Pro')
        ->where('packages.2.allows_custom_alias', true)
        ->has('packages', 3));
});

test('a package sold only for another period shows that period', function () {
    Package::factory()->create(['name' => 'Annual', 'price_monthly' => null, 'price_quarterly' => null, 'price_one_time' => null, 'price_yearly' => 9900]);

    $this->get('/')->assertInertia(fn (Assert $page) => $page
        ->where('packages.1.name', 'Annual')
        ->where('packages.1.price', 9900)
        ->where('packages.1.period', 'yearly'));
});

test('the footer lists published legal documents only', function () {
    documentPage('terms');
    documentPage('privacy-policy', published: false);
    documentPage('about-us', legal: false);

    $this->get('/')->assertInertia(fn (Assert $page) => $page
        ->where('legalDocuments', [['title' => 'Terms', 'slug' => 'terms']]));
});

test('public CMS pages carry the same footer documents', function () {
    documentPage('terms');
    documentPage('about-us', legal: false);

    $this->get(route('content.show', 'about-us'))->assertInertia(fn (Assert $page) => $page
        ->component('content/Show')
        ->where('legalDocuments', [['title' => 'Terms', 'slug' => 'terms']]));
});
