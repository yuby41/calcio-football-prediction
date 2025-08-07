<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class UserSubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'subscription_plan_id',
        'starts_at',
        'ends_at',
        'status',
        'price_paid',
        'payment_method',
        'transaction_id',
        'cancelled_at',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'price_paid' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subscriptionPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && 
               $this->starts_at <= now() && 
               ($this->ends_at === null || $this->ends_at > now());
    }

    public function isExpired(): bool
    {
        return $this->ends_at && $this->ends_at < now();
    }

    public function daysUntilExpiry(): ?int
    {
        if (!$this->ends_at) return null;
        return now()->diffInDays($this->ends_at, false);
    }

    public function getRemainingTimeAttribute(): ?string
    {
        if (!$this->ends_at) return 'Sin vencimiento';
        
        $days = $this->daysUntilExpiry();
        if ($days < 0) return 'Vencido';
        if ($days === 0) return 'Vence hoy';
        if ($days === 1) return 'Vence mañana';
        if ($days < 30) return "Vence en {$days} días";
        
        $months = intval($days / 30);
        return $months === 1 ? 'Vence en 1 mes' : "Vence en {$months} meses";
    }

    public function cancel(): void
    {
        $this->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);
    }

    public function expire(): void
    {
        $this->update(['status' => 'expired']);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active')
                    ->where('starts_at', '<=', now())
                    ->where(function($q) {
                        $q->whereNull('ends_at')->orWhere('ends_at', '>', now());
                    });
    }

    public static function createFromPlan(User $user, SubscriptionPlan $plan, string $paymentMethod = null, string $transactionId = null): self
    {
        $endsAt = $plan->billing_cycle === 'yearly' 
            ? now()->addYear() 
            : now()->addMonth();

        return self::create([
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'starts_at' => now(),
            'ends_at' => $endsAt,
            'status' => 'active',
            'price_paid' => $plan->price,
            'payment_method' => $paymentMethod,
            'transaction_id' => $transactionId,
        ]);
    }
}