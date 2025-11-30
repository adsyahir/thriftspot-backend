<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RolePermissionController;

// Public routes
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login'])
        ->middleware('throttle.conditional:5,1')
        ->name('auth.login');

    Route::post('register', [UserController::class, 'store'])
        ->middleware('throttle.conditional:10,1')
        ->name('auth.register');
});

// Protected routes
Route::middleware('auth:api')->group(function () {
    // Authentication routes (authenticated)
    Route::prefix('auth')->controller(AuthController::class)->group(function () {
        Route::post('logout', 'logout')->name('auth.logout');
        Route::post('refresh', 'refresh')->name('auth.refresh');
        Route::get('me', 'me')->name('auth.me');
    });

    Route::controller(RolePermissionController::class)->group(function () {
        // Roles
        Route::get('roles', 'getRoles')->name('roles.index');
        Route::post('roles', 'createRole')->name('roles.store');
        Route::put('roles/{id}', 'updateRole')->name('roles.update');
        Route::delete('roles/{id}', 'deleteRole')->name('roles.destroy');

        // Permissions
        Route::get('permissions', 'getPermissions')->name('permissions.index');
        Route::post('permissions', 'createPermission')->name('permissions.store');
        Route::put('permissions/{id}', 'updatePermission')->name('permissions.update');
        Route::delete('permissions/{id}', 'deletePermission')->name('permissions.destroy');
    });

    // User routes
    Route::prefix('users')->group(function () {
        Route::get('me', [UserController::class, 'show'])->name('users.me');
    });
});
