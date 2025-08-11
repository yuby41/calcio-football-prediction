<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BudgetConfiguration extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'strategy',
        'initial_budget',
        'current_budget',
        'target_profit',
        'max_bet_percentage',
        'min_confidence',
        'strategy_parameters',
        'is_active',
    ];

    protected $casts = [
        'initial_budget' => 'decimal:2',
        'current_budget' => 'decimal:2',
        'target_profit' => 'decimal:2',
        'max_bet_percentage' => 'decimal:2',
        'min_confidence' => 'decimal:2',
        'strategy_parameters' => 'array',
        'is_active' => 'boolean',
    ];

    public function bets(): HasMany
    {
        return $this->hasMany(Bet::class);
    }

    public function budgetHistory(): HasMany
    {
        return $this->hasMany(BudgetHistory::class);
    }

    public function getWinRateAttribute(): float
    {
        $totalBets = $this->bets()->whereIn('status', ['won', 'lost'])->count();
        if ($totalBets === 0) return 0;

        $wonBets = $this->bets()->where('status', 'won')->count();
        return round(($wonBets / $totalBets) * 100, 2);
    }

    public function getTotalProfitAttribute(): float
    {
        return (float) $this->bets()->whereNotNull('actual_profit')->sum('actual_profit');
    }

    public function getNetProfitAttribute(): float
    {
        return $this->current_budget - $this->initial_budget;
    }

    public function getROIAttribute(): float
    {
        if ($this->initial_budget == 0) return 0;
        return round(($this->getNetProfitAttribute() / $this->initial_budget) * 100, 2);
    }

    public function getPendingBetsAttribute()
    {
        return $this->bets()->where('status', 'pending')->count();
    }

    public function getMaxDrawdownAttribute(): float
    {
        $history = $this->budgetHistory()->orderBy('created_at')->get();
        $maxBalance = $this->initial_budget;
        $maxDrawdown = 0;

        foreach ($history as $record) {
            $maxBalance = max($maxBalance, $record->balance_after);
            $drawdown = $maxBalance - $record->balance_after;
            $maxDrawdown = max($maxDrawdown, $drawdown);
        }

        return $maxDrawdown;
    }

    /**
     * Check if the budget can place a bet
     */
    public function canBet(float $minimumAmount = null): bool
    {
        $minimumThreshold = $minimumAmount ?? max(2.0, $this->initial_budget * 0.01);
        
        return $this->is_active && 
               $this->current_budget >= $minimumThreshold &&
               !$this->hasReachedTargetProfit();
    }

    /**
     * Check if target profit has been reached
     */
    public function hasReachedTargetProfit(): bool
    {
        if (!$this->target_profit || $this->target_profit <= 0) {
            return false; // No target set
        }
        
        $currentProfit = $this->getNetProfitAttribute();
        return $currentProfit >= $this->target_profit;
    }

    /**
     * Check if budget should be deactivated due to target achievement
     */
    public function shouldDeactivateForTarget(): bool
    {
        return $this->hasReachedTargetProfit() && $this->is_active;
    }

    /**
     * Get minimum bet amount based on strategy
     */
    public function getMinimumBetAmount(): float
    {
        return max(2.0, $this->initial_budget * 0.01);
    }

    /**
     * Check if budget is exhausted
     */
    public function isExhausted(): bool
    {
        return $this->current_budget < $this->getMinimumBetAmount();
    }
}