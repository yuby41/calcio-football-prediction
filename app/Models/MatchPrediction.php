<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchPrediction extends Model
{
    use HasFactory;

    protected $fillable = [
        'match_id',
        'home_goals_prediction',
        'away_goals_prediction',
        'home_win_probability',
        'draw_probability',
        'away_win_probability',
        'predicted_outcome',
        'both_teams_score_probability',
        'over_2_5_probability',
        'under_2_5_probability',
        'first_half_over_0_5_probability',
        'first_half_over_0_5_correct',
        'first_half_over_1_5_probability',
        'first_half_over_1_5_correct',
        'home_goals_first_half_prediction',
        'away_goals_first_half_prediction',
        'confidence_score',
        'model_version',
        'features_used',
        'predicted_at',
        'is_correct',
        'both_teams_score_correct',
        'over_under_correct',
    ];

    protected $casts = [
        'home_goals_prediction' => 'decimal:2',
        'away_goals_prediction' => 'decimal:2',
        'home_win_probability' => 'decimal:4',
        'draw_probability' => 'decimal:4',
        'away_win_probability' => 'decimal:4',
        'both_teams_score_probability' => 'decimal:4',
        'over_2_5_probability' => 'decimal:4',
        'under_2_5_probability' => 'decimal:4',
        'first_half_over_0_5_probability' => 'decimal:4',
        'first_half_over_1_5_probability' => 'decimal:4',
        'home_goals_first_half_prediction' => 'decimal:2',
        'away_goals_first_half_prediction' => 'decimal:2',
        'confidence_score' => 'decimal:4',
        'features_used' => 'array',
        'predicted_at' => 'datetime',
        'is_correct' => 'boolean',
        'both_teams_score_correct' => 'boolean',
        'over_under_correct' => 'boolean',
        'first_half_over_0_5_correct' => 'boolean',
        'first_half_over_1_5_correct' => 'boolean',
    ];

    public function match(): BelongsTo
    {
        return $this->belongsTo(FootballMatch::class, 'match_id');
    }

    public function getTotalGoalsPredictionAttribute(): float
    {
        return round($this->home_goals_prediction + $this->away_goals_prediction, 2);
    }

    public function getMostLikelyOutcomeAttribute(): string
    {
        $probabilities = [
            'home_win' => $this->home_win_probability,
            'draw' => $this->draw_probability,
            'away_win' => $this->away_win_probability,
        ];

        return array_keys($probabilities, max($probabilities))[0];
    }

    public function getOutcomeProbabilityAttribute(): float
    {
        return match ($this->most_likely_outcome) {
            'home_win' => $this->home_win_probability,
            'draw' => $this->draw_probability,
            'away_win' => $this->away_win_probability,
            default => 0,
        };
    }

    public function checkAccuracy(): void
    {
        if (!$this->match->isFinished()) {
            return;
        }

        $match = $this->match;
        
        // Check main outcome prediction
        $actualResult = $match->result;
        $this->is_correct = $this->predicted_outcome === $actualResult;
        
        // Check both teams score prediction
        $actualBothScore = ($match->home_goals > 0 && $match->away_goals > 0);
        $predictedBothScore = $this->both_teams_score_probability > 0.5;
        $this->both_teams_score_correct = $actualBothScore === $predictedBothScore;
        
        // Check over/under 2.5 prediction
        $actualTotalGoals = $match->home_goals + $match->away_goals;
        $predictedOver25 = $this->over_2_5_probability > $this->under_2_5_probability;
        $actualOver25 = $actualTotalGoals > 2.5;
        $this->over_under_correct = $actualOver25 === $predictedOver25;
        
        // Check first half over 0.5 prediction if data available
        if (!is_null($match->home_goals_first_half) && !is_null($match->away_goals_first_half)) {
            $actualFirstHalfGoals = $match->home_goals_first_half + $match->away_goals_first_half;
            $predictedFirstHalfOver05 = $this->first_half_over_0_5_probability > 0.5;
            $actualFirstHalfOver05 = $actualFirstHalfGoals > 0.5;
            $this->first_half_over_0_5_correct = $actualFirstHalfOver05 === $predictedFirstHalfOver05;
        }
        
        $this->save();
    }
    
    /**
     * Get overall prediction accuracy as a percentage
     */
    public function getOverallAccuracyAttribute(): ?float
    {
        if (!$this->match->isFinished()) {
            return null;
        }
        
        $predictions = [];
        
        // Main outcome (always include if match is finished)
        if (!is_null($this->is_correct)) {
            $predictions[] = $this->is_correct;
        }
        
        // Both teams score
        if (!is_null($this->both_teams_score_correct)) {
            $predictions[] = $this->both_teams_score_correct;
        }
        
        // Over/Under 2.5
        if (!is_null($this->over_under_correct)) {
            $predictions[] = $this->over_under_correct;
        }
        
        // First half over 0.5
        if (!is_null($this->first_half_over_0_5_correct)) {
            $predictions[] = $this->first_half_over_0_5_correct;
        }
        
        // If no predictions are set, return 0 instead of null for finished matches
        if (empty($predictions)) {
            return 0.0;
        }
        
        // Filter out null values to avoid counting them as false
        $validPredictions = array_filter($predictions, function($prediction) {
            return !is_null($prediction);
        });
        
        if (empty($validPredictions)) {
            return 0.0;
        }
        
        $correctPredictions = count(array_filter($validPredictions, function($prediction) {
            return $prediction === true;
        }));
        $totalPredictions = count($validPredictions);
        
        return round(($correctPredictions / $totalPredictions) * 100, 1);
    }
    
    /**
     * Get detailed accuracy breakdown
     */
    public function getAccuracyBreakdownAttribute(): array
    {
        if (!$this->match->isFinished()) {
            return [];
        }
        
        $breakdown = [];
        
        // Main result
        $breakdown['result'] = [
            'prediction' => $this->predicted_outcome,
            'actual' => $this->match->result,
            'correct' => $this->is_correct,
            'label' => 'Resultado'
        ];
        
        // Both teams score
        if (!is_null($this->both_teams_score_correct)) {
            $actualBothScore = ($this->match->home_goals > 0 && $this->match->away_goals > 0);
            $predictedBothScore = $this->both_teams_score_probability > 0.5;
            
            $breakdown['both_teams_score'] = [
                'prediction' => $predictedBothScore ? 'Sí' : 'No',
                'actual' => $actualBothScore ? 'Sí' : 'No',
                'correct' => $this->both_teams_score_correct,
                'label' => 'Ambos Anotan'
            ];
        }
        
        // Over/Under 2.5
        if (!is_null($this->over_under_correct)) {
            $predictedOver25 = $this->over_2_5_probability > $this->under_2_5_probability;
            $actualTotalGoals = $this->match->home_goals + $this->match->away_goals;
            $actualOver25 = $actualTotalGoals > 2.5;
            
            $breakdown['over_under'] = [
                'prediction' => $predictedOver25 ? 'Over 2.5' : 'Under 2.5',
                'actual' => $actualOver25 ? 'Over 2.5' : 'Under 2.5',
                'correct' => $this->over_under_correct,
                'label' => 'Total Goles'
            ];
        }
        
        return $breakdown;
    }

    public function getConfidenceLevelAttribute(): string
    {
        if ($this->confidence_score >= 0.8) {
            return 'Muy alta';
        } elseif ($this->confidence_score >= 0.6) {
            return 'Alta';
        } elseif ($this->confidence_score >= 0.4) {
            return 'Media';
        } else {
            return 'Baja';
        }
    }

    public function getBothTeamsScorePredictionAttribute(): string
    {
        return $this->both_teams_score_probability > 0.5 ? 'Sí' : 'No';
    }

    public function getOver25PredictionAttribute(): string
    {
        return $this->over_2_5_probability > $this->under_2_5_probability ? 'Over 2.5' : 'Under 2.5';
    }

    public function getOver05FirstHalfPredictionAttribute(): string
    {
        return $this->first_half_over_0_5_probability > 0.5 ? 'Over 0.5 1T' : 'Under 0.5 1T';
    }

    public function getTotalFirstHalfGoalsPredictionAttribute(): float
    {
        return round($this->home_goals_first_half_prediction + $this->away_goals_first_half_prediction, 2);
    }

    /**
     * Get normalized win probabilities that sum to 100%
     */
    public function getNormalizedWinProbabilitiesAttribute(): array
    {
        $home = $this->home_win_probability ?? 0;
        $draw = $this->draw_probability ?? 0;
        $away = $this->away_win_probability ?? 0;
        
        $total = $home + $draw + $away;
        
        // If total is 0 or very close to 0, return equal probabilities
        if ($total < 0.001) {
            return [
                'home' => 33.3,
                'draw' => 33.3,
                'away' => 33.4
            ];
        }
        
        // Normalize to sum to 100%
        return [
            'home' => round(($home / $total) * 100, 1),
            'draw' => round(($draw / $total) * 100, 1),
            'away' => round(($away / $total) * 100, 1)
        ];
    }
}