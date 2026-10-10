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

test('job requeues itself with a five minute delay while the token is missing', function () {
    Queue::fake();
    $this->freezeTime();
    $domain = Domain::factory()->create();

    (new VerifyDomain($domain->id))->handle($this->verifier);

    expect($domain->fresh()->verified_at)->toBeNull();
    Queue::assertPushed(VerifyDomain::class, fn (VerifyDomain $job) => $job->domainId === $domain->id
        && $job->delay->equalTo(now()->addMinutes(5)));
});

test('job gives up on domains older than 72 hours', function () {
    Queue::fake();
    $domain = Domain::factory()->create(['created_at' => now()->subHours(73)]);
    $this->verifier->verified[] = $domain->id;

    (new VerifyDomain($domain->id))->handle($this->verifier);

    expect($domain->fresh()->verified_at)->toBeNull();
    Queue::assertNothingPushed();
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

test('DnsDomainVerifier matches only the exact TXT value', function () {
    $domain = Domain::factory()->make(['verification_token' => 'abc123']);
    $verifier = new DnsDomainVerifier;

    expect($verifier->matches([['txt' => 'shortify-verify=abc123']], $domain))->toBeTrue()
        ->and($verifier->matches([['txt' => 'other'], ['txt' => 'shortify-verify=abc123']], $domain))->toBeTrue()
        ->and($verifier->matches([['txt' => 'shortify-verify=abc1234']], $domain))->toBeFalse()
        ->and($verifier->matches([['txt' => 'xshortify-verify=abc123']], $domain))->toBeFalse()
        ->and($verifier->matches([], $domain))->toBeFalse();
});
