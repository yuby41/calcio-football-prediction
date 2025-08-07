<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'price',
        'billing_cycle',
        'features',
        'daily_picks_limit',
        'allowed_leagues',
        'has_ai_analysis',
        'has_budget_strategies',
        'budget_strategies_count',
        'has_detailed_ai',
        'has_priority_support',
        'has_early_access',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'features' => 'array',
        'allowed_leagues' => 'array',
        'has_ai_analysis' => 'boolean',
        'has_budget_strategies' => 'boolean',
        'has_detailed_ai' => 'boolean',
        'has_priority_support' => 'boolean',
        'has_early_access' => 'boolean',
        'is_active' => 'boolean',
        'price' => 'decimal:2',
    ];

    public function userSubscriptions(): HasMany
    {
        return $this->hasMany(UserSubscription::class);
    }

    public function activeSubscriptions(): HasMany
    {
        return $this->hasMany(UserSubscription::class)->where('status', 'active');
    }

    public function getFormattedPriceAttribute(): string
    {
        return '€' . number_format($this->price, 2);
    }

    public function getMonthlyPriceAttribute(): float
    {
        return $this->billing_cycle === 'yearly' ? round($this->price / 12, 2) : $this->price;
    }

    public function getIsYearlyAttribute(): bool
    {
        return $this->billing_cycle === 'yearly';
    }

    public function getYearlySavingsAttribute(): float
    {
        if ($this->billing_cycle !== 'yearly') return 0;
        
        $monthlyEquivalent = $this->getMonthlyPriceAttribute() * 12;
        return round(($monthlyEquivalent - $this->price) / $monthlyEquivalent * 100, 0);
    }

    // Check if user can access specific league
    public function canAccessLeague(string $leagueCode): bool
    {
        if (empty($this->allowed_leagues)) return true; // All leagues if not specified
        return in_array($leagueCode, $this->allowed_leagues);
    }

    // Get available leagues for this plan
    public function getAvailableLeagues(): array
    {
        $allLeagues = [
            'PL' => 'Premier League',
            'PD' => 'La Liga',
            'BL1' => 'Bundesliga',
            'SA' => 'Serie A',
            'FL1' => 'Ligue 1',
            'CL' => 'Champions League',
            'EL' => 'Europa League',
            'ECL' => 'Europa Conference League',
        ];

        if (empty($this->allowed_leagues)) return $allLeagues;
        
        return array_intersect_key($allLeagues, array_flip($this->allowed_leagues));
    }

    public static function getBySlug(string $slug): ?self
    {
        return self::where('slug', $slug)->where('is_active', true)->first();
    }

    public static function getActivePlans()
    {
        return self::where('is_active', true)->orderBy('sort_order')->orderBy('price')->get();
    }
}