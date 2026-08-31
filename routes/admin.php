<?php

use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\DealerController;
use App\Http\Controllers\Admin\ManagerController;
use App\Http\Controllers\Admin\SalesmanController;
use App\Http\Controllers\Admin\VehicleController;
use App\Http\Controllers\Admin\DealerVehicleAllocationController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', 'role:admin'])
    ->group(function (): void {
        Route::view('/dashboard', 'pages.dashboard')->name('dashboard');
        Route::get('/access-test', fn () => 'Admin access granted')
            ->name('access-test');
    });

Route::middleware(['auth', 'role:admin'])->group(function (): void {
    Route::resource('customers', CustomerController::class);
    Route::get('/managers/create', [ManagerController::class, 'create'])->name('managers.create');
    Route::post('/managers', [ManagerController::class, 'store'])->name('managers.store');
    Route::view('/conversations', 'pages.conversations')->name('conversations');
    Route::view('/bookings', 'pages.bookings.index')->name('bookings.index');
    Route::view('/bookings/{id}', 'pages.bookings.show')->name('bookings.show');
    Route::view('/calendar', 'pages.calendar')->name('calendar');
    Route::get('/inventory', [VehicleController::class, 'index'])->name('inventory');
    Route::view('/reports', 'pages.reports')->name('reports');
    Route::view('/integrations', 'pages.integrations')->name('integrations');
    Route::view('/settings', 'pages.settings')->name('settings');
    Route::patch('/dealers/{dealer}/status', [DealerController::class, 'updateStatus'])
        ->name('dealers.status');
    Route::post('/dealers/{dealer}/users', [DealerController::class, 'assignUser'])
        ->name('dealers.users.assign');
    Route::delete('/dealers/{dealer}/users/{user}', [DealerController::class, 'unassignUser'])
        ->name('dealers.users.unassign');
    Route::post('/dealers/{dealer}/salesmen', [DealerController::class, 'assignSalesman'])
        ->name('dealers.salesmen.assign');
    Route::delete('/dealers/{dealer}/salesmen/{user}', [DealerController::class, 'unassignSalesman'])
        ->name('dealers.salesmen.unassign');
    Route::resource('dealers', DealerController::class)->except('destroy');
    Route::view('/salesmen', 'pages.salesmen.index')->name('salesmen.index');
    Route::get('/salesmen/create', [SalesmanController::class, 'create'])->name('salesmen.create');
    Route::post('/salesmen', [SalesmanController::class, 'store'])->name('salesmen.store');
    Route::view('/salesmen/{salesman}', 'pages.salesmen.show')->name('salesmen.show');
    Route::resource('vehicles', VehicleController::class);
    Route::post('/vehicles/{vehicle}/demo-allocations', [DealerVehicleAllocationController::class, 'store'])->name('vehicles.demo-allocations.store');
    Route::put('/vehicles/{vehicle}/demo-allocations/{allocation}', [DealerVehicleAllocationController::class, 'update'])->name('vehicles.demo-allocations.update');
    Route::delete('/vehicles/{vehicle}/demo-allocations/{allocation}', [DealerVehicleAllocationController::class, 'destroy'])->name('vehicles.demo-allocations.destroy');
    Route::view('/test-drives', 'pages.calendar')->name('test-drives.index');
    Route::view('/deliveries', 'pages.deliveries')->name('deliveries.index');
});
