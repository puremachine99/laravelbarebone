<?php

use App\Http\Controllers\Auth\SetPasswordController;
use App\Http\Controllers\CsvExportController;
use App\Http\Controllers\Debug\ErrorLogController;
use App\Http\Controllers\Profile\AppearanceController;
use App\Livewire\Admin\RoleManagement;
use App\Livewire\Tech\UiKit;
use App\Services\ErrorTracker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::redirect('/', '/login')->name('home');

Route::get('/set-password/{token}', [SetPasswordController::class, 'create'])->name('password.setup');
Route::post('/set-password/{token}', [SetPasswordController::class, 'store'])->name('password.setup.store');

Route::middleware(['auth', 'verified', 'active'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Volt::route('/profile', 'profile.index')->name('profile.show');
    Route::put('/profile/appearance', AppearanceController::class)->name('profile.appearance.update');

    Route::livewire('/profile/security', 'profile.security-settings')
        ->middleware('password.confirm')
        ->name('profile.security');

    Volt::route('/notifications', 'notifications.index')->name('notifications.index');

    Route::get('/export/csv/{slug}', CsvExportController::class)->name('csv-export.download');

    Volt::route('/users/{user}', 'users.show')->name('users.show');

    Route::middleware('can:access-internal-org')->group(function () {
        Volt::route('/departments', 'master.departments.index')->name('departments.index')->middleware('can:departments.view');
        Volt::route('/positions', 'master.positions.index')->name('positions.index')->middleware('can:positions.view');
        Volt::route('/employees', 'master.employees.index')->name('employees.index')->middleware('can:employees.view');
        Route::redirect('/admin/users', '/employees')->name('admin.users');
        Route::get('/admin/roles', RoleManagement::class)->name('admin.roles');
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

    // Demo of every notification (toast, popup, confirm), to check the look per theme.
    Route::get('/ui-kit', UiKit::class)->name('dev.ui-kit');
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
