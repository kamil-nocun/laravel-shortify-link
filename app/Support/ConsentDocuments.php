<?php

namespace App\Support;

use App\Enums\PageStatus;
use App\Models\Page;
use App\Models\PageVersion;
use App\Models\User;
use App\Models\UserConsent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ConsentDocuments
{
    /**
     * Current versions of the published pages marked as legal documents that have content.
     *
     * @return Collection<int, PageVersion>
     */
    public static function current(): Collection
    {
        return self::currentPages()->get()->map(fn (Page $page): PageVersion => self::versionOf($page))->values();
    }

    private static function versionOf(Page $page): PageVersion
    {
        return $page->currentVersion->setRelation('page', $page);
    }

    /**
     * Published legal pages with content and their current version, in a fixed order (slug descending: terms, privacy policy, cookies policy).
     *
     * @return Builder<Page>
     */
    private static function currentPages(): Builder
    {
        return Page::query()
            ->where('is_legal_document', true)
            ->where('status', PageStatus::Published)
            ->whereNotNull('current_version_id')
            ->with('currentVersion')
            ->orderBy('slug', 'desc');
    }

    /**
     * Current versions the user has not accepted yet, found in one query for the pages plus one for their versions.
     *
     * @return Collection<int, PageVersion>
     */
    public static function pendingFor(User $user): Collection
    {
        return self::currentPages()
            ->whereNotIn('current_version_id', $user->consents()->select('page_version_id'))
            ->get()
            ->map(fn (Page $page): PageVersion => self::versionOf($page))
            ->values();
    }

    /**
     * Record the user's acceptance of the current versions, or of exactly the given versions when the user saw only those
     * (a version published after the form was rendered stays pending). Repeating it changes nothing.
     *
     * @param  list<string>|null  $versionIds
     */
    public static function accept(User $user, ?array $versionIds = null): void
    {
        $versions = $versionIds === null
            ? self::current()
            : PageVersion::query()->whereIn('id', $versionIds)->whereHas('page', fn ($query) => $query->where('is_legal_document', true))->get();

        $rows = $versions->map(fn (PageVersion $version): array => [
            'id' => (string) Str::uuid7(),
            'user_id' => $user->id,
            'page_version_id' => $version->id,
            'accepted_at' => now(),
        ])->all();

        if ($rows !== []) {
            UserConsent::query()->insertOrIgnore($rows);
        }
    }
}
