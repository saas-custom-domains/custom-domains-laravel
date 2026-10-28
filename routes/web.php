<?php

use App\Http\Controllers\BoardController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\JobApplicationController;
use Illuminate\Support\Facades\Route;

// Hireboard's dashboard, on APP_HOSTS only.
Route::middleware('app-host')->prefix('dashboard')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/boards/{board}/domains', [DashboardController::class, 'store'])->name('domains.store');
    Route::post('/domains/{domain}/check', [DashboardController::class, 'check'])->name('domains.check');
    Route::delete('/domains/{domain}', [DashboardController::class, 'destroy'])->name('domains.destroy');
});

// A company's job board, on its own domain. Route::domain() can't do
// this: the domains live in the database, and behind SaaS Custom Domains
// the router sees your own hostname. ResolveBoard picks the board instead.
Route::middleware('board')->group(function () {
    Route::get('/', [BoardController::class, 'index'])->name('board');
    Route::get('/jobs/{job}', [BoardController::class, 'show'])->whereNumber('job')->name('jobs.show');
    Route::post('/jobs/{job}/apply', [JobApplicationController::class, 'store'])->whereNumber('job')->name('jobs.apply');
});
