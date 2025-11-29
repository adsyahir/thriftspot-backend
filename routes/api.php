<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login'])
        ->middleware('throttle:5,1')
        ->name('auth.login');

    Route::post('register', [UserController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('auth.register');
});

// Protected routes
Route::middleware('auth:api')->group(function () {
    // Authentication routes (authenticated)
    Route::prefix('auth')->group(function () {
        Route::post('logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::post('refresh', [AuthController::class, 'refresh'])->name('auth.refresh');
        Route::get('me', [AuthController::class, 'me'])->name('auth.me');
    });

    // User routes
    Route::prefix('users')->group(function () {
        Route::get('me', [UserController::class, 'show'])->name('users.me');
    });
});
