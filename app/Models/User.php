<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'current_subscription_id',
        'subscription_status',
        'last_login_at',
        'timezone',
        'preferences',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_login_at' => 'datetime',
            'preferences' => 'array',
        ];
    }

    // Relationships
    public function subscriptions(): HasMany
    {
        return $this->hasMany(UserSubscription::class);
    }

    public function currentSubscription(): BelongsTo
    {
        return $this->belongsTo(UserSubscription::class, 'current_subscription_id');
    }

    public function activeSubscription(): ?UserSubscription
    {
        return $this->subscriptions()->active()->first();
    }

    public function dailyPickAllocations(): HasMany
    {
        return $this->hasMany(DailyPickAllocation::class);
    }

    // Subscription Status Methods
    public function isFree(): bool
    {
        return $this->subscription_status === 'free';
    }

    public function hasActiveSubscription(): bool
    {
        return $this->subscription_status === 'active' && $this->activeSubscription();
    }

    public function getSubscriptionPlan(): ?SubscriptionPlan
    {
        return $this->activeSubscription()?->subscriptionPlan;
    }

    public function getCurrentPlanName(): string
    {
        if ($this->subscription_status === 'free' || !$this->hasActiveSubscription()) {
            return 'Free';
        }
        return $this->getSubscriptionPlan()?->name ?? 'Free';
    }

    // Picks & Limits
    public function getDailyPicksLimit(): int
    {
        if ($this->isFree()) return 1;
        return $this->getSubscriptionPlan()?->daily_picks_limit ?? 1;
    }

    public function getTodayPickAllocation(): DailyPickAllocation
    {
        return DailyPickAllocation::getTodayAllocation($this);
    }

    public function getRemainingPicksToday(): int
    {
        return $this->getTodayPickAllocation()->remaining_picks;
    }

    public function canAccessMatch(FootballMatch $match): bool
    {
        $plan = $this->getSubscriptionPlan();
        
        if (!$plan) return true; // Free users get random picks
        
        return $plan->canAccessLeague($match->league?->code ?? '');
    }

    // AI & Analysis Access
    public function hasAIAnalysis(): bool
    {
        return $this->getSubscriptionPlan()?->has_ai_analysis ?? false;
    }

    public function hasDetailedAI(): bool
    {
        return $this->getSubscriptionPlan()?->has_detailed_ai ?? false;
    }

    public function hasBudgetStrategies(): bool
    {
        return $this->getSubscriptionPlan()?->has_budget_strategies ?? false;
    }

    public function getBudgetStrategiesCount(): int
    {
        return $this->getSubscriptionPlan()?->budget_strategies_count ?? 0;
    }

    public function hasPrioritySupport(): bool
    {
        return $this->getSubscriptionPlan()?->has_priority_support ?? false;
    }

    public function hasEarlyAccess(): bool
    {
        return $this->getSubscriptionPlan()?->has_early_access ?? false;
    }

    // Subscription Management
    public function subscribeTo(SubscriptionPlan $plan, string $paymentMethod = null, string $transactionId = null): UserSubscription
    {
        // Cancel any active subscriptions first
        $this->subscriptions()->active()->each(function($subscription) {
            $subscription->cancel();
        });

        // Create new subscription
        $subscription = UserSubscription::createFromPlan($this, $plan, $paymentMethod, $transactionId);

        // Update user's current subscription
        $this->update([
            'current_subscription_id' => $subscription->id,
            'subscription_status' => 'active',
        ]);

        return $subscription;
    }

    public function cancelSubscription(): bool
    {
        if (!$this->hasActiveSubscription()) return false;

        $this->activeSubscription()->cancel();
        
        $this->update([
            'current_subscription_id' => null,
            'subscription_status' => 'cancelled',
        ]);

        return true;
    }

    public function getAvailableLeagues(): array
    {
        return $this->getSubscriptionPlan()?->getAvailableLeagues() ?? [];
    }

    public function updateLastLogin(): void
    {
        $this->update(['last_login_at' => now()]);
    }
}
