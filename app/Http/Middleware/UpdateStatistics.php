<?php

namespace App\Http\Middleware;

use App\Jobs\UpdatePredictionAccuracy;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class UpdateStatistics
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        
        // Only update on specific routes and not too frequently
        if ($this->shouldUpdateStatistics($request)) {
            $this->dispatchUpdateJobIfNeeded();
        }
        
        return $response;
    }
    
    private function shouldUpdateStatistics(Request $request): bool
    {
        // Update statistics on home page and statistics page visits
        return in_array($request->route()?->getName(), [
            'home',
            'statistics.index',
            'matches.index',
            'matches.show'
        ]);
    }
    
    private function dispatchUpdateJobIfNeeded(): void
    {
        $lastUpdate = Cache::get('statistics_last_update');
        $now = Carbon::now();
        
        // Dispatch update job if more than 3 minutes have passed (increased frequency for betting decisions)
        if (!$lastUpdate || $now->diffInMinutes($lastUpdate) >= 3) {
            try {
                // Dispatch job to update prediction accuracy
                UpdatePredictionAccuracy::dispatch();
                Cache::put('statistics_last_update', $now, 15); // Cache for 15 minutes only
            } catch (\Exception $e) {
                // Log error but don't break the request
                \Log::warning('Failed to dispatch statistics update job: ' . $e->getMessage());
            }
        }
    }
}