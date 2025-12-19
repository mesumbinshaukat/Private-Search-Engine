<?php

use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\SearchController;
use App\Http\Controllers\Api\V1\StatsController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/search', [SearchController::class, 'search'])->middleware('throttle:60,1');
    Route::get('/topic', [SearchController::class, 'getRandomTopic'])->middleware('throttle:60,1');
    Route::get('/categories', [CategoryController::class, 'index'])->middleware('throttle:60,1');
    Route::get('/health', [HealthController::class, 'check']);
    Route::get('/stats', [StatsController::class, 'show'])->middleware('throttle:60,1');
});
