<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminStatsRequest;
use App\Models\Link;
use App\Support\LinkStatsReport;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class LinkStatsController extends Controller
{
    public function __construct(private readonly LinkStatsReport $report) {}

    /**
     * Show click statistics of any user's link.
     */
    public function __invoke(AdminStatsRequest $request, Link $link): Response
    {
        return Inertia::render('links/Stats', [
            'link' => $link,
            ...$this->report->build($link, $request),
            'dataUrl' => route('admin.links.stats.data', $link),
            'backUrl' => route('admin.users.show', $link->user_id),
        ]);
    }

    /**
     * Stats payload as JSON, limited to the requested sections.
     */
    public function data(AdminStatsRequest $request, Link $link): JsonResponse
    {
        return response()->json($this->report->build($link, $request, $request->sections()));
    }
}
