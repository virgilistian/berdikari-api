<?php

use Illuminate\Support\Facades\Route;
use Modules\IAM\Http\Controllers\AuthController;
use Modules\IAM\Http\Controllers\UserController;

// Public authentication routes
Route::prefix('v1/auth')->name('auth.')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->name('login');
});

// Protected routes — require valid Sanctum token
Route::middleware('auth:sanctum')->prefix('v1')->name('api.')->group(function () {
    // Auth profile
    Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
    Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');

    // User management (owner-only enforced inside controller)
    Route::apiResource('users', UserController::class);
});
