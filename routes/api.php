<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\RolePermissionController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login'])
        ->middleware('throttle.conditional:5,1,login')
        ->name('auth.login');

    Route::post('register', [UserController::class, 'store'])
        ->middleware('throttle.conditional:10,1,register')
        ->name('auth.register');

    Route::post('refresh', [AuthController::class, 'refresh'])->name('auth.refresh');
});

// Protected routes
Route::middleware('auth:api')->group(function () {
    // Authentication routes (authenticated)
    Route::prefix('auth')->controller(AuthController::class)->group(function () {
        Route::post('logout', 'logout')->name('auth.logout');
        Route::post('me', 'me')->name('auth.me');
    });

    Route::controller(RolePermissionController::class)->group(function () {
        Route::middleware('permission:view-users,api')->group(function () {
            Route::get('roles', 'getRoles')->name('roles.index');
            Route::get('permissions', 'getPermissions')->name('permissions.index');
            Route::get('users/roles', 'getUsersWithRoles')->name('users.roles.index');
        });

        Route::middleware('permission:manage-settings,api')->group(function () {
            // Roles
            Route::post('roles', 'createRole')->name('roles.store');
            Route::put('roles/{id}', 'updateRole')->name('roles.update');
            Route::delete('roles/{id}', 'deleteRole')->name('roles.destroy');

            // Permissions
            Route::post('permissions', 'createPermission')->name('permissions.store');
            Route::put('permissions/{id}', 'updatePermission')->name('permissions.update');
            Route::delete('permissions/{id}', 'deletePermission')->name('permissions.destroy');

            // User Role Assignment
            Route::post('users/{userId}/roles', 'assignRoleToUser')->name('users.roles.assign');
            Route::delete('users/{userId}/roles', 'removeRoleFromUser')->name('users.roles.remove');
        });
    });

    // User routes
    Route::prefix('users')->group(function () {
        Route::get('me', [UserController::class, 'show'])->name('users.me');
    });
});
