<?php

use Illuminate\Support\Facades\Route;
use Modules\Sales\Http\Controllers\PlateScanController;
use Modules\Sales\Http\Controllers\SaleOrderController;
use Modules\Sales\Http\Controllers\SalesController;

Route::middleware(['auth:sanctum'])->prefix('v1/sales')->group(function () {
    Route::post('/checkout', [SalesController::class, 'checkout'])->name('sales.checkout');
    Route::post('/scan-plate', [PlateScanController::class, 'scan'])->name('sales.scan-plate');

    Route::prefix('orders')->group(function () {
        Route::get('/', [SaleOrderController::class, 'index'])->name('sales.orders.index');
        Route::post('/', [SaleOrderController::class, 'store'])->name('sales.orders.store');
        Route::get('{id}', [SaleOrderController::class, 'show'])->name('sales.orders.show');
        Route::post('{id}/complete', [SaleOrderController::class, 'complete'])->name('sales.orders.complete');
        Route::post('{id}/payments', [SaleOrderController::class, 'pay'])->name('sales.orders.pay');
        Route::post('{id}/cancel', [SaleOrderController::class, 'cancel'])->name('sales.orders.cancel');
        Route::post('{id}/refund', [SaleOrderController::class, 'refund'])->name('sales.orders.refund');
    });
});
