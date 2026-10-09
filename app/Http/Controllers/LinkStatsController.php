<?php

namespace App\Http\Controllers;

use App\Http\Requests\Links\LinkStatsRequest;
use App\Models\Link;
use App\Support\LinkStatsReport;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class LinkStatsController extends Controller
{
    public function __construct(private readonly LinkStatsReport $report) {}

    /**
     * Show click statistics of a link for the requested period.
     */
    public function __invoke(LinkStatsRequest $request, Link $link): Response
    {
        return Inertia::render('links/Stats', ['link' => $link, ...$this->report->build($link, $request)]);
    }

    /**
     * Stats payload as JSON, limited to the requested sections.
     */
    public function data(LinkStatsRequest $request, Link $link): JsonResponse
    {
        return response()->json($this->report->build($link, $request, $request->sections()));
    }
}
