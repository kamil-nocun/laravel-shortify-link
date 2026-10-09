<?php

use App\Http\Controllers\Admin\UserBlockController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\UserRoleController;
use App\Http\Controllers\LinkController;
use App\Http\Controllers\LinkStatsController;
use App\Http\Controllers\LinkToggleController;
use App\Http\Controllers\RedirectLinkController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'FrontApp')->name('front_app');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
    Route::get('links/{link}/stats', LinkStatsController::class)->name('links.stats');
    Route::get('links/{link}/stats/data', [LinkStatsController::class, 'data'])->name('links.stats.data');
    Route::patch('links/{link}/toggle', LinkToggleController::class)->name('links.toggle');
    Route::resource('links', LinkController::class)->except('show');
});

Route::middleware(['auth', 'verified', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('users', UserController::class)->only(['index', 'show', 'destroy']);
    Route::patch('users/{user}/block', [UserBlockController::class, 'store'])->name('users.block');
    Route::delete('users/{user}/block', [UserBlockController::class, 'destroy'])->name('users.unblock');
    Route::patch('users/{user}/role', UserRoleController::class)->name('users.role');
});

require __DIR__.'/settings.php';

Route::get('{code}', RedirectLinkController::class)->where('code', '[0-9A-Za-z]+')->name('links.redirect');
