<?php

use App\Models\User;
use App\Notifications\AccountCreatedNotification;
use Tests\TestCase;

uses(TestCase::class);

test('mail contains a relative password reset link and the expiry', function () {
    $user = User::factory()->make(['email' => 'new@example.com']);

    $mail = (new AccountCreatedNotification('tok'))->toMail($user);

    expect($mail->actionUrl)->toContain('/reset-password/tok')->toContain('email=new%40example.com')
        ->and($mail->outroLines[0])->toContain((string) config('auth.passwords.users.expire'));
});
