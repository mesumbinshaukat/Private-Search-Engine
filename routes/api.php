<?php

use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\SearchController;
use App\Http\Controllers\Api\V1\StatsController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/login', [\App\Http\Controllers\Api\V1\AuthController::class, 'apiLogin']);

    Route::middleware('master_key')->group(function () {
        Route::get('/search', [SearchController::class, 'search'])->middleware('throttle:60,1');
        Route::get('/topic', [SearchController::class, 'getRandomTopic'])->middleware('throttle:60,1');
        Route::get('/categories', [CategoryController::class, 'index'])->middleware('throttle:60,1');
        Route::get('/stats', [StatsController::class, 'show'])->middleware('throttle:60,1');
        
        Route::post('/trigger-refresh', function () {
            try {
                if (\Illuminate\Support\Facades\Cache::has('master_refresh_running')) {
                    return response()->json([
                        'status' => 'busy',
                        'message' => 'A master refresh process is already running in the background.'
                    ], 409);
                }

                \Illuminate\Support\Facades\Artisan::call('master:refresh', ['--async' => true]);
                return response()->json(['status' => 'success', 'message' => 'Master refresh triggered in background']);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Failed to trigger master refresh', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);

                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to trigger refresh: ' . $e->getMessage()
                ], 500);
            }
        });
    });

    Route::get('/health', [HealthController::class, 'check']);
});
