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
        'bet_option',
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

    public function budgetHistory()
    {
        return $this->hasMany(BudgetHistory::class);
    }

    public function getBetTypeDisplayAttribute(): string
    {
        return match($this->bet_type) {
            'home_win' => 'Victoria Local',
            'away_win' => 'Victoria Visitante', 
            'draw' => 'Empate',
            'match_result' => 'Resultado del Partido',
            'over_2_5' => 'Más de 2.5 Goles',
            'over_under_2_5' => 'Más de 2.5 Goles',
            'under_2_5' => 'Menos de 2.5 Goles',
            'over_1_5' => 'Más de 1.5 Goles',
            'under_1_5' => 'Menos de 1.5 Goles',
            'over_0_5' => 'Más de 0.5 Goles',
            'under_0_5' => 'Menos de 0.5 Goles',
            'both_teams_score' => 'Ambos Equipos Marcan',
            'over_0_5_first_half' => 'Over 0.5 1T',
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
            case 'match_result':
                // Inferir qué se apostó basándose en el resultado y el status actual
                $won = $this->inferMatchResultBet($match);
                break;
            case 'over_2_5':
            case 'over_under_2_5':
                // Asumimos que over_under_2_5 es "over 2.5" basado en el contexto
                $won = ($match->home_goals + $match->away_goals) > 2.5;
                break;
            case 'under_2_5':
                $won = ($match->home_goals + $match->away_goals) < 2.5;
                break;
            case 'over_1_5':
                $won = ($match->home_goals + $match->away_goals) > 1.5;
                break;
            case 'under_1_5':
                $won = ($match->home_goals + $match->away_goals) < 1.5;
                break;
            case 'over_0_5':
                $won = ($match->home_goals + $match->away_goals) > 0.5;
                break;
            case 'under_0_5':
                $won = ($match->home_goals + $match->away_goals) < 0.5;
                break;
            case 'both_teams_score':
                $won = $match->home_goals > 0 && $match->away_goals > 0;
                break;
            case 'over_0_5_first_half':
                // Note: First half goals data not currently available in FootballMatch model
                // For now, use prediction accuracy from MatchPrediction if available
                if ($match->prediction && !is_null($match->prediction->over_0_5_first_half_correct)) {
                    $won = $match->prediction->over_0_5_first_half_correct;
                } else {
                    // Fallback: Cannot determine result without first half data
                    return ['status' => 'pending', 'profit' => 0];
                }
                break;
        }

        $profit = $won ? ($this->amount * $this->odds) - $this->amount : -$this->amount;
        
        return [
            'status' => $won ? 'won' : 'lost',
            'profit' => $profit,
        ];
    }

    /**
     * Infer what was bet for match_result type based on stored result and odds
     */
    private function inferMatchResultBet($match): bool
    {
        // Si la apuesta fue ganada, inferir qué resultado se apostó
        if ($this->status === 'won') {
            if ($match->home_goals > $match->away_goals) {
                return true; // Se apostó victoria local
            } elseif ($match->home_goals < $match->away_goals) {
                return true; // Se apostó victoria visitante
            } else {
                return true; // Se apostó empate
            }
        }
        
        // Si la apuesta fue perdida, verificar que efectivamente se perdió
        if ($this->status === 'lost') {
            // La apuesta se perdió, así que el resultado no coincide con lo apostado
            return false;
        }
        
        // Para apuestas pendientes, intentar inferir basándose en las odds
        // (esto es más complejo y puede requerir análisis de patrones)
        
        // Por ahora, si no podemos inferir, devolver el estado almacenado
        return $this->status === 'won';
    }
}