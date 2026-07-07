<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\BusinessController;
use Modules\Core\Http\Controllers\CoreController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::get('businesses', [BusinessController::class, 'index'])->name('businesses.index');
    Route::apiResource('cores', CoreController::class)->names('core');
});
