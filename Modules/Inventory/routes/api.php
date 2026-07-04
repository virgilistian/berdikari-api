<?php

use Illuminate\Support\Facades\Route;
use Modules\Inventory\Http\Controllers\InventoryController;
use Modules\Inventory\Http\Controllers\DailyStockController;

/*
 *--------------------------------------------------------------------------
 * API Routes
 *--------------------------------------------------------------------------
 *
 * Here is where you can register API routes for your application. These
 * routes are loaded by the RouteServiceProvider within a group which
 * is assigned the "api" middleware group. Enjoy building your API!
 *
*/

Route::prefix('v1/inventory')->middleware('auth:sanctum')->group(function () {
    Route::apiResource('/', InventoryController::class)->only(['index', 'show']);

    Route::prefix('daily-stock')->group(function () {
        Route::get('{date}', [DailyStockController::class, 'show']);
        Route::post('open', [DailyStockController::class, 'open']);
        Route::post('close', [DailyStockController::class, 'close']);
    });
});
