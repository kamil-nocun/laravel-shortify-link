<?php

namespace App\Support;

use App\Http\Requests\Links\LinkStatsRequest;
use App\Models\Link;
use App\Models\LinkClick;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;

class AdminStatsReport
{
    private const TOP_LIMIT = 10;

    /**
     * Stats of the whole system, or of one user's links when a user is given.
     *
     * @return array{period: array<string, ?string>, granularity: string, total: int, series: list<array{date: string, count: int}>, counters: array<string, int>, topLinks: list<array{id: string, title: ?string, code: string, clicks: int}>, topUsers: list<array{id: string, name: string, email: string, clicks: int}>}
     */
    public function build(?User $user, LinkStatsRequest $request): array
    {
        $actor = $request->user();
        $scopeToActor = $user === null && ! $actor->isSuperAdmin();

        $scoped = fn (): Builder => LinkClick::query()->toBase()
            ->join('links', 'links.id', '=', (new LinkClick)->qualifyColumn('link_id'))
            ->when($user, fn (Builder $query) => $query->where('links.user_id', $user->id))
            ->when($scopeToActor, fn (Builder $query) => $query->whereIn('links.user_id', User::visibleTo($actor)->select('id')));

        $firstClick = $request->period()['period'] === 'all' ? $scoped()->min('clicked_at') : null;
        $period = StatsPeriod::resolve($request->period(), $firstClick === null ? null : CarbonImmutable::parse($firstClick), now());
        $inRange = fn (): Builder => $period->constrain($scoped());

        $series = $period->series($inRange());
        $total = array_sum(array_column($series, 'count'));

        $links = Link::query()
            ->when($user, fn ($query) => $query->whereBelongsTo($user))
            ->when($scopeToActor, fn ($query) => $query->visibleTo($actor));
        $linkCount = (clone $links)->count();
        $activeLinks = (clone $links)->active()->count();

        $topLinks = $inRange()
            ->select('links.id', 'links.title', 'links.code')->selectRaw('sum(link_statistics.clicks) as clicks')
            ->groupBy('links.id', 'links.title', 'links.code')
            ->orderByDesc('clicks')->limit(self::TOP_LIMIT)->get()
            ->map(fn ($row): array => ['id' => $row->id, 'title' => $row->title, 'code' => $row->code, 'clicks' => (int) $row->clicks])
            ->all();

        $topUsers = $user !== null ? [] : $inRange()
            ->join('users', 'users.id', '=', 'links.user_id')
            ->select('users.id', 'users.name', 'users.email')->selectRaw('sum(link_statistics.clicks) as clicks')
            ->groupBy('users.id', 'users.name', 'users.email')
            ->orderByDesc('clicks')->limit(self::TOP_LIMIT)->get()
            ->map(fn ($row): array => ['id' => $row->id, 'name' => $row->name, 'email' => $row->email, 'clicks' => (int) $row->clicks])
            ->all();

        return [
            'period' => $request->period(),
            'granularity' => $period->granularity,
            'total' => $total,
            'series' => $series,
            'counters' => ($user === null ? ['users' => User::visibleTo($actor)->count()] : [])
                + ['links' => $linkCount, 'activeLinks' => $activeLinks, 'disabledLinks' => $linkCount - $activeLinks, 'clicks' => $total],
            'topLinks' => array_values($topLinks),
            'topUsers' => array_values($topUsers),
        ];
    }
}
