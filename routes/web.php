<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Volt::route('/departments', 'master.departments.index')->name('departments.index');
    Volt::route('/positions', 'master.positions.index')->name('positions.index');
    Volt::route('/employees', 'master.employees.index')->name('employees.index');
    Volt::route('/region', 'master.region.index')->name('region.index');

    Volt::route('/opd', 'master.opd.index')->name('opd.index');
    Volt::route('/urusan', 'master.urusan.index')->name('urusan.index');
    Volt::route('/products', 'master.products.index')->name('products.index');
    Route::view('/contact', 'pages.master.contact.index')->name('contact.index');
    Route::view('/investors', 'pages.master.investors.index')->name('investors.index');
});

require __DIR__.'/settings.php';
