<?php

namespace App\Http\Controllers;

use App\Enums\PageStatus;
use App\Models\Page;
use App\Support\PublicPageData;
use Inertia\Inertia;
use Inertia\Response;

class ContentPageController extends Controller
{
    /**
     * Show the current version of a published CMS page.
     */
    public function __invoke(string $slug): Response
    {
        $page = Page::where('slug', $slug)->where('status', PageStatus::Published)->with('currentVersion')->firstOrFail();
        $version = $page->currentVersion ?? abort(404);

        return Inertia::render('content/Show', [
            'page' => ['title' => $version->title, 'body' => $version->body],
            'legalDocuments' => PublicPageData::legalDocuments(),
        ]);
    }
}
