<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Inventory\ProjectController;
use App\Http\Controllers\Inventory\BlockController;
use App\Http\Controllers\Inventory\UnitController;
use App\Http\Controllers\Inventory\UnitCategoryController;
use App\Http\Controllers\Clients\ClientsController;
use App\Http\Controllers\Clients\NomineesController;
use App\Http\Controllers\Bookings\BookingsController;
use App\Http\Controllers\Bookings\AdjustmentsController;
use App\Http\Controllers\Payments\PaymentsController;
use App\Http\Controllers\Statements\StatementsController;
use App\Http\Controllers\Notifications\NotificationsController;
use App\Http\Controllers\Reconciliation\ReconciliationController;
use App\Http\Controllers\Reports\ReportsController;
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

    // Clients
    Route::resource('clients', ClientsController::class);
    Route::post('clients/{client}/nominees', [NomineesController::class, 'store'])->name('clients.nominees.store');
    Route::put('clients/{client}/nominees/{nominee}', [NomineesController::class, 'update'])->name('clients.nominees.update');
    Route::delete('clients/{client}/nominees/{nominee}', [NomineesController::class, 'destroy'])->name('clients.nominees.destroy');

    // Bookings + nested adjustments + statement
    Route::resource('bookings', BookingsController::class)->except(['edit', 'update']);
    Route::post('bookings/{booking}/adjustments', [AdjustmentsController::class, 'store'])->name('bookings.adjustments.store');
    Route::delete('bookings/{booking}/adjustments/{adjustment}', [AdjustmentsController::class, 'destroy'])->name('bookings.adjustments.destroy');
    Route::get('bookings/{booking}/statement', [StatementsController::class, 'show'])->name('bookings.statement');
    Route::get('bookings/{booking}/statement.pdf', [StatementsController::class, 'download'])->name('bookings.statement.pdf');

    // Payments (no edit — payments are reversed via DELETE, never edited)
    Route::resource('payments', PaymentsController::class)->except(['edit', 'update']);

    // Notifications
    Route::get('notifications', [NotificationsController::class, 'index'])->name('notifications.index');
    Route::get('notifications/{notificationLog}', [NotificationsController::class, 'show'])->name('notifications.show');
    Route::post('notifications/send-for-schedule', [NotificationsController::class, 'sendForSchedule'])->name('notifications.send-for-schedule');

    // Reconciliation
    Route::get('reconciliation', [ReconciliationController::class, 'index'])->name('reconciliation.index');
    Route::post('reconciliation/upload', [ReconciliationController::class, 'upload'])->name('reconciliation.upload');
    Route::get('reconciliation/suggested-bookings', [ReconciliationController::class, 'suggestedBookings'])->name('reconciliation.suggested-bookings');
    Route::get('reconciliation/{import}', [ReconciliationController::class, 'show'])->name('reconciliation.show');
    Route::post('reconciliation/lines/{line}/confirm', [ReconciliationController::class, 'confirm'])->name('reconciliation.confirm');
    Route::post('reconciliation/lines/{line}/ignore', [ReconciliationController::class, 'ignore'])->name('reconciliation.ignore');

    // Reports
    Route::get('reports', fn () => redirect()->route('reports.collections'))->name('reports.index');
    Route::get('reports/collections', [ReportsController::class, 'collections'])->name('reports.collections');
});

// Healthcheck (no auth) — for uptime monitors
Route::get('/up', fn () => response('OK', 200));
