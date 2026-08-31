<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'pages.login');
Route::get('/login', [AuthenticatedSessionController::class, 'create'])
    ->middleware('guest')
    ->name('login');
Route::post('/login', [AuthenticatedSessionController::class, 'store'])
    ->middleware('guest')
    ->name('login.submit');
Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');
Route::get('/dashboard', [AuthenticatedSessionController::class, 'redirectToDashboard'])
    ->middleware('auth')
    ->name('dashboard');

Route::prefix('customer')->name('customer.')->group(function () {
    Route::view('/login', 'pages.customer.auth.login')->name('login');
    Route::view('/register', 'pages.customer.auth.register')->name('register');
    Route::view('/forgot-password', 'pages.customer.auth.forgot')->name('forgot');
});
