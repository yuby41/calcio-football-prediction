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
            'first_half_over_0_5' => 'Over 0.5 1T',
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
        // Check if match is finished, regardless of bet status
        if (!$this->match || $this->match->status !== 'finished') {
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
            case 'first_half_over_0_5':
                // Use REAL first half goals data if available
                if (!is_null($match->home_goals_first_half) && !is_null($match->away_goals_first_half)) {
                    $firstHalfGoals = $match->home_goals_first_half + $match->away_goals_first_half;
                    $won = $firstHalfGoals > 0.5;
                } else {
                    // Fallback: For finished matches without first half data, use conservative inference
                    // If the final result is 0-0, assume first half was also 0-0 (lost bet)
                    // For any other final result, we cannot safely infer without risking false positives
                    $totalGoals = $match->home_goals + $match->away_goals;
                    if ($totalGoals === 0) {
                        // If final is 0-0, very likely first half was also 0-0
                        $won = false;
                    } else {
                        // For other results, mark as pending to avoid incorrect resolution
                        return ['status' => 'pending', 'profit' => 0];
                    }
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
        // Si la apuesta ya fue ganada o perdida, usar el estado existente
        if ($this->status === 'won') {
            return true;
        }
        if ($this->status === 'lost') {
            return false;
        }
        
        // Para apuestas pendientes, inferir basándose en las odds
        $actualResult = $match->result; // home_win, draw, away_win
        
        // Inferir qué se apostó basándose en las odds típicas
        $inferredBet = $this->inferBetTypeFromOdds();
        
        // Comparar el resultado real con lo que se infirió
        return $actualResult === $inferredBet;
    }
    
    /**
     * Infer what was bet based on odds ranges
     */
    private function inferBetTypeFromOdds(): string
    {
        $odds = $this->odds;
        
        // Basándose en rangos de odds típicos:
        // Home win: 1.2 - 3.0 (más común: 1.5 - 2.5)
        // Draw: 2.8 - 4.5 (más común: 3.0 - 4.0) 
        // Away win: 1.8 - 8.0+ (más común: 2.0 - 5.0)
        
        if ($odds >= 1.2 && $odds <= 2.1) {
            // Odds bajas = favorito = probablemente victoria local
            return 'home_win';
        } elseif ($odds >= 2.8 && $odds <= 4.5) {
            // Odds medias-altas = probablemente empate
            return 'draw';
        } elseif ($odds >= 2.1 && $odds <= 2.8) {
            // Rango ambiguo - podría ser home_win con odds altas o away_win con odds bajas
            // Usar contexto adicional si está disponible
            // Por defecto asumir victoria local si las odds no son muy altas
            return $odds <= 2.4 ? 'home_win' : 'away_win';
        } else {
            // Odds muy altas = underdog = probablemente victoria visitante
            return 'away_win';
        }
    }
}