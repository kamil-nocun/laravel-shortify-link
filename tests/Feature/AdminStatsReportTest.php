<?php

use App\Http\Requests\Admin\AdminStatsRequest;
use App\Models\Link;
use App\Models\LinkClick;
use App\Models\User;
use App\Support\AdminStatsReport;

beforeEach(function () {
    $this->freezeTime();
});

function adminReport(?User $user = null, array $query = ['period' => '7d']): array
{
    $request = AdminStatsRequest::create('/', 'GET', $query);
    $request->setUserResolver(fn () => User::factory()->superAdmin()->make());

    return (new AdminStatsReport)->build($user, $request);
}

test('global report counts users, links and clicks', function () {
    $first = User::factory()->create();
    $second = User::factory()->create();
    $active = Link::factory()->for($first)->create();
    Link::factory()->for($first)->inactive()->create();
    Link::factory()->for($second)->disabledByAdmin()->create();
    LinkClick::factory()->for($active)->count(2)->create();
    LinkClick::factory()->for($active)->create(['clicked_at' => now()->subDays(2)]);
    LinkClick::factory()->for($active)->create(['clicked_at' => now()->subDays(20)]);

    $report = adminReport();

    expect($report['counters'])->toBe(['users' => 2, 'links' => 3, 'activeLinks' => 1, 'disabledLinks' => 2, 'clicks' => 3])
        ->and($report['total'])->toBe(3)
        ->and($report['series'])->toHaveCount(7)
        ->and(array_sum(array_column($report['series'], 'count')))->toBe(3)
        ->and($report['series'][6])->toBe(['date' => now()->format('Y-m-d'), 'count' => 2])
        ->and($report['series'][0]['count'])->toBe(0);
});

test('top links and users are sorted by clicks and capped at ten', function () {
    $top = User::factory()->create();
    $other = User::factory()->create();
    $bestLink = Link::factory()->for($top)->create();
    LinkClick::factory()->for($bestLink)->count(3)->create();
    Link::factory()->for($other)->count(11)->create()->each(fn (Link $link) => LinkClick::factory()->for($link)->create());

    $report = adminReport();

    expect($report['topLinks'])->toHaveCount(10)
        ->and($report['topLinks'][0])->toBe(['id' => $bestLink->id, 'title' => $bestLink->title, 'code' => $bestLink->code, 'clicks' => 3])
        ->and($report['topUsers'])->toHaveCount(2)
        ->and($report['topUsers'][0])->toBe(['id' => $other->id, 'name' => $other->name, 'email' => $other->email, 'clicks' => 11])
        ->and($report['topUsers'][1]['clicks'])->toBe(3);
});

test('user report only covers that user', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $link = Link::factory()->for($user)->create();
    LinkClick::factory()->for($link)->count(2)->create();
    LinkClick::factory()->for(Link::factory()->for($other))->count(5)->create();

    $report = adminReport($user);

    expect($report['counters'])->toBe(['links' => 1, 'activeLinks' => 1, 'disabledLinks' => 0, 'clicks' => 2])
        ->and($report['total'])->toBe(2)
        ->and($report['topLinks'])->toHaveCount(1)
        ->and($report['topUsers'])->toBe([]);
});

test('empty data yields zeros and empty lists', function () {
    $user = User::factory()->create();

    foreach ([adminReport($user), adminReport(null, ['period' => 'all'])] as $report) {
        expect($report['total'])->toBe(0)
            ->and($report['topLinks'])->toBe([])
            ->and($report['topUsers'])->toBe([])
            ->and($report['counters']['clicks'])->toBe(0)
            ->and($report['counters']['links'])->toBe(0);
    }

    expect(adminReport($user)['series'])->toHaveCount(7);
});

test('all period starts at the first click', function () {
    $link = Link::factory()->create();
    LinkClick::factory()->for($link)->create(['clicked_at' => now()->subDays(3)]);

    $report = adminReport(null, ['period' => 'all']);

    expect($report['granularity'])->toBe('month')->and($report['series'])->toHaveCount(1)->and($report['total'])->toBe(1);
});
