<?php

use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\SearchController;
use App\Http\Controllers\Api\V1\StatsController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/login', function (\Illuminate\Http\Request $request) {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (!\Illuminate\Support\Facades\Auth::attempt($credentials)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $user = \Illuminate\Support\Facades\Auth::user();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
        ]);
    });

    Route::middleware('master_key')->group(function () {
        Route::get('/search', [SearchController::class, 'search'])->middleware('throttle:60,1');
        Route::get('/topic', [SearchController::class, 'getRandomTopic'])->middleware('throttle:60,1');
        Route::get('/categories', [CategoryController::class, 'index'])->middleware('throttle:60,1');
        Route::get('/stats', [StatsController::class, 'show'])->middleware('throttle:60,1');
        
        Route::post('/trigger-refresh', function () {
            if (\Illuminate\Support\Facades\Cache::has('master_refresh_running')) {
                return response()->json([
                    'status' => 'busy',
                    'message' => 'A master refresh process is already running in the background.'
                ], 409);
            }

            \Illuminate\Support\Facades\Artisan::call('master:refresh', ['--async' => true]);
            return response()->json(['status' => 'success', 'message' => 'Master refresh triggered in background']);
        });
    });

    Route::get('/health', [HealthController::class, 'check']);
});
