<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Volt::route('/departments', 'departments.index')->name('departments.index');
    Volt::route('/positions', 'positions.index')->name('positions.index');
    Volt::route('/employees', 'employees.index')->name('employees.index');
    Volt::route('/region', 'region.index')->name('region.index');
});

require __DIR__.'/settings.php';
