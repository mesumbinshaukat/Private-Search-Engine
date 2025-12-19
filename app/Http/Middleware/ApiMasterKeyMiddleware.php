<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiMasterKeyMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Check Sanctum Auth
        if (\Illuminate\Support\Facades\Auth::guard('sanctum')->check()) {
            return $next($request);
        }

        // 2. Check Master Key
        $masterKey = env('API_MASTER_KEY');
        $providedKey = $request->header('X-API-MASTER-KEY') ?: $request->query('api_master_key');

        if ($masterKey && $providedKey === $masterKey) {
            return $next($request);
        }

        return response()->json(['message' => 'Unauthorized: Invalid Auth or Master Key'], 401);
    }
}
