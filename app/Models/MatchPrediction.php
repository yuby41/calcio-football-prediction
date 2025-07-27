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
        'confidence_score' => 'decimal:4',
        'features_used' => 'array',
        'predicted_at' => 'datetime',
        'is_correct' => 'boolean',
        'both_teams_score_correct' => 'boolean',
        'over_under_correct' => 'boolean',
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

        $actualResult = $this->match->result;
        $this->is_correct = $this->predicted_outcome === $actualResult;
        $this->save();
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
}