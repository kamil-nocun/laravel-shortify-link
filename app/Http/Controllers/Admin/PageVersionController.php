<?php

namespace App\Http\Controllers\Admin;

use App\Actions\SavePageVersion;
use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\PageVersion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PageVersionController extends Controller
{
    /**
     * List the versions of a page, newest first.
     */
    public function index(Page $page): Response
    {
        Gate::authorize('update', $page);

        return Inertia::render('admin/pages/Versions', [
            'page' => ['id' => $page->id, 'slug' => $page->slug, 'status' => $page->status->value],
            'versions' => $page->versions()->with('author:id,name')->latest('created_at')->latest('id')->get()->map(fn (PageVersion $version) => [
                'id' => $version->id,
                'title' => $version->title,
                'created_at' => $version->created_at,
                'author_name' => $version->author?->name,
                'is_current' => $version->id === $page->current_version_id,
            ]),
        ]);
    }

    /**
     * Show a stored version.
     */
    public function show(Page $page, PageVersion $version): Response
    {
        Gate::authorize('update', $page);

        return Inertia::render('admin/pages/VersionShow', [
            'page' => ['id' => $page->id, 'slug' => $page->slug, 'status' => $page->status->value],
            'version' => $version->only(['id', 'title', 'body', 'created_at']),
        ]);
    }

    /**
     * Restore a version by saving its content as a new version.
     */
    public function restore(Request $request, Page $page, PageVersion $version, SavePageVersion $savePageVersion): RedirectResponse
    {
        Gate::authorize('update', $page);

        if ($version->id !== $page->current_version_id) {
            $savePageVersion($page, $version->title, $version->body, $request->user());
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Version restored.')]);

        return to_route('admin.pages.edit', $page);
    }
}
