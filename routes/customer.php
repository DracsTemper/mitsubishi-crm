<?php

use Illuminate\Support\Facades\Route;

Route::prefix('customer')
    ->name('customer.')
    ->middleware(['auth', 'role:customer'])
    ->group(function (): void {
        Route::view('/', 'pages.customer.dashboard')->name('home');
        Route::view('/dashboard', 'pages.customer.dashboard')->name('dashboard');
        Route::view('/vehicles', 'pages.customer.vehicles.index')->name('vehicles');
        Route::view('/vehicles/{vehicle}', 'pages.customer.vehicles.show')->name('vehicles.show');
        Route::view('/conversations', 'pages.customer.conversations')->name('conversations');
        Route::view('/conversations/{conversation}', 'pages.customer.conversations')->name('conversations.show');
        Route::view('/test-drives', 'pages.customer.test-drives')->name('test-drives');
        Route::view('/test-drives/book', 'pages.customer.test-drives')->name('test-drives.book');
        Route::view('/test-drives/confirmed', 'pages.customer.test-drive-confirmed')->name('test-drives.confirmed');
        Route::view('/bookings', 'pages.customer.bookings')->name('bookings');
        Route::view('/bookings/{booking}', 'pages.customer.booking-show')->name('bookings.show');
        Route::view('/garage', 'pages.customer.garage')->name('garage');
        Route::view('/payments', 'pages.customer.payments')->name('payments');
        Route::view('/profile', 'pages.customer.profile')->name('profile');
        Route::view('/settings', 'pages.customer.settings')->name('settings');
        Route::get('/access-test', fn () => 'Customer access granted')
            ->name('access-test');
    });
