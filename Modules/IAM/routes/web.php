<?php

use Illuminate\Support\Facades\Route;
use Modules\IAM\Http\Controllers\IAMController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('iams', IAMController::class)->names('iam');
});
