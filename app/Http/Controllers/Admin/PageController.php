<?php

namespace App\Http\Controllers\Admin;

use App\Actions\SavePageVersion;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePageRequest;
use App\Http\Requests\Admin\UpdatePageRequest;
use App\Models\Page;
use App\Support\HtmlSanitizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PageController extends Controller
{
    public function __construct(private readonly SavePageVersion $savePageVersion) {}

    /**
     * List pages with the title of their current version.
     */
    public function index(): Response
    {
        Gate::authorize('viewAny', Page::class);

        return Inertia::render('admin/pages/Index', [
            'pages' => Page::with('currentVersion:id,title')->orderBy('slug')->get()->map(fn (Page $page) => [
                'id' => $page->id,
                'slug' => $page->slug,
                'status' => $page->status->value,
                'is_legal_document' => $page->is_legal_document,
                'title' => $page->currentVersion?->title,
                'updated_at' => $page->updated_at,
            ]),
        ]);
    }

    /**
     * Show the create form.
     */
    public function create(): Response
    {
        Gate::authorize('create', Page::class);

        return Inertia::render('admin/pages/Create');
    }

    /**
     * Store a draft page together with its first version.
     */
    public function store(StorePageRequest $request): RedirectResponse
    {
        $page = Page::create(['slug' => $request->validated('slug'), 'is_legal_document' => $request->boolean('is_legal_document')]);

        ($this->savePageVersion)($page, $request->validated('title'), $request->validated('body'), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Page created.')]);

        return to_route('admin.pages.edit', $page);
    }

    /**
     * Show the edit form with the current version.
     */
    public function edit(Page $page): Response
    {
        Gate::authorize('update', $page);

        return Inertia::render('admin/pages/Edit', [
            'page' => [
                'id' => $page->id,
                'slug' => $page->slug,
                'status' => $page->status->value,
                'is_legal_document' => $page->is_legal_document,
                'title' => $page->currentVersion?->title,
                'body' => $page->currentVersion?->body,
            ],
        ]);
    }

    /**
     * Update the slug and, when title or content changed, save a new version.
     */
    public function update(UpdatePageRequest $request, Page $page, HtmlSanitizer $sanitizer): RedirectResponse
    {
        $page->update(['slug' => $request->validated('slug'), 'is_legal_document' => $request->boolean('is_legal_document')]);

        $current = $page->currentVersion;
        $title = $request->validated('title');
        $body = $request->validated('body');

        if ($current === null || $current->title !== $title || $current->body !== $sanitizer->sanitize($body)) {
            ($this->savePageVersion)($page, $title, $body, $request->user());
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Page updated.')]);

        return to_route('admin.pages.edit', $page);
    }
}
