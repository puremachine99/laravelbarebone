<?php

use App\Http\Controllers\AiCenter\StreamMessageController;
use App\Http\Controllers\Auth\SetPasswordController;
use App\Http\Controllers\CsvExportController;
use App\Http\Controllers\Debug\ErrorLogController;
use App\Livewire\Admin\RoleManagement;
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

Route::view('/documentation', 'pages.documentation')->name('documentation');

Route::get('/set-password/{token}', [SetPasswordController::class, 'create'])->name('password.setup');
Route::post('/set-password/{token}', [SetPasswordController::class, 'store'])->name('password.setup.store');

Route::middleware(['auth', 'verified', 'active'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Volt::route('/profile', 'profile.index')->name('profile.show');

    Route::livewire('/profile/security', 'profile.security-settings')
        ->middleware('password.confirm')
        ->name('profile.security');

    Volt::route('/notifications', 'notifications.index')->name('notifications.index');

    Route::get('/export/csv/{slug}', CsvExportController::class)->name('csv-export.download');

    Volt::route('/users/{user}', 'users.show')->name('users.show');

    Route::middleware('can:view-executive-dashboard')->group(function () {
        Route::get('/executive-dashboard', ExecutiveIndex::class)->name('executive-dashboard');
    });

    Route::middleware('can:manage-talent-okr')->group(function () {
        Route::get('/leaderboard', Leaderboard::class)->name('leaderboard');
    });

    Route::middleware('can:access-partnership')->group(function () {
        Volt::route('/partnership/dashboard', 'partnership.dashboard-index')->name('partnership.dashboard')->middleware('can:partnership-dashboard.view');
        Volt::route('/partnership/contact', 'partnership.contact-index')->name('contact.index')->middleware('can:partnership-contacts.view');
        Volt::route('/partnership/pitching', 'partnership.pitching-index')->name('partnership.pitching')->middleware('can:partnership-pitching.view');
        Volt::route('/partnership/realisasi', 'partnership.realisasi-index')->name('partnership.realisasi')->middleware('can:partnership-realisasi.view');
    });

    Route::middleware('can:access-internal-org')->group(function () {
        Volt::route('/departments', 'master.departments.index')->name('departments.index')->middleware('can:departments.view');
        Volt::route('/positions', 'master.positions.index')->name('positions.index')->middleware('can:positions.view');
        Volt::route('/employees', 'master.employees.index')->name('employees.index')->middleware('can:employees.view');
        Route::redirect('/admin/users', '/employees')->name('admin.users');
        Route::get('/admin/roles', RoleManagement::class)->name('admin.roles');
    });

    Route::middleware('can:access-partnership')->group(function () {
        Volt::route('/region', 'master.region.index')->name('region.index')->middleware('can:region.view');
        Volt::route('/opd', 'master.opd.index')->name('opd.index')->middleware('can:opd.view');
        Volt::route('/urusan', 'master.urusan.index')->name('urusan.index')->middleware('can:urusan.view');
        Volt::route('/contact-positions', 'master.contact-positions.index')->name('contact-positions.index')->middleware('can:contact-positions.view');
        Volt::route('/partnership/pmo', 'partnership.pmo-index')->name('partnership-employees.index')->middleware('can:partnership-pmo.view');
        Volt::route('/partnership/data-coverage', 'partnership.data-coverage-index')->name('partnership.data-coverage')->middleware('can:partnership-data-coverage.view');
        Volt::route('/partnership/activity-log', 'partnership.activity-log-index')->name('partnership.activity-log')->middleware('can:partnership-activity-log.view');
    });

    Route::middleware('can:access-finance')->group(function () {
        Route::get('/finance/intake', FinanceIntakeIndex::class)->name('finance.intake')->middleware('can:finance-intake.view');
        Route::get('/finance/reconciliation', Reconciliation::class)->name('finance.reconciliation')->middleware('can:finance-reconciliation.view');
        Route::get('/finance/disbursements', Disbursements::class)->name('finance.disbursements')->middleware('can:finance-disbursements.view');
        Route::get('/finance/clearance-archive', ClearanceArchive::class)->name('finance.clearance-archive')->middleware('can:finance-clearance-archive.view');
    });

    Route::middleware('can:manage-chat')->group(function () {
        Route::get('/chat', ChatIndex::class)->name('chat');
    });

    Route::middleware('can:manage-ai-center')->group(function () {
        Route::get('/ai-center', AiCenter::class)->name('ai-center');
        Route::post('/ai-center/messages', StreamMessageController::class)->name('ai-center.messages.stream');
    });

    Volt::route('/partnership/test/pitching', 'partnership.test-pitching-compare')->name('partnership.test.pitching');

    Volt::route('/products', 'master.products.index')->name('products.index')->middleware(['can:access-products', 'can:products.view']);

    Route::middleware('can:access-investors')->group(function () {
        Volt::route('/investors', 'investors.dashboard-index')->name('investors.index')->middleware('can:investors-dashboard.view');
        Volt::route('/investors/monthly', 'investors.monthly-index')->name('investors.monthly')->middleware('can:investors-monthly.view');
        Volt::route('/investors/conversion', 'investors.conversion-index')->name('investors.conversion')->middleware('can:investors-conversion.view');
        Volt::route('/investors/contacts', 'investors.contact-index')->name('investors.contacts')->middleware('can:fo-contacts.view');
        Volt::route('/investors/funnel', 'investors.funnel-index')->name('investors.funnel')->middleware('can:fo-funnel.view');
        Volt::route('/investors/management-fo', 'investors.management-fo')->name('investors.management-fo')->middleware('can:fo-targets.view');
        Volt::route('/investors/docs', 'investors.docs-index')->name('investors.docs')->middleware('can:investor-documents.view');
        Volt::route('/investors/activity-log', 'investors.activity-log-index')->name('investors.activity-log')->middleware('can:investors-activity-log.view');
    });

    Route::prefix('tech')->name('tech.')->middleware('can:access-technology')->group(function () {
        Route::view('/timeline-calc', 'pages.tech')->name('timeline-calc')->middleware('can:tech-timeline-calc.view');
    });
});

// ─── Sentry: error tracker + force login (Super Admin only) ───
// Anyone who isn't an active Super Admin gets a 404, not a redirect/403 —
// so the existence of these routes isn't revealed to unauthorized visitors.
Route::middleware('dev.super-admin')->prefix('dev')->group(function () {
    // Old dev panel URLs, kept so existing bookmarks still land on Sentry.
    Route::permanentRedirect('/errors', '/tech/sentry');
    Route::permanentRedirect('/login', '/tech/sentry');
    Route::permanentRedirect('/force-login', '/tech/sentry?tab=force-login');
});

Route::middleware('dev.super-admin')->prefix('tech/sentry')->name('dev.errors.')->group(function () {
    Route::get('/', [ErrorLogController::class, 'index'])->name('index');
    Route::post('/purge', [ErrorLogController::class, 'purge'])->name('purge');
    Route::get('/{code}', [ErrorLogController::class, 'show'])->name('show');
    Route::post('/{id}/resolve', [ErrorLogController::class, 'resolve'])->name('resolve');
    Route::delete('/{id}', [ErrorLogController::class, 'destroy'])->name('destroy');
});

// Public intake for browser-side JS errors (e.g. window.onerror) — no session guard,
// since it may fire before the visitor is authenticated.
Route::post('/api/error-track', function (Request $request) {
    ErrorTracker::captureFromBrowser($request->all(), $request);

    return response()->json(['ok' => true]);
})->name('api.error-track');
