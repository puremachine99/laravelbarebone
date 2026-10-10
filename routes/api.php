<?php

use App\Http\Controllers\Api\AiDataController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\OpdController;
use App\Http\Controllers\Api\ProvinceController;
use App\Http\Controllers\Api\RegencyController;
use Illuminate\Support\Facades\Route;

// Public, read-only
Route::middleware('throttle:public-api')->group(function () {
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
