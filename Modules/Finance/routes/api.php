<?php

use Illuminate\Support\Facades\Route;
use Modules\Finance\Http\Controllers\FinanceCategoryController;
use Modules\Finance\Http\Controllers\FinanceController;

Route::middleware(['auth:sanctum', 'permission.team'])->prefix('v1/finance')->group(function () {
    Route::get('/', [FinanceController::class, 'index'])->name('finance.index');
    Route::post('/', [FinanceController::class, 'store'])->name('finance.store');
    Route::get('summary', [FinanceController::class, 'summary'])->name('finance.summary');

    Route::get('categories', [FinanceCategoryController::class, 'index'])
        ->middleware('can:finance.view')->name('finance.categories.index');
    Route::post('categories', [FinanceCategoryController::class, 'store'])
        ->middleware('can:finance.create')->name('finance.categories.store');
    Route::put('categories/{id}', [FinanceCategoryController::class, 'update'])
        ->middleware('can:finance.update')->name('finance.categories.update');
    Route::delete('categories/{id}', [FinanceCategoryController::class, 'destroy'])
        ->middleware('can:finance.delete')->name('finance.categories.destroy');

    Route::get('{id}', [FinanceController::class, 'show'])->name('finance.show');
    Route::delete('{id}', [FinanceController::class, 'destroy'])
        ->middleware('can:finance.delete')->name('finance.destroy');
});
