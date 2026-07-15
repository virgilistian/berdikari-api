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
    Route::get('/', [InventoryController::class, 'index']);
    Route::get('summary', [InventoryController::class, 'summary']);
    Route::get('low-stock', [InventoryController::class, 'lowStock']);
    Route::post('receive', [InventoryController::class, 'receive']);
    Route::post('adjust', [InventoryController::class, 'adjust']);

    Route::prefix('daily-stock')->group(function () {
        Route::get('products', [DailyStockController::class, 'products']);
        Route::get('{date}', [DailyStockController::class, 'show']);
        Route::post('open', [DailyStockController::class, 'open']);
        Route::post('close', [DailyStockController::class, 'close']);
        Route::post('adjust', [DailyStockController::class, 'adjust']);
    });

    Route::get('{id}', [InventoryController::class, 'show']);
    Route::get('{id}/movements', [InventoryController::class, 'movements']);
    Route::put('{id}/min-stock', [InventoryController::class, 'setMinStock']);
});
