<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\BusinessController;
use Modules\Core\Http\Controllers\CoreController;
use Modules\Core\Http\Controllers\NotificationController;

Route::middleware(['auth:sanctum', 'permission.team'])->prefix('v1')->group(function () {
    Route::get('businesses', [BusinessController::class, 'index'])->name('businesses.index');
    Route::apiResource('cores', CoreController::class)->names('core');

    // Notifikasi in-app
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index'])->name('notifications.index');
        Route::get('unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unread-count');
        Route::post('mark-all-read', [NotificationController::class, 'markAllRead'])->name('notifications.mark-all-read');
        Route::post('{id}/read', [NotificationController::class, 'markRead'])->name('notifications.mark-read');
    });
});
