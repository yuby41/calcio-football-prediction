<?php

namespace App\Http\Middleware;

use App\Services\SimpleAccuracyService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class UpdateAccuracyMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        
        // Only update on home page visits
        if ($request->route() && $request->route()->getName() === 'home') {
            try {
                // Trigger accuracy calculation which will auto-update if needed
                SimpleAccuracyService::getCurrentAccuracy();
            } catch (\Exception $e) {
                Log::warning('Middleware failed to update accuracy: ' . $e->getMessage());
            }
        }
        
        return $response;
    }
}