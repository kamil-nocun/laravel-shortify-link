<?php

use App\Models\User;

test('creates a verified super admin when the account is missing', function () {
    $this->artisan('user:make-super-admin', ['email' => 'boss@example.com'])
        ->expectsQuestion('Name', 'Boss')
        ->expectsQuestion('Password', 'S3cure-pass-word!')
        ->assertExitCode(0);

    $user = User::where('email', 'boss@example.com')->sole();
    expect($user->isSuperAdmin())->toBeTrue()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->name)->toBe('Boss');
});

test('promotes an existing account without changing it', function (string $state) {
    $user = User::factory()->$state()->create(['name' => 'Keep']);
    $hash = $user->password;

    $this->artisan('user:make-super-admin', ['email' => $user->email])
        ->expectsConfirmation("Promote {$user->email} to the single super admin?", 'yes')
        ->assertExitCode(0);

    $user->refresh();
    expect($user->isSuperAdmin())->toBeTrue()
        ->and($user->name)->toBe('Keep')
        ->and($user->password)->toBe($hash);
})->with(['unverified', 'admin']);

test('refuses when a super admin already exists', function (bool $sameEmail) {
    $existing = User::factory()->superAdmin()->create();
    $other = User::factory()->create();

    $this->artisan('user:make-super-admin', ['email' => $sameEmail ? $existing->email : $other->email])
        ->assertExitCode(1);

    expect(User::count())->toBe(2)
        ->and($other->refresh()->isClient())->toBeTrue()
        ->and($existing->refresh()->isSuperAdmin())->toBeTrue();
})->with([true, false]);

test('refuses an invalid email argument without asking anything', function () {
    $this->artisan('user:make-super-admin', ['email' => 'not-an-email'])->assertExitCode(1);

    expect(User::count())->toBe(0);
});

test('refuses a weak password', function (string $password) {
    $this->artisan('user:make-super-admin', ['email' => 'boss@example.com'])
        ->expectsQuestion('Name', 'Boss')
        ->expectsQuestion('Password', $password)
        ->assertExitCode(1);

    expect(User::count())->toBe(0);
})->with(['123', '']);

test('asks for the email when no argument is given', function () {
    $this->artisan('user:make-super-admin')
        ->expectsQuestion('Email', 'boss@example.com')
        ->expectsQuestion('Name', 'Boss')
        ->expectsQuestion('Password', 'S3cure-pass-word!')
        ->assertExitCode(0);

    expect(User::where('email', 'boss@example.com')->sole()->isSuperAdmin())->toBeTrue();
});

test('does not promote an existing account when the confirmation is declined', function () {
    $user = User::factory()->create();

    $this->artisan('user:make-super-admin', ['email' => $user->email])
        ->expectsConfirmation("Promote {$user->email} to the single super admin?", 'no')
        ->assertExitCode(1);

    expect($user->refresh()->isSuperAdmin())->toBeFalse();
});

test('refuses before asking for a password when a super admin already exists', function () {
    User::factory()->superAdmin()->create();

    $this->artisan('user:make-super-admin', ['email' => 'new@example.com'])->assertExitCode(1);

    expect(User::where('email', 'new@example.com')->exists())->toBeFalse();
});

test('promoting a blocked account clears blocked_at', function () {
    $user = User::factory()->blocked()->create();

    $this->artisan('user:make-super-admin', ['email' => $user->email])
        ->expectsConfirmation("Promote {$user->email} to the single super admin?", 'yes')
        ->assertExitCode(0);

    expect($user->refresh()->blocked_at)->toBeNull();
});
