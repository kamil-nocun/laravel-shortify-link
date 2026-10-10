<?php

namespace Tests;

use App\Billing\PackageBilling;
use App\Support\DomainVerifier;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;
use Tests\Support\FakeDomainVerifier;
use Tests\Support\FakePackageBilling;

abstract class TestCase extends BaseTestCase
{
    protected FakePackageBilling $billing;

    protected FakeDomainVerifier $verifier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->billing = new FakePackageBilling;
        $this->app->instance(PackageBilling::class, $this->billing);

        $this->verifier = new FakeDomainVerifier;
        $this->app->instance(DomainVerifier::class, $this->verifier);
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
