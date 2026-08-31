<?php

use App\Http\Controllers\Dealer\CustomerController;
use App\Http\Controllers\Dealer\SalesmanController;
use App\Http\Controllers\Dealer\VehicleController;
use Illuminate\Support\Facades\Route;

Route::prefix('dealer')
    ->name('dealer.')
    ->middleware(['auth', 'role:dealer'])
    ->group(function (): void {
        Route::view('/dashboard', 'pages.dealer.dashboard')->name('dashboard');
        Route::get('/customers', [CustomerController::class, 'index'])
            ->middleware('manager.dealer')
            ->name('customers.index');
        Route::get('/customers/{customer}', [CustomerController::class, 'show'])
            ->middleware('manager.dealer')
            ->name('customers.show');
        Route::get('/salesmen/create', [SalesmanController::class, 'create'])
            ->middleware('manager.dealer')
            ->name('salesmen.create');
        Route::post('/salesmen', [SalesmanController::class, 'store'])
            ->middleware('manager.dealer')
            ->name('salesmen.store');
        Route::get('/salesmen', [SalesmanController::class, 'index'])
            ->middleware('manager.dealer')
            ->name('salesmen.index');
        Route::get('/salesmen/{user}', [SalesmanController::class, 'show'])
            ->middleware('manager.dealer')
            ->name('salesmen.show');
        Route::view('/conversations', 'pages.conversations')->name('conversations');
        Route::view('/bookings', 'pages.dealer.bookings')->name('bookings');
        Route::view('/calendar', 'pages.calendar')->name('calendar');
        Route::get('/inventory', [VehicleController::class, 'index'])
            ->middleware('manager.dealer')
            ->name('inventory');
        Route::view('/performance', 'pages.dealer.performance')->name('performance');
        Route::view('/profile', 'pages.dealer.profile')->name('profile');
        Route::get('/team', [SalesmanController::class, 'index'])
            ->middleware('manager.dealer')
            ->name('team');
        Route::view('/team/{salesman}', 'pages.salesmen.show')->name('team.show');
        Route::view('/test-drives', 'pages.calendar')->name('test-drives');
        Route::view('/chassis', 'pages.dealer.chassis')->name('chassis');
        Route::view('/deliveries', 'pages.dealer.deliveries')->name('deliveries');
        Route::view('/reports', 'pages.dealer.performance')->name('reports');
        Route::get('/access-test', fn () => 'Dealer access granted')
            ->name('access-test');
    });
