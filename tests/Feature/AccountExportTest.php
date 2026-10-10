<?php

use App\Models\Domain;
use App\Models\Link;
use App\Models\LinkClick;
use App\Models\Payment;
use App\Models\User;
use App\Support\ConsentDocuments;

function exportFiles(User $user): array
{
    $response = test()->actingAs($user)->get(route('privacy.export'))->assertOk();
    $zip = new ZipArchive;
    $zip->open($response->getFile()->getPathname());

    $files = [];

    for ($index = 0; $index < $zip->numFiles; $index++) {
        $files[$zip->getNameIndex($index)] = preg_replace('/^\xEF\xBB\xBF/', '', $zip->getFromIndex($index));
    }

    $zip->close();

    return $files;
}

test('the response is a zip download named after the date', function () {
    $this->freezeTime();

    $this->actingAs(User::factory()->create())->get(route('privacy.export'))
        ->assertOk()
        ->assertDownload('shortify-export-'.now()->toDateString().'.zip');
});

test('guests are redirected to login', function () {
    $this->get(route('privacy.export'))->assertRedirect(route('login'));
});

test('contains the five csv files with the expected headers', function () {
    $files = exportFiles(User::factory()->create());

    expect(array_keys($files))->toEqualCanonicalizing(['profile.csv', 'links.csv', 'domains.csv', 'payments.csv', 'consents.csv'])
        ->and(strtok($files['profile.csv'], "\n"))->toBe('name,email,role,package,created_at,email_verified_at')
        ->and(strtok($files['links.csv'], "\n"))->toBe('title,code,alias,original_url,domain,active,expires_at,created_at,clicks')
        ->and(strtok($files['domains.csv'], "\n"))->toBe('host,verified_at,created_at')
        ->and(strtok($files['payments.csv'], "\n"))->toBe('invoice,billing_period,status,amount,currency,issued_at,paid_at')
        ->and(strtok($files['consents.csv'], "\n"))->toBe('document,slug,version_id,accepted_at');
});

test('an empty account still produces headers only', function () {
    $files = exportFiles(User::factory()->create());

    expect(substr_count($files['links.csv'], "\n"))->toBe(1)
        ->and(substr_count($files['payments.csv'], "\n"))->toBe(1);
});

test('exports only the signed-in user data', function () {
    $user = User::factory()->create(['name' => 'Ann Example']);
    $other = User::factory()->create(['name' => 'Bob Other']);
    Link::factory()->for($user)->create(['title' => 'My link']);
    Link::factory()->for($other)->create(['title' => 'Their link']);
    Domain::factory()->for($other)->create(['host' => 'other.example.com']);
    Payment::factory()->for($other)->create(['stripe_invoice_id' => 'in_other']);

    $files = exportFiles($user);

    expect($files['profile.csv'])->toContain('Ann Example')->not->toContain('Bob Other')
        ->and($files['links.csv'])->toContain('My link')->not->toContain('Their link')
        ->and($files['domains.csv'])->not->toContain('other.example.com')
        ->and($files['payments.csv'])->not->toContain('in_other');
});

test('link rows carry the summed clicks and the domain host', function () {
    $user = User::factory()->create();
    $domain = Domain::factory()->verified()->for($user)->create(['host' => 'go.example.com']);
    $link = Link::factory()->for($user)->create(['domain_id' => $domain->id]);
    LinkClick::factory()->for($link)->count(2)->create();
    LinkClick::factory()->for($link)->summary(5)->create();

    $files = exportFiles($user);

    expect($files['links.csv'])->toContain('go.example.com')->toContain(',7'."\n")
        ->and($files['domains.csv'])->toContain('go.example.com');
});

test('link titles that look like formulas are escaped', function () {
    $user = User::factory()->create();
    Link::factory()->for($user)->create(['title' => '=HYPERLINK("http://evil","x")']);

    expect(exportFiles($user)['links.csv'])->toContain("\"'=HYPERLINK(")->not->toContain(',=HYPERLINK');
});

test('payments and consents appear', function () {
    documentPage('terms');
    $user = User::factory()->create();
    Payment::factory()->for($user)->create(['stripe_invoice_id' => 'in_mine']);
    ConsentDocuments::accept($user);

    $files = exportFiles($user);

    expect($files['payments.csv'])->toContain('in_mine')
        ->and($files['consents.csv'])->toContain('Terms,terms,');
});

test('every csv starts with a utf-8 byte order mark so Excel reads Polish characters', function () {
    $user = User::factory()->create(['name' => 'Zażółć Gęślą']);
    $response = $this->actingAs($user)->get(route('privacy.export'))->assertOk();
    $zip = new ZipArchive;
    $zip->open($response->getFile()->getPathname());

    for ($index = 0; $index < $zip->numFiles; $index++) {
        expect(str_starts_with($zip->getFromIndex($index), "\xEF\xBB\xBF"))->toBeTrue();
    }
});

test('the export is throttled', function () {
    $user = User::factory()->create();

    foreach (range(1, 6) as $ignored) {
        $this->actingAs($user)->get(route('privacy.export'))->assertOk();
    }

    $this->actingAs($user)->get(route('privacy.export'))->assertStatus(429);
});
