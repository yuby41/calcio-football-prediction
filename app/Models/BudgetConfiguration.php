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
        if ($minimumAmount === null) {
            $minimumThreshold = max(2.0, $this->initial_budget * 0.01);
            
            // For Martingale strategy, use a more lenient threshold
            if ($this->strategy === 'martingale') {
                $minimumThreshold = max(1.0, $this->initial_budget * 0.005);
            }
        } else {
            $minimumThreshold = $minimumAmount;
        }
        
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
        $minimumThreshold = $this->getMinimumBetAmount();
        
        // For Martingale strategy, use a more lenient threshold
        // since it's designed to recover from losses by doubling bets
        if ($this->strategy === 'martingale') {
            $minimumThreshold = max(1.0, $this->initial_budget * 0.005); // 0.5% threshold instead of 1%
        }
        
        return $this->current_budget < $minimumThreshold;
    }

    /**
     * Get the total balance after all resolved bets (won/lost)
     */
    public function getBalanceAfterResolvedBets(): float
    {
        $resolvedBets = $this->bets()->whereIn('status', ['won', 'lost'])->get();
        $balance = $this->initial_budget;
        
        foreach ($resolvedBets as $bet) {
            $balance += $bet->actual_profit ?? 0;
        }
        
        return $balance;
    }

    /**
     * Get total amount committed to pending bets
     */
    public function getPendingBetsAmount(): float
    {
        return (float) $this->bets()->where('status', 'pending')->sum('amount');
    }

    /**
     * Get the correct available balance (after resolved bets minus pending commitments)
     */
    public function getAvailableBalance(): float
    {
        return $this->getBalanceAfterResolvedBets() - $this->getPendingBetsAmount();
    }

    /**
     * Synchronize current_budget with correct available balance
     */
    public function syncAvailableBalance(): bool
    {
        $correctBalance = $this->getAvailableBalance();
        
        if (abs($this->current_budget - $correctBalance) > 0.01) {
            $this->current_budget = $correctBalance;
            $this->save();
            return true; // Budget was corrected
        }
        
        return false; // No correction needed
    }

    /**
     * Update budget when a bet is placed (reduce available balance)
     */
    public function placeBet(float $amount): void
    {
        $this->current_budget -= $amount;
        $this->save();
    }

    /**
     * Update budget when a bet is resolved (add only profit, amount was already deducted)
     */
    public function resolveBet(float $amount, float $actualProfit): void
    {
        // When a bet resolves, we only add the profit/loss
        // The original amount was already deducted when the bet was placed
        // For lost bets: actualProfit = -amount (so we lose the amount)
        // For won bets: actualProfit = positive (so we gain profit)
        $this->current_budget += $actualProfit;
        $this->save();
    }

    /**
     * Update budget when a pending bet is cancelled (restore amount)
     */
    public function cancelBet(float $amount): void
    {
        $this->current_budget += $amount;
        $this->save();
    }
}