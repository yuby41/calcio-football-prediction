<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class Bet extends Model
{
    use HasFactory;

    protected $fillable = [
        'budget_configuration_id',
        'match_id',
        'amount',
        'odds',
        'bet_type',
        'potential_profit',
        'status',
        'actual_profit',
        'confidence',
        'budget_before',
        'budget_after',
        'sequence_step',
        'notes',
        'placed_at',
        'resolved_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'odds' => 'decimal:3',
        'potential_profit' => 'decimal:2',
        'actual_profit' => 'decimal:2',
        'confidence' => 'decimal:2',
        'budget_before' => 'decimal:2',
        'budget_after' => 'decimal:2',
        'placed_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function budgetConfiguration(): BelongsTo
    {
        return $this->belongsTo(BudgetConfiguration::class);
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(FootballMatch::class, 'match_id');
    }

    public function budgetHistory(): BelongsTo
    {
        return $this->belongsTo(BudgetHistory::class);
    }

    public function getBetTypeDisplayAttribute(): string
    {
        return match($this->bet_type) {
            'home_win' => 'Victoria Local',
            'away_win' => 'Victoria Visitante', 
            'draw' => 'Empate',
            'over_2_5' => 'Más de 2.5 Goles',
            'under_2_5' => 'Menos de 2.5 Goles',
            'both_teams_score' => 'Ambos Equipos Marcan',
            default => ucfirst(str_replace('_', ' ', $this->bet_type)),
        };
    }

    public function getStatusDisplayAttribute(): string
    {
        return match($this->status) {
            'pending' => 'Pendiente',
            'won' => 'Ganada',
            'lost' => 'Perdida',
            'cancelled' => 'Cancelada',
            default => ucfirst($this->status),
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'pending' => 'yellow',
            'won' => 'green',
            'lost' => 'red',
            'cancelled' => 'gray',
            default => 'gray',
        };
    }

    public function isResolvable(): bool
    {
        return $this->status === 'pending' && 
               $this->match && 
               $this->match->status === 'finished';
    }

    public function calculateResult(): array
    {
        if (!$this->isResolvable()) {
            return ['status' => 'pending', 'profit' => 0];
        }

        $match = $this->match;
        $won = false;

        switch ($this->bet_type) {
            case 'home_win':
                $won = $match->home_goals > $match->away_goals;
                break;
            case 'away_win':
                $won = $match->away_goals > $match->home_goals;
                break;
            case 'draw':
                $won = $match->home_goals == $match->away_goals;
                break;
            case 'over_2_5':
                $won = ($match->home_goals + $match->away_goals) > 2.5;
                break;
            case 'under_2_5':
                $won = ($match->home_goals + $match->away_goals) < 2.5;
                break;
            case 'both_teams_score':
                $won = $match->home_goals > 0 && $match->away_goals > 0;
                break;
        }

        $profit = $won ? ($this->amount * $this->odds) - $this->amount : -$this->amount;
        
        return [
            'status' => $won ? 'won' : 'lost',
            'profit' => $profit,
        ];
    }
}