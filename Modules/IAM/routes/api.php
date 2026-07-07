<?php

use Illuminate\Support\Facades\Route;
use Modules\IAM\Http\Controllers\AuthController;
use Modules\IAM\Http\Controllers\ProfileController;
use Modules\IAM\Http\Controllers\RoleController;
use Modules\IAM\Http\Controllers\UserController;

// Public authentication routes
Route::prefix('v1/auth')->name('auth.')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->name('login');
});

// Protected routes — require valid Sanctum token + business team context
Route::middleware(['auth:sanctum', 'permission.team'])->prefix('v1')->name('api.')->group(function () {
    // Auth — session
    Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
    Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');

    // Auth — self-service profile (any authenticated user)
    Route::put('auth/profile', [ProfileController::class, 'update'])->name('auth.profile.update');
    Route::put('auth/password', [ProfileController::class, 'changePassword'])->name('auth.password.change');

    // User management — guarded by user.manage permission inside controller
    Route::apiResource('users', UserController::class);

    // Role management — list roles, update permissions, assign/remove roles on users
    Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
    Route::put('roles/{roleId}/permissions', [RoleController::class, 'syncPermissions'])->name('roles.permissions.sync');
    Route::post('users/{user}/roles', [RoleController::class, 'assignRole'])->name('users.roles.assign');
    Route::delete('users/{user}/roles/{role}', [RoleController::class, 'removeRole'])->name('users.roles.remove');
});
