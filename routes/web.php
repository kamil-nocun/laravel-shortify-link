<?php

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

require __DIR__.'/settings.php';

Route::get('{code}', RedirectLinkController::class)->where('code', '[0-9A-Za-z]+')->name('links.redirect');
