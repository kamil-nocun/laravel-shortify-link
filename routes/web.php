<?php

use Illuminate\Support\Facades\Route;

Route::inertia('/', 'FrontApp')->name('front_app');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
