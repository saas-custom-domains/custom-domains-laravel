<?php

use App\Http\Controllers\BoardController as Board;
use App\Http\Controllers\DashboardController as Dashboard;
use App\Http\Controllers\JobApplicationController as Apply;
use Illuminate\Support\Facades\Route;

// Hireboard's dashboard, on APP_HOSTS only.
Route::middleware('app-host')->prefix('dashboard')->group(function () {
    Route::get('/', [Dashboard::class, 'index'])
        ->name('dashboard');
    Route::post('/boards/{board}/domains', [Dashboard::class, 'store'])
        ->name('domains.store');
    Route::post('/domains/{domain}/check', [Dashboard::class, 'check'])
        ->name('domains.check');
    Route::delete('/domains/{domain}', [Dashboard::class, 'destroy'])
        ->name('domains.destroy');
});

// A company's job board, on its own domain. Route::domain() can't do
// this: the domains live in the database, and behind SaaS Custom
// Domains the router sees your own hostname.
Route::middleware('board')->group(function () {
    Route::get('/', [Board::class, 'index'])
        ->name('board');
    Route::get('/jobs/{job}', [Board::class, 'show'])
        ->whereNumber('job')->name('jobs.show');
    Route::post('/jobs/{job}/apply', [Apply::class, 'store'])
        ->whereNumber('job')->name('jobs.apply');
});
