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
