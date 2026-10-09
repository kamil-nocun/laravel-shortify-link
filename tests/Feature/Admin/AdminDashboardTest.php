<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('admin sees the dashboard report', function () {
    $this->actingAs(User::factory()->admin()->create())->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Dashboard')
            ->hasAll(['counters', 'series', 'topLinks', 'topUsers']));
});

test('period parameter is honoured and validated', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get(route('admin.dashboard', ['period' => '7d']))->assertOk();
    $this->actingAs($admin)->get(route('admin.dashboard', ['period' => 'bogus']))->assertSessionHasErrors('period');
});

test('regular users cannot open the dashboard', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.dashboard'))->assertForbidden();
});
