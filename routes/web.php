<?php

use App\Livewire\Ai\AiCenter;
use App\Livewire\Chat\ChatIndex;
use App\Livewire\Dashboard\ExecutiveIndex;
use App\Livewire\Finance\ClearanceArchive;
use App\Livewire\Finance\Disbursements;
use App\Livewire\Finance\FinanceIntakeIndex;
use App\Livewire\Finance\Reconciliation;
use App\Livewire\Pipeline\Config;
use App\Livewire\Talent\Leaderboard;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::middleware('can:view-executive-dashboard')->group(function () {
        Route::get('/executive-dashboard', ExecutiveIndex::class)->name('executive-dashboard');
    });

    Route::middleware('can:manage-talent-okr')->group(function () {
        Route::get('/leaderboard', Leaderboard::class)->name('leaderboard');
    });

    Route::middleware('can:manage-pipeline')->group(function () {
        Route::get('/pipeline-config', Config::class)->name('pipeline-config');
    });

    Route::middleware('can:manage-internal-org')->group(function () {
        Volt::route('/departments', 'master.departments.index')->name('departments.index');
        Volt::route('/positions', 'master.positions.index')->name('positions.index');
        Volt::route('/employees', 'master.employees.index')->name('employees.index');
    });

    Route::middleware('can:manage-partnership')->group(function () {
        Volt::route('/region', 'master.region.index')->name('region.index');
        Volt::route('/opd', 'master.opd.index')->name('opd.index');
        Volt::route('/urusan', 'master.urusan.index')->name('urusan.index');
        Volt::route('/contact', 'master.contact.index')->name('contact.index');
        Volt::route('/contact-positions', 'master.contact-positions.index')->name('contact-positions.index');
    });

    Route::middleware('can:manage-finance-intake')->group(function () {
        Route::get('/finance/intake', FinanceIntakeIndex::class)->name('finance.intake');
    });

    Route::middleware('can:manage-finance-reconciliation')->group(function () {
        Route::get('/finance/reconciliation', Reconciliation::class)->name('finance.reconciliation');
    });

    Route::middleware('can:manage-finance-disbursements')->group(function () {
        Route::get('/finance/disbursements', Disbursements::class)->name('finance.disbursements');
    });

    Route::middleware('can:view-finance-clearance')->group(function () {
        Route::get('/finance/clearance-archive', ClearanceArchive::class)->name('finance.clearance-archive');
    });

    Route::middleware('can:manage-chat')->group(function () {
        Route::get('/chat', ChatIndex::class)->name('chat');
    });

    Route::middleware('can:manage-ai-center')->group(function () {
        Route::get('/ai-center', AiCenter::class)->name('ai-center');
    });

    Volt::route('/products', 'master.products.index')->name('products.index')->middleware('can:manage-products');
    Route::view('/investors', 'pages.master.investors.index')->name('investors.index')->middleware('can:manage-investors');
});

require __DIR__.'/settings.php';
