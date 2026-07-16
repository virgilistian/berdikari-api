<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\BranchController;
use Modules\Core\Http\Controllers\BusinessController;
use Modules\Core\Http\Controllers\CoreController;
use Modules\Core\Http\Controllers\NotificationController;

Route::middleware(['auth:sanctum', 'permission.team'])->prefix('v1')->group(function () {
    Route::get('businesses', [BusinessController::class, 'index'])->name('businesses.index');
    Route::get('business', [BusinessController::class, 'show'])
        ->middleware('can:business.manage')->name('business.show');
    Route::put('business', [BusinessController::class, 'update'])
        ->middleware('can:business.manage')->name('business.update');

    Route::prefix('branches')->group(function () {
        Route::get('/', [BranchController::class, 'index'])
            ->middleware('can:business.manage')->name('branches.index');
        Route::post('/', [BranchController::class, 'store'])
            ->middleware('can:business.manage')->name('branches.store');
        Route::put('{branch}', [BranchController::class, 'update'])
            ->middleware('can:business.manage')->name('branches.update');
        Route::delete('{branch}', [BranchController::class, 'destroy'])
            ->middleware('can:business.manage')->name('branches.destroy');
    });

    // Multi-business management. Any member can switch (low-risk context
    // change); create/update/deactivate/logo require business.manage.
    Route::prefix('businesses')->group(function () {
        Route::post('{business}/switch', [BusinessController::class, 'switch'])->name('businesses.switch');

        Route::middleware('can:business.manage')->group(function () {
            Route::post('/', [BusinessController::class, 'store'])->name('businesses.store');
            Route::get('{business}', [BusinessController::class, 'showOne'])->name('businesses.show-one');
            Route::put('{business}', [BusinessController::class, 'updateOne'])->name('businesses.update-one');
            Route::delete('{business}', [BusinessController::class, 'destroy'])->name('businesses.destroy');
            Route::post('{business}/logo', [BusinessController::class, 'uploadLogo'])->name('businesses.logo');
        });
    });

    Route::apiResource('cores', CoreController::class)->names('core');

    // Notifikasi in-app
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index'])->name('notifications.index');
        Route::get('unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unread-count');
        Route::post('mark-all-read', [NotificationController::class, 'markAllRead'])->name('notifications.mark-all-read');
        Route::post('{id}/read', [NotificationController::class, 'markRead'])->name('notifications.mark-read');
    });
});
