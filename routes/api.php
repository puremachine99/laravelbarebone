<?php

use App\Http\Controllers\Api\AiDataController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\OpdController;
use App\Http\Controllers\Api\OpdTypeController;
use App\Http\Controllers\Api\ProvinceController;
use App\Http\Controllers\Api\RegencyController;
use App\Http\Controllers\Api\RegionController;
use App\Http\Controllers\Api\SsoUserController;
use Illuminate\Support\Facades\Route;

// Public, read-only
Route::middleware('throttle:public-api')->group(function () {
    Route::get('/opd-types', [OpdTypeController::class, 'index'])->name('api.opd-types.index');
    Route::get('/regions', [RegionController::class, 'index'])->name('api.regions.index');
    Route::get('/provinces', [ProvinceController::class, 'index'])->name('api.provinces.index');
    Route::get('/regencies', [RegencyController::class, 'index'])->name('api.regencies.index');
    Route::get('/opds', [OpdController::class, 'index'])->name('api.opds.index');
    Route::get('/opds/search', [OpdController::class, 'search'])->name('api.opds.search');
});

// Data API
Route::prefix('ai')->name('api.ai.')->middleware(['api.token', 'ai.assertion'])->group(function () {
    Route::get('/datasets', [AiDataController::class, 'datasets'])->middleware('throttle:120,1')->name('datasets');
    Route::post('/query', [AiDataController::class, 'query'])->middleware('throttle:ai-data')->name('query');
});

// Internal employee directory (service token only, no user assertion)
Route::prefix('employees')->name('api.employees.')->middleware(['api.token', 'throttle:120,1'])->group(function () {
    Route::get('/', [EmployeeController::class, 'index'])->name('index');
    Route::get('/{employee}', [EmployeeController::class, 'show'])->whereNumber('employee')->name('show');
});

// SSO: profile of the user behind a Passport token issued to a connected app (/tech/sso)
Route::get('/me', SsoUserController::class)->middleware(['auth:api', 'throttle:120,1'])->name('api.sso.me');
