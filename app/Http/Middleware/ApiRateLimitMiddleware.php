<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ApiRateLimitMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, int $maxAttempts = 60, int $decayMinutes = 1): Response
    {
        $key = $this->resolveRequestSignature($request);
        $attempts = Cache::get($key, 0);

        if ($attempts >= $maxAttempts) {
            return $this->buildResponse($key, $maxAttempts, $decayMinutes);
        }

        Cache::put($key, $attempts + 1, now()->addMinutes($decayMinutes));

        $response = $next($request);

        return $this->addHeaders(
            $response, 
            $maxAttempts, 
            $maxAttempts - $attempts - 1,
            $this->getTimeUntilNextRetry($key, $decayMinutes)
        );
    }

    /**
     * Resolve request signature for rate limiting.
     */
    protected function resolveRequestSignature(Request $request): string
    {
        $ip = $request->ip();
        $route = $request->route()?->getName() ?? $request->path();
        
        // Different limits for different endpoints
        $key = "rate_limit:{$ip}:{$route}";
        
        // Add user identification if available (for future auth implementation)
        if ($request->user()) {
            $key .= ":{$request->user()->id}";
        }
        
        return $key;
    }

    /**
     * Create a 'too many attempts' response.
     */
    protected function buildResponse(string $key, int $maxAttempts, int $decayMinutes): JsonResponse
    {
        $retryAfter = $this->getTimeUntilNextRetry($key, $decayMinutes);

        return response()->json([
            'error' => 'Too Many Requests',
            'message' => "Rate limit exceeded. Try again in {$retryAfter} seconds.",
            'max_attempts' => $maxAttempts,
            'retry_after' => $retryAfter
        ], 429)->header('Retry-After', $retryAfter);
    }

    /**
     * Add rate limit headers to response.
     */
    protected function addHeaders(Response $response, int $maxAttempts, int $remaining, int $retryAfter): Response
    {
        return $response->withHeaders([
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => max(0, $remaining),
            'X-RateLimit-Reset' => now()->addSeconds($retryAfter)->timestamp,
        ]);
    }

    /**
     * Get the time until the next retry.
     */
    protected function getTimeUntilNextRetry(string $key, int $decayMinutes): int
    {
        $resetTime = Cache::get("{$key}:reset", now()->addMinutes($decayMinutes)->timestamp);
        
        return max(1, $resetTime - now()->timestamp);
    }
}