<?php

use App\Http\Controllers\Api\FundController;
use Illuminate\Support\Facades\Route;

Route::prefix('funds')->group(function () {
    Route::get('', [FundController::class, 'index']);
    Route::get('{fund}', [FundController::class, 'show']);
});
