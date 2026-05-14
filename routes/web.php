<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Inventory\ProjectController;
use App\Http\Controllers\Inventory\BlockController;
use App\Http\Controllers\Inventory\UnitController;
use App\Http\Controllers\Inventory\UnitCategoryController;
use Illuminate\Support\Facades\Route;

// Auth
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

// Authenticated app
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('/dashboard', DashboardController::class)->name('dashboard.alias');

    // Inventory
    Route::prefix('inventory')->name('inventory.')->group(function () {
        Route::resource('projects', ProjectController::class);
        Route::resource('projects.blocks', BlockController::class)->shallow();
        Route::resource('unit-categories', UnitCategoryController::class)->parameters([
            'unit-categories' => 'unitCategory',
        ]);
        Route::resource('units', UnitController::class);
    });
});

// Healthcheck (no auth) — for uptime monitors
Route::get('/up', fn () => response('OK', 200));
