<?php

use App\Models\Package;
use App\Models\PackageAssignment;

test('assignments without a domain limit get the limit of their package', function () {
    $package = Package::factory()->create(['domain_limit' => 5]);
    $assignment = PackageAssignment::factory()->create(['package_id' => $package->id, 'domain_limit' => 0]);

    $this->artisan('domains:backfill-limits')->expectsOutput('Updated 1 assignments.')->assertSuccessful();

    expect($assignment->fresh()->domain_limit)->toBe(5);
});

test('assignments that already carry a domain limit keep it', function () {
    $package = Package::factory()->create(['domain_limit' => 5]);
    $assignment = PackageAssignment::factory()->create(['package_id' => $package->id, 'domain_limit' => 2]);

    $this->artisan('domains:backfill-limits')->expectsOutput('Updated 0 assignments.')->assertSuccessful();

    expect($assignment->fresh()->domain_limit)->toBe(2);
});

test('assignments of a package without domains stay at zero', function () {
    $package = Package::factory()->create(['domain_limit' => 0]);
    $assignment = PackageAssignment::factory()->create(['package_id' => $package->id, 'domain_limit' => 0]);

    $this->artisan('domains:backfill-limits')->assertSuccessful();

    expect($assignment->fresh()->domain_limit)->toBe(0);
});
