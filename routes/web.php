<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Inventory\ProjectController;
use App\Http\Controllers\Inventory\BlockController;
use App\Http\Controllers\Inventory\UnitController;
use App\Http\Controllers\Inventory\UnitCategoryController;
use App\Http\Controllers\Clients\ClientsController;
use App\Http\Controllers\Clients\NomineesController;
use App\Http\Controllers\Clients\PortalTokensController;
use App\Http\Controllers\Bookings\BookingsController;
use App\Http\Controllers\Bookings\AdjustmentsController;
use App\Http\Controllers\Bookings\BulkActionsController;
use App\Http\Controllers\Bookings\PossessionLetterController;
use App\Http\Controllers\Payments\PaymentsController;
use App\Http\Controllers\Statements\StatementsController;
use App\Http\Controllers\Notifications\NotificationsController;
use App\Http\Controllers\Reconciliation\ReconciliationController;
use App\Http\Controllers\Reports\ReportsController;
use App\Http\Controllers\Reports\OpeningBalancesController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\UsersController;
use App\Http\Controllers\Admin\RolesController;
use App\Http\Controllers\Admin\AuditController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\ExportsController;
use App\Http\Controllers\Client\ClientPortalController;
use Illuminate\Support\Facades\Route;

// Auth
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

// ============================================================
// Client self-service portal — UNAUTH (signed via opaque token)
// ============================================================
Route::prefix('c')->name('client.')->group(function () {
    Route::get('/{token}', [ClientPortalController::class, 'dashboard'])
        ->name('dashboard')
        ->where('token', '[A-Za-z0-9]{32,64}');
    Route::get('/{token}/bookings/{booking}/statement.pdf', [ClientPortalController::class, 'statement'])
        ->name('statement')
        ->where('token', '[A-Za-z0-9]{32,64}');
});

// ============================================================
// Authenticated app
// ============================================================
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('/dashboard', DashboardController::class)->name('dashboard.alias');

    // Global search (JSON)
    Route::get('/search', [SearchController::class, 'index'])->name('search');

    // ============ Inventory ============
    Route::prefix('inventory')->name('inventory.')->group(function () {
        Route::resource('projects', ProjectController::class);
        Route::get('projects/{project}/grid', [ProjectController::class, 'grid'])->name('projects.grid');
        Route::resource('projects.blocks', BlockController::class)->shallow();
        Route::resource('unit-categories', UnitCategoryController::class)->parameters([
            'unit-categories' => 'unitCategory',
        ]);
        Route::resource('units', UnitController::class);
    });

    // ============ Clients ============
    // Specific routes BEFORE resource (so /clients/export.csv isn't captured as {client})
    Route::get('clients/export.csv', [ExportsController::class, 'clients'])->name('clients.export');
    Route::resource('clients', ClientsController::class);
    Route::post('clients/{client}/nominees', [NomineesController::class, 'store'])->name('clients.nominees.store');
    Route::put('clients/{client}/nominees/{nominee}', [NomineesController::class, 'update'])->name('clients.nominees.update');
    Route::delete('clients/{client}/nominees/{nominee}', [NomineesController::class, 'destroy'])->name('clients.nominees.destroy');
    Route::post('clients/{client}/portal-tokens', [PortalTokensController::class, 'store'])->name('clients.portal-tokens.store');
    Route::delete('clients/{client}/portal-tokens/{token}', [PortalTokensController::class, 'destroy'])->name('clients.portal-tokens.destroy');

    // ============ Bookings ============
    // Specific routes BEFORE resource
    Route::get('bookings/export.csv', [ExportsController::class, 'bookings'])->name('bookings.export');
    Route::post('bookings/bulk/reminders', [BulkActionsController::class, 'reminders'])->name('bookings.bulk.reminders');
    Route::post('bookings/bulk/statements.zip', [BulkActionsController::class, 'statementsZip'])->name('bookings.bulk.statements');
    Route::post('bookings/bulk/reassign', [BulkActionsController::class, 'reassignSalesperson'])->name('bookings.bulk.reassign');
    Route::resource('bookings', BookingsController::class)->except(['edit', 'update']);
    Route::post('bookings/{booking}/adjustments', [AdjustmentsController::class, 'store'])->name('bookings.adjustments.store');
    Route::delete('bookings/{booking}/adjustments/{adjustment}', [AdjustmentsController::class, 'destroy'])->name('bookings.adjustments.destroy');
    Route::get('bookings/{booking}/statement', [StatementsController::class, 'show'])->name('bookings.statement');
    Route::get('bookings/{booking}/statement.pdf', [StatementsController::class, 'download'])->name('bookings.statement.pdf');
    Route::get('bookings/{booking}/possession-letter.pdf', [PossessionLetterController::class, 'show'])->name('bookings.possession-letter');

    // ============ Payments ============
    Route::get('payments/export.csv', [ExportsController::class, 'payments'])->name('payments.export');
    Route::resource('payments', PaymentsController::class)->except(['edit', 'update']);

    // ============ Notifications ============
    Route::get('notifications', [NotificationsController::class, 'index'])->name('notifications.index');
    Route::get('notifications/{notificationLog}', [NotificationsController::class, 'show'])->name('notifications.show');
    Route::post('notifications/send-for-schedule', [NotificationsController::class, 'sendForSchedule'])->name('notifications.send-for-schedule');

    // ============ Reconciliation ============
    Route::get('reconciliation', [ReconciliationController::class, 'index'])->name('reconciliation.index');
    Route::post('reconciliation/upload', [ReconciliationController::class, 'upload'])->name('reconciliation.upload');
    Route::get('reconciliation/suggested-bookings', [ReconciliationController::class, 'suggestedBookings'])->name('reconciliation.suggested-bookings');
    Route::get('reconciliation/{import}', [ReconciliationController::class, 'show'])->name('reconciliation.show');
    Route::post('reconciliation/lines/{line}/confirm', [ReconciliationController::class, 'confirm'])->name('reconciliation.confirm');
    Route::post('reconciliation/lines/{line}/ignore', [ReconciliationController::class, 'ignore'])->name('reconciliation.ignore');

    // ============ Reports ============
    Route::get('reports', fn () => \Inertia\Inertia::render('Reports/Index'))->name('reports.index');
    Route::get('reports/collections', [ReportsController::class, 'collections'])->name('reports.collections');
    Route::get('reports/cash-flow', [ReportsController::class, 'cashFlow'])->name('reports.cash-flow');
    Route::get('reports/booking-summary', [ReportsController::class, 'bookingSummary'])->name('reports.booking-summary');
    Route::get('reports/forecast', [ReportsController::class, 'forecast'])->name('reports.forecast');
    Route::get('reports/possession', [ReportsController::class, 'possession'])->name('reports.possession');
    Route::get('reports/opening-balances', [OpeningBalancesController::class, 'show'])->name('reports.opening-balances');

    // ============ Admin ============
    Route::get('admin', [AdminController::class, 'index'])->name('admin.index');
    Route::resource('admin/users', UsersController::class)->names('admin.users');
    Route::get('admin/roles', [RolesController::class, 'index'])->name('admin.roles');
    Route::get('admin/audit', [AuditController::class, 'index'])->name('admin.audit');
});

// Healthcheck (no auth) — for uptime monitors
Route::get('/up', fn () => response('OK', 200));
