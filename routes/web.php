<?php

use App\Http\Controllers\Admin\AdministratorController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\DomainDisableController;
use App\Http\Controllers\Admin\LinkDisableController;
use App\Http\Controllers\Admin\LinkStatsController as AdminLinkStatsController;
use App\Http\Controllers\Admin\PackageController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserAdministratorController;
use App\Http\Controllers\Admin\UserBlockController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\UserDomainController;
use App\Http\Controllers\Admin\UserLinkController;
use App\Http\Controllers\Admin\UserPackageController;
use App\Http\Controllers\Admin\UserPermissionController;
use App\Http\Controllers\Admin\UserRoleController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DomainCheckController;
use App\Http\Controllers\DomainController;
use App\Http\Controllers\DomainVerifyController;
use App\Http\Controllers\LinkController;
use App\Http\Controllers\LinkQrController;
use App\Http\Controllers\LinkStatsController;
use App\Http\Controllers\LinkToggleController;
use App\Http\Controllers\RedirectLinkController;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

Route::inertia('/', 'FrontApp')->name('front_app');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('links/{link}/stats', LinkStatsController::class)->name('links.stats');
    Route::get('links/{link}/stats/data', [LinkStatsController::class, 'data'])->name('links.stats.data');
    Route::get('links/{link}/qr/{type}', LinkQrController::class)->whereIn('type', ['code', 'alias'])->name('links.qr');
    Route::patch('links/{link}/toggle', LinkToggleController::class)->name('links.toggle');
    Route::resource('links', LinkController::class)->except('show');
    Route::resource('domains', DomainController::class)->only(['index', 'store', 'destroy']);
    Route::post('domains/{domain}/verify', DomainVerifyController::class)->name('domains.verify');
    Route::get('billing/package', [BillingController::class, 'show'])->name('billing.package.show');
    Route::delete('billing/package', [BillingController::class, 'cancel'])->name('billing.package.cancel');
    Route::post('billing/package/checkout', [BillingController::class, 'checkout'])->name('billing.package.checkout');
});

Route::middleware(['auth', 'verified', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', AdminDashboardController::class)->name('dashboard');
    Route::get('links/{link}/stats', AdminLinkStatsController::class)->name('links.stats');
    Route::get('links/{link}/stats/data', [AdminLinkStatsController::class, 'data'])->name('links.stats.data');
    Route::patch('links/{link}/disable', [LinkDisableController::class, 'store'])->name('links.disable');
    Route::delete('links/{link}/disable', [LinkDisableController::class, 'destroy'])->name('links.enable');
    Route::resource('packages', PackageController::class)->except('show');
    Route::resource('roles', RoleController::class)->except('show');
    Route::resource('administrators', AdministratorController::class)->only(['index', 'create', 'store']);
    Route::resource('users', UserController::class)->only(['index', 'create', 'store', 'show', 'edit', 'update', 'destroy']);
    Route::get('users/{user}/links/create', [UserLinkController::class, 'create'])->name('users.links.create');
    Route::post('users/{user}/links', [UserLinkController::class, 'store'])->name('users.links.store');
    Route::post('users/{user}/domains', [UserDomainController::class, 'store'])->name('users.domains.store');
    Route::delete('domains/{domain}', [UserDomainController::class, 'destroy'])->name('domains.destroy');
    Route::patch('domains/{domain}/disable', [DomainDisableController::class, 'store'])->name('domains.disable');
    Route::delete('domains/{domain}/disable', [DomainDisableController::class, 'destroy'])->name('domains.enable');
    Route::patch('users/{user}/block', [UserBlockController::class, 'store'])->name('users.block');
    Route::delete('users/{user}/block', [UserBlockController::class, 'destroy'])->name('users.unblock');
    Route::put('users/{user}/administrators', [UserAdministratorController::class, 'update'])->name('users.administrators.update');
    Route::put('users/{user}/package', [UserPackageController::class, 'update'])->name('users.package.update');
    Route::patch('users/{user}/role', UserRoleController::class)->name('users.role');
    Route::put('users/{user}/permissions', [UserPermissionController::class, 'update'])->name('users.permissions.update');
});

require __DIR__.'/settings.php';

Route::get('internal/domain-check', DomainCheckController::class)->name('domains.check')
    ->withoutMiddleware([
        StartSession::class,
        AddQueuedCookiesToResponse::class,
        ShareErrorsFromSession::class,
        PreventRequestForgery::class,
    ]);

Route::get('{code}', RedirectLinkController::class)->where('code', '[0-9A-Za-z_-]+')->name('links.redirect');
