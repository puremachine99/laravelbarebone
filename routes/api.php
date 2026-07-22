<?php

use App\Http\Controllers\Api\OpdController;
use App\Http\Controllers\Api\ProvinceController;
use App\Http\Controllers\Api\RegencyController;
use Illuminate\Support\Facades\Route;

Route::get('/provinces', [ProvinceController::class, 'index'])->name('api.provinces.index');
Route::get('/regencies', [RegencyController::class, 'index'])->name('api.regencies.index');
Route::get('/opds', [OpdController::class, 'index'])->name('api.opds.index');
