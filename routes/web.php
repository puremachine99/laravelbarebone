<?php

use App\Http\Controllers\Debug\ErrorLogController;
use App\Livewire\Admin\RoleManagement;
use App\Livewire\Admin\UserManagement;
use App\Livewire\Ai\AiCenter;
use App\Livewire\Chat\ChatIndex;
use App\Livewire\Dashboard\ExecutiveIndex;
use App\Livewire\Finance\ClearanceArchive;
use App\Livewire\Finance\Disbursements;
use App\Livewire\Finance\FinanceIntakeIndex;
use App\Livewire\Finance\Reconciliation;
use App\Livewire\Talent\Leaderboard;
use App\Services\ErrorTracker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::redirect('/', '/login')->name('home');

Route::view('/tech', 'pages.tech')->name('tech');

Route::view('/documentation', 'pages.documentation')->name('documentation');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Volt::route('/profile', 'profile.index')->name('profile.show');

    Route::livewire('/profile/security', 'profile.security-settings')
        ->middleware('password.confirm')
        ->name('profile.security');

    Volt::route('/notifications', 'notifications.index')->name('notifications.index');

    Route::middleware('can:view-executive-dashboard')->group(function () {
        Route::get('/executive-dashboard', ExecutiveIndex::class)->name('executive-dashboard');
    });

    Route::middleware('can:manage-talent-okr')->group(function () {
        Route::get('/leaderboard', Leaderboard::class)->name('leaderboard');
    });

    Route::middleware('can:access-partnership')->group(function () {
        Volt::route('/partnership/dashboard', 'partnership.dashboard-index')->name('partnership.dashboard');
        Volt::route('/partnership/contact', 'partnership.contact-index')->name('contact.index');
        Volt::route('/partnership/pitching', 'partnership.pitching-index')->name('partnership.pitching');
        Volt::route('/partnership/realisasi', 'partnership.realisasi-index')->name('partnership.realisasi');
    });

    Route::middleware('can:access-internal-org')->group(function () {
        Volt::route('/departments', 'master.departments.index')->name('departments.index');
        Volt::route('/positions', 'master.positions.index')->name('positions.index');
        Volt::route('/employees', 'master.employees.index')->name('employees.index');
        Route::get('/admin/users', UserManagement::class)->name('admin.users');
        Route::get('/admin/roles', RoleManagement::class)->name('admin.roles');
    });

    Route::middleware('can:access-partnership')->group(function () {
        Volt::route('/region', 'master.region.index')->name('region.index');
        Volt::route('/opd', 'master.opd.index')->name('opd.index');
        Volt::route('/urusan', 'master.urusan.index')->name('urusan.index');
        Volt::route('/contact-positions', 'master.contact-positions.index')->name('contact-positions.index');
        Volt::route('/partnership/pmo', 'partnership.pmo-index')->name('partnership-employees.index');
    });

    Route::middleware('can:access-finance')->group(function () {
        Route::get('/finance/intake', FinanceIntakeIndex::class)->name('finance.intake');
        Route::get('/finance/reconciliation', Reconciliation::class)->name('finance.reconciliation');
        Route::get('/finance/disbursements', Disbursements::class)->name('finance.disbursements');
        Route::get('/finance/clearance-archive', ClearanceArchive::class)->name('finance.clearance-archive');
    });

    Route::middleware('can:manage-chat')->group(function () {
        Route::get('/chat', ChatIndex::class)->name('chat');
    });

    Route::middleware('can:manage-ai-center')->group(function () {
        Route::get('/ai-center', AiCenter::class)->name('ai-center');
    });

    Volt::route('/partnership/test/pitching', 'partnership.test-pitching-compare')->name('partnership.test.pitching');

    Volt::route('/products', 'master.products.index')->name('products.index')->middleware('can:access-products');

    Route::middleware('can:access-investors')->group(function () {
        Volt::route('/investors', 'investors.dashboard-index')->name('investors.index');
        Volt::route('/investors/monthly', 'investors.monthly-index')->name('investors.monthly');
        Volt::route('/investors/conversion', 'investors.conversion-index')->name('investors.conversion');
        Volt::route('/investors/contacts', 'investors.contact-index')->name('investors.contacts');
        Volt::route('/investors/funnel', 'investors.funnel-index')->name('investors.funnel');
        Volt::route('/investors/management-fo', 'investors.management-fo')->name('investors.management-fo');
    });
});

// ─── Custom Error Tracker (dev-only debug panel, session-gated, not tied to app auth) ───
Route::prefix('dev')->name('dev.')->group(function () {
    Route::get('/login', [ErrorLogController::class, 'loginForm'])->name('login');
    Route::post('/login', [ErrorLogController::class, 'login'])->name('login.store');
    Route::get('/logout', [ErrorLogController::class, 'logout'])->name('logout');

    Route::prefix('errors')->name('errors.')->group(function () {
        Route::get('/', [ErrorLogController::class, 'index'])->name('index');
        Route::post('/purge', [ErrorLogController::class, 'purge'])->name('purge');
        Route::get('/{code}', [ErrorLogController::class, 'show'])->name('show');
        Route::post('/{id}/resolve', [ErrorLogController::class, 'resolve'])->name('resolve');
        Route::delete('/{id}', [ErrorLogController::class, 'destroy'])->name('destroy');
    });
});

// Public intake for browser-side JS errors (e.g. window.onerror) — no session guard,
// since it may fire before the visitor is authenticated.
Route::post('/api/error-track', function (Request $request) {
    ErrorTracker::captureFromBrowser($request->all(), $request);

    return response()->json(['ok' => true]);
})->name('api.error-track');
