<?php

use Illuminate\Support\Facades\Route;
use Modules\Sales\Http\Controllers\PlateScanController;
use Modules\Sales\Http\Controllers\SalesController;

Route::middleware(['auth:sanctum'])->prefix('v1/sales')->group(function () {
    Route::post('/checkout', [SalesController::class, 'checkout'])->name('sales.checkout');
    Route::post('/scan-plate', [PlateScanController::class, 'scan'])->name('sales.scan-plate');
});
