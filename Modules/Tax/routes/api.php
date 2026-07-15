<?php

use Illuminate\Support\Facades\Route;
use Modules\Tax\Http\Controllers\TaxAssetController;
use Modules\Tax\Http\Controllers\TaxPdfController;
use Modules\Tax\Http\Controllers\TaxProfileController;
use Modules\Tax\Http\Controllers\TaxReportController;

Route::middleware(['auth:sanctum', 'permission.team'])->prefix('v1/tax')->group(function () {
    Route::get('business-types', [TaxProfileController::class, 'businessTypes'])
        ->middleware('can:tax.view')->name('tax.business-types');

    Route::get('profiles', [TaxProfileController::class, 'index'])
        ->middleware('can:tax.view')->name('tax.profiles.index');
    Route::put('profiles/{type}', [TaxProfileController::class, 'update'])
        ->middleware('can:tax.manage')->name('tax.profiles.update');

    Route::get('assets', [TaxAssetController::class, 'index'])
        ->middleware('can:tax.view')->name('tax.assets.index');
    Route::post('assets/{type}', [TaxAssetController::class, 'store'])
        ->middleware('can:tax.manage')->name('tax.assets.store');
    Route::delete('assets/{type}', [TaxAssetController::class, 'destroy'])
        ->middleware('can:tax.manage')->name('tax.assets.destroy');

    Route::post('generate', [TaxReportController::class, 'generate'])
        ->middleware('can:tax.create')->name('tax.generate');

    Route::get('reports', [TaxReportController::class, 'index'])
        ->middleware('can:tax.view')->name('tax.reports.index');
    Route::get('reports/{id}', [TaxReportController::class, 'show'])
        ->middleware('can:tax.view')->name('tax.reports.show');
    Route::put('reports/{id}', [TaxReportController::class, 'update'])
        ->middleware('can:tax.update')->name('tax.reports.update');
    Route::delete('reports/{id}', [TaxReportController::class, 'destroy'])
        ->middleware('can:tax.delete')->name('tax.reports.destroy');
    Route::get('reports/{id}/pdf', [TaxPdfController::class, 'show'])
        ->middleware('can:tax.export')->name('tax.reports.pdf');
});
