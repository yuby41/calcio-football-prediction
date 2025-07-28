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
}