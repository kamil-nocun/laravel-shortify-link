<?php

use App\Http\Controllers\LinkController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'FrontApp')->name('front_app');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
    Route::resource('links', LinkController::class)->except('show');
});

require __DIR__.'/settings.php';
