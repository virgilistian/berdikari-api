<?php

use Illuminate\Support\Facades\Route;
use Modules\Finance\Http\Controllers\FinanceController;

Route::middleware(['auth:sanctum'])->prefix('v1/finance')->group(function () {
    Route::get('/', [FinanceController::class, 'index'])->name('finance.index');
    Route::post('/', [FinanceController::class, 'store'])->name('finance.store');
    Route::get('summary', [FinanceController::class, 'summary'])->name('finance.summary');
    Route::get('{id}', [FinanceController::class, 'show'])->name('finance.show');
    Route::delete('{id}', [FinanceController::class, 'destroy'])->name('finance.destroy');
});
