<?php

use App\Jobs\VerifyDomain;
use App\Models\Domain;
use App\Support\DnsDomainVerifier;
use Illuminate\Support\Facades\Queue;

test('job verifies a domain whose token is present', function () {
    $domain = Domain::factory()->create();
    $this->verifier->verified[] = $domain->id;

    (new VerifyDomain($domain->id))->handle($this->verifier);

    expect($domain->fresh()->verified_at)->not->toBeNull();
});

test('job releases itself for five minutes while the token is missing', function () {
    $domain = Domain::factory()->create();
    $job = (new VerifyDomain($domain->id))->withFakeQueueInteractions();

    $job->handle($this->verifier);

    expect($domain->fresh()->verified_at)->toBeNull();
    $job->assertReleased(300);
});

test('job deletes an unverified domain older than 72 hours', function () {
    $domain = Domain::factory()->create(['created_at' => now()->subHours(73)]);
    $job = (new VerifyDomain($domain->id))->withFakeQueueInteractions();

    $job->handle($this->verifier);

    expect($domain->fresh())->toBeNull();
    $job->assertNotReleased();
});

test('job keeps younger unverified domains and never deletes verified ones', function () {
    $young = Domain::factory()->create(['created_at' => now()->subHours(71)]);
    $old = Domain::factory()->verified()->create(['created_at' => now()->subDays(30)]);

    (new VerifyDomain($young->id))->withFakeQueueInteractions()->handle($this->verifier);
    (new VerifyDomain($old->id))->withFakeQueueInteractions()->handle($this->verifier);

    expect($young->fresh())->not->toBeNull()->and($old->fresh())->not->toBeNull();
});

test('job ignores verified and deleted domains', function () {
    Queue::fake();
    $verified = Domain::factory()->verified()->create();
    $verifiedAt = $verified->verified_at;
    $deleted = Domain::factory()->create();
    $deletedId = $deleted->id;
    $deleted->delete();

    (new VerifyDomain($verified->id))->handle($this->verifier);
    (new VerifyDomain($deletedId))->handle($this->verifier);

    expect($verified->fresh()->verified_at->equalTo($verifiedAt))->toBeTrue();
    Queue::assertNothingPushed();
});

test('the TXT record shown to the user is exactly what the verifier accepts', function () {
    $domain = Domain::factory()->make(['host' => 'a.example.com', 'verification_token' => 'tok']);

    expect($domain->txtName())->toBe('_shortify-verify.a.example.com')
        ->and((new DnsDomainVerifier)->matches([['txt' => $domain->txtValue()]], $domain))->toBeTrue();
});

test('DnsDomainVerifier matches only the exact TXT value', function () {
    $domain = Domain::factory()->make(['verification_token' => 'abc123']);
    $verifier = new DnsDomainVerifier;

    expect($verifier->matches([['txt' => 'shortify-verify=abc123']], $domain))->toBeTrue()
        ->and($verifier->matches([['txt' => 'other'], ['txt' => 'shortify-verify=abc123']], $domain))->toBeTrue()
        ->and($verifier->matches([['txt' => 'shortify-verify=abc1234']], $domain))->toBeFalse()
        ->and($verifier->matches([['txt' => 'xshortify-verify=abc123']], $domain))->toBeFalse()
        ->and($verifier->matches([], $domain))->toBeFalse();
});
