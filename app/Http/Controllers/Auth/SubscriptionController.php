<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use App\Models\UserSubscription;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class SubscriptionController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth')->except(['index', 'plans']);
    }

    /**
     * Show subscription plans (public page)
     */
    public function index(): View
    {
        $plans = SubscriptionPlan::getActivePlans();
        
        return view('auth.subscriptions.index', compact('plans'));
    }

    /**
     * Show subscription plans for authenticated users
     */
    public function plans(): View
    {
        $plans = SubscriptionPlan::getActivePlans();
        $userPlan = auth()->user()?->getSubscriptionPlan();
        
        return view('auth.subscriptions.plans', compact('plans', 'userPlan'));
    }

    /**
     * Show user's current subscription details
     */
    public function show(): View
    {
        $user = auth()->user();
        $currentSubscription = $user->activeSubscription();
        $subscriptionHistory = $user->subscriptions()
            ->with('subscriptionPlan')
            ->orderBy('created_at', 'desc')
            ->paginate(10);
        
        $todayAllocation = $user->getTodayPickAllocation();
        
        return view('auth.subscriptions.show', compact(
            'user', 
            'currentSubscription', 
            'subscriptionHistory',
            'todayAllocation'
        ));
    }

    /**
     * Show subscription checkout page
     */
    public function checkout(string $planSlug): View
    {
        $plan = SubscriptionPlan::getBySlug($planSlug);
        
        if (!$plan) {
            abort(404, 'Plan de suscripción no encontrado');
        }

        $user = auth()->user();
        
        return view('auth.subscriptions.checkout', compact('plan', 'user'));
    }

    /**
     * Process subscription purchase
     */
    public function subscribe(Request $request, string $planSlug): RedirectResponse
    {
        $plan = SubscriptionPlan::getBySlug($planSlug);
        
        if (!$plan) {
            return back()->withErrors(['plan' => 'Plan de suscripción no encontrado']);
        }

        $validated = $request->validate([
            'payment_method' => 'required|in:credit_card,paypal,stripe,bank_transfer',
            'transaction_id' => 'sometimes|string',
            'terms_accepted' => 'required|accepted',
        ]);

        try {
            $user = auth()->user();
            
            // For demo purposes - in production, integrate with payment gateway
            if ($plan->price > 0) {
                // Mock payment processing
                $transactionId = $validated['transaction_id'] ?? 'demo_' . uniqid();
                
                // Here you would integrate with:
                // - Stripe: \Stripe\PaymentIntent::create()
                // - PayPal: PayPal SDK
                // - Bank transfer: Manual verification
                
                $paymentSuccessful = true; // Mock success
                
                if (!$paymentSuccessful) {
                    return back()->withErrors(['payment' => 'Error en el procesamiento del pago']);
                }
            } else {
                $transactionId = 'free_plan';
            }

            // Create subscription
            $subscription = $user->subscribeTo($plan, $validated['payment_method'], $transactionId);

            return redirect()->route('subscription.show')
                ->with('success', "¡Suscripción activada! Bienvenido al plan {$plan->name}.");
                
        } catch (\Exception $e) {
            \Log::error('Subscription creation failed: ' . $e->getMessage());
            
            return back()->withErrors(['subscription' => 'Error al procesar la suscripción. Intente nuevamente.']);
        }
    }

    /**
     * Cancel user subscription
     */
    public function cancel(): RedirectResponse
    {
        $user = auth()->user();
        
        if (!$user->hasActiveSubscription()) {
            return redirect()->route('subscription.show')
                ->withErrors(['subscription' => 'No tienes una suscripción activa para cancelar.']);
        }

        try {
            $user->cancelSubscription();
            
            return redirect()->route('subscription.show')
                ->with('success', 'Suscripción cancelada. Tendrás acceso hasta el final del período pagado.');
                
        } catch (\Exception $e) {
            \Log::error('Subscription cancellation failed: ' . $e->getMessage());
            
            return back()->withErrors(['subscription' => 'Error al cancelar la suscripción.']);
        }
    }

    /**
     * Upgrade/Downgrade subscription
     */
    public function changePlan(Request $request, string $planSlug): RedirectResponse
    {
        $newPlan = SubscriptionPlan::getBySlug($planSlug);
        
        if (!$newPlan) {
            return back()->withErrors(['plan' => 'Plan no encontrado']);
        }

        $user = auth()->user();
        $currentPlan = $user->getSubscriptionPlan();

        if ($currentPlan && $currentPlan->id === $newPlan->id) {
            return back()->withErrors(['plan' => 'Ya tienes este plan activo']);
        }

        $validated = $request->validate([
            'payment_method' => 'required|in:credit_card,paypal,stripe,bank_transfer',
        ]);

        try {
            // Calculate prorated amount (simplified for demo)
            $proratedAmount = $this->calculateProration($currentPlan, $newPlan);
            
            if ($proratedAmount > 0) {
                // Process payment for difference
                $transactionId = 'upgrade_' . uniqid();
            } else {
                $transactionId = 'downgrade_' . uniqid();
            }

            // Create new subscription
            $subscription = $user->subscribeTo($newPlan, $validated['payment_method'], $transactionId);

            $message = $newPlan->price > ($currentPlan?->price ?? 0) 
                ? "¡Plan actualizado a {$newPlan->name}!" 
                : "Plan cambiado a {$newPlan->name}";

            return redirect()->route('subscription.show')->with('success', $message);
            
        } catch (\Exception $e) {
            \Log::error('Plan change failed: ' . $e->getMessage());
            
            return back()->withErrors(['subscription' => 'Error al cambiar el plan.']);
        }
    }

    /**
     * Calculate prorated amount for plan changes
     */
    private function calculateProration(?SubscriptionPlan $currentPlan, SubscriptionPlan $newPlan): float
    {
        if (!$currentPlan) return $newPlan->price;
        
        // Simplified calculation - in production, calculate based on remaining days
        return max(0, $newPlan->price - $currentPlan->price);
    }

    /**
     * API endpoint to get user's remaining picks
     */
    public function remainingPicks(): \Illuminate\Http\JsonResponse
    {
        $user = auth()->user();
        $allocation = $user->getTodayPickAllocation();
        
        return response()->json([
            'remaining_picks' => $allocation->remaining_picks,
            'total_picks' => $allocation->picks_allocated,
            'used_picks' => $allocation->picks_used,
            'usage_percentage' => $allocation->usage_percentage,
            'plan_name' => $user->getCurrentPlanName(),
        ]);
    }
}