<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSubscriptionAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->check()) {
            return redirect()->route('login')->with('error', 'Debes iniciar sesión para acceder a las recomendaciones.');
        }

        $user = auth()->user();
        $allocation = $user->getTodayPickAllocation();

        // Check if user has remaining picks for today
        if ($allocation->remaining_picks <= 0) {
            return redirect()->route('subscription.plans')
                ->with('error', 'Has agotado tus picks diarios. Actualiza tu plan para obtener más picks.');
        }

        // Store allocation in request for use in controller
        $request->attributes->set('daily_allocation', $allocation);

        return $next($request);
    }
}