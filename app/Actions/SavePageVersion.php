<?php

namespace App\Actions;

use App\Models\Page;
use App\Models\PageVersion;
use App\Models\User;
use App\Support\HtmlSanitizer;
use Illuminate\Support\Facades\DB;

class SavePageVersion
{
    public function __construct(private readonly HtmlSanitizer $sanitizer) {}

    /**
     * Save a new version only when the title or the sanitized content differs from the current one; returns null otherwise.
     */
    public function ifChanged(Page $page, string $title, string $body, ?User $author): ?PageVersion
    {
        $current = $page->currentVersion;

        if ($current !== null && $current->title === $title && $current->body === $this->sanitizer->sanitize($body)) {
            return null;
        }

        return $this($page, $title, $body, $author);
    }

    /**
     * Store the content as a new immutable version and make it the page's current one.
     */
    public function __invoke(Page $page, string $title, string $body, ?User $author): PageVersion
    {
        return DB::transaction(function () use ($page, $title, $body, $author) {
            $version = $page->versions()->create([
                'title' => $title,
                'body' => $this->sanitizer->sanitize($body),
                'created_by' => $author?->id,
            ]);

            $page->update(['current_version_id' => $version->id]);

            return $version;
        });
    }
}
