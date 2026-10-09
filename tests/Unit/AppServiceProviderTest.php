<?php

use App\Providers\AppServiceProvider;
use Illuminate\Validation\Rules\Password;
use Tests\TestCase;

uses(TestCase::class);

test('password defaults are strict in production only', function () {
    expect(Password::default()->appliedRules()['min'])->toBe(8);

    $this->app['env'] = 'production';
    (new AppServiceProvider($this->app))->boot();

    expect(Password::default()->appliedRules())->toMatchArray(['min' => 12, 'mixedCase' => true, 'symbols' => true]);
});
