<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminStatsRequest;
use App\Support\AdminStatsReport;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Show system-wide counters, click series and top lists.
     */
    public function __invoke(AdminStatsRequest $request, AdminStatsReport $report): Response
    {
        return Inertia::render('admin/Dashboard', $report->build(null, $request));
    }
}
