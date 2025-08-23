<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TimeoutMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, $timeout = 60): Response
    {
        // Set execution time limit for this request
        set_time_limit((int)$timeout);
        
        // Set memory limit to handle large datasets
        ini_set('memory_limit', '512M');
        
        // Disable default timeout for database connections on heavy pages
        if (in_array($request->route()->getName(), ['statistics.index', 'statistics.chart-data', 'statistics.league-data'])) {
            set_time_limit(120); // 2 minutes for statistics pages
        }
        
        return $next($request);
    }
}
