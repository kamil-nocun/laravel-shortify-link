<?php

namespace App\Http\Controllers;

use App\Billing\PackagePageData;
use App\Http\Requests\Admin\AdminStatsRequest;
use App\Support\AdminStatsReport;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Show the signed-in user's own click stats and package.
     */
    public function __invoke(AdminStatsRequest $request, AdminStatsReport $report, PackagePageData $packages): Response
    {
        $user = $request->user();

        return Inertia::render('Dashboard', [
            ...Arr::except($report->build($user, $request), 'topUsers'),
            'package' => $user->isSuperAdmin() ? null : [
                ...Arr::only($packages->current($user), ['package_name', 'link_limit', 'domain_limit', 'domains_used', 'billing_period', 'expires_at']),
                'links_used' => $user->links()->count(),
            ],
        ]);
    }
}
