<?php

use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/user',[UserController::class, 'store'])->name('store');

Route::get('/test', function (Request $request) {

    return response()->json([
        'success' => true,
        'message' => 'User created successfully',
    ]);
});