<?php

namespace App\Support;

use App\Enums\PageStatus;
use App\Models\Page;
use App\Models\PageVersion;
use App\Models\User;
use App\Models\UserConsent;
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
        return Page::query()
            ->where('is_legal_document', true)
            ->where('status', PageStatus::Published)
            ->whereNotNull('current_version_id')
            ->with('currentVersion')
            ->orderBy('slug', 'desc')
            ->get()
            ->map(fn (Page $page): PageVersion => $page->currentVersion)
            ->values();
    }

    /**
     * Current versions the user has not accepted yet.
     *
     * @return Collection<int, PageVersion>
     */
    public static function pendingFor(User $user): Collection
    {
        $accepted = $user->consents()->pluck('page_version_id');

        return self::current()->reject(fn (PageVersion $version): bool => $accepted->contains($version->id))->values();
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
