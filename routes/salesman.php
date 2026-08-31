<?php

use App\Http\Controllers\Salesman\CustomerController;
use App\Http\Controllers\Salesman\BookingController;
use App\Http\Controllers\Salesman\TestDriveController;
use Illuminate\Support\Facades\Route;

Route::prefix('salesman')
    ->name('salesman.')
    ->middleware(['auth', 'role:salesman', 'salesman.dealer'])
    ->group(function (): void {
        Route::view('/dashboard', 'pages.salesman.dashboard')->name('dashboard');
        Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::get('/customers/create', [CustomerController::class, 'create'])->name('customers.create');
        Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store');
        Route::get('/customers/{customer}/edit', [CustomerController::class, 'edit'])->name('customers.edit');
        Route::match(['put', 'patch'], '/customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
        Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])->name('customers.destroy');
        Route::get('/customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
        Route::view('/conversations', 'pages.conversations')->name('conversations');
        Route::get('/test-drives', [TestDriveController::class, 'index'])->name('test-drives.index');
        Route::get('/test-drives-overview', [TestDriveController::class, 'index'])->name('test-drives');
        Route::get('/customers/{customer}/test-drives/create', [TestDriveController::class, 'create'])->name('customers.test-drives.create');
        Route::post('/customers/{customer}/test-drives', [TestDriveController::class, 'store'])->name('customers.test-drives.store');
        Route::get('/test-drives/{testDrive}/edit', [TestDriveController::class, 'edit'])->name('test-drives.edit');
        Route::match(['put', 'patch'], '/test-drives/{testDrive}', [TestDriveController::class, 'update'])->name('test-drives.update');
        Route::get('/test-drives/{testDrive}', [TestDriveController::class, 'show'])->name('test-drives.show');
        Route::get('/calendar', [TestDriveController::class, 'calendar'])->name('calendar');
        Route::post('/calendar/test-drives', [TestDriveController::class, 'storeFromCalendar'])->name('calendar.test-drives.store');
        Route::get('/bookings', [BookingController::class, 'index'])->name('bookings');
        Route::get('/bookings/{booking}', [BookingController::class, 'show'])->name('bookings.show');
        Route::get('/customers/{customer}/test-drives/{testDrive}/book', [BookingController::class, 'create'])->name('customers.test-drives.book.create');
        Route::post('/customers/{customer}/test-drives/{testDrive}/book', [BookingController::class, 'store'])->name('customers.test-drives.book.store');
        Route::view('/chassis', 'pages.salesman.chassis')->name('chassis');
        Route::view('/deliveries', 'pages.salesman.deliveries')->name('deliveries');
        Route::get('/access-test', fn () => 'Salesman access granted')
            ->name('access-test');
    });
