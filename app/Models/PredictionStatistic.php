<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class PredictionStatistic extends Model
{
    use HasFactory;

    protected $fillable = [
        'prediction_type',
        'total_predictions',
        'correct_predictions',
        'accuracy_percentage',
        'monthly_stats',
        'league_stats',
        'last_updated',
    ];

    protected $casts = [
        'monthly_stats' => 'array',
        'league_stats' => 'array',
        'last_updated' => 'date',
        'accuracy_percentage' => 'decimal:2',
    ];

    const PREDICTION_TYPES = [
        'match_outcome' => 'Resultado del Partido',
        'both_teams_score_yes' => 'Gol',
        'both_teams_score_no' => 'No Gol',
        'over_2_5' => 'Over 2.5 Goles',
        'under_2_5' => 'Under 2.5 Goles',
        'first_half_over_0_5' => 'Over 0.5 Goles (1T)',
    ];

    public function getDisplayNameAttribute(): string
    {
        return self::PREDICTION_TYPES[$this->prediction_type] ?? $this->prediction_type;
    }

    public function getSuccessRateAttribute(): float
    {
        return $this->total_predictions > 0 
            ? round(($this->correct_predictions / $this->total_predictions) * 100, 2)
            : 0;
    }

    public function getMonthlyAccuracyForChart(): array
    {
        $monthlyStats = $this->monthly_stats ?? [];
        $labels = [];
        $accuracies = [];

        // Last 12 months
        for ($i = 11; $i >= 0; $i--) {
            $month = Carbon::now()->subMonths($i)->format('Y-m');
            $monthName = Carbon::now()->subMonths($i)->format('M Y');
            
            $labels[] = $monthName;
            $accuracies[] = $monthlyStats[$month]['accuracy'] ?? 0;
        }

        return [
            'labels' => $labels,
            'data' => $accuracies
        ];
    }

    public function getLeagueStatsForChart(): array
    {
        $leagueStats = $this->league_stats ?? [];
        $labels = [];
        $accuracies = [];

        // Map of league codes to readable names
        $leagueNames = [
            'PL' => 'Premier League',
            'PD' => 'La Liga',
            'BL1' => 'Bundesliga',
            'SA' => 'Serie A',
            'FL1' => 'Ligue 1',
            'PPL' => 'Primeira Liga',
            'DED' => 'Eredivisie',
            'BSA' => 'Brasileirão',
            'Championship' => 'Championship',
            'Unknown' => 'Otras Ligas',
        ];

        foreach ($leagueStats as $league => $stats) {
            $leagueName = $leagueNames[$league] ?? $league;
            $labels[] = $leagueName;
            $accuracies[] = $stats['accuracy'] ?? 0;
        }

        return [
            'labels' => $labels,
            'data' => $accuracies
        ];
    }
}