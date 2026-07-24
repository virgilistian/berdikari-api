<?php

use Illuminate\Support\Facades\Route;
use Modules\Catalog\Http\Controllers\CategoryController;
use Modules\Catalog\Http\Controllers\ProductController;

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

Route::prefix('v1/catalog')->middleware('auth:sanctum')->group(function () {
    Route::apiResource('categories', CategoryController::class);
    Route::apiResource('products', ProductController::class);

    Route::middleware('permission.team')->group(function () {
        Route::get('products/{id}/image', [ProductController::class, 'showImage'])
            ->middleware('can:catalog.view')->name('catalog.products.image.show');
        Route::post('products/{id}/image', [ProductController::class, 'uploadImage'])
            ->middleware(['can:catalog.update', 'throttle:20,1'])->name('catalog.products.image.store');
        Route::delete('products/{id}/image', [ProductController::class, 'deleteImage'])
            ->middleware('can:catalog.update')->name('catalog.products.image.destroy');
    });
});
