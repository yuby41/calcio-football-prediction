<?php

namespace App\Http\Controllers;

use App\Models\PredictionStatistic;
use App\Models\MatchPrediction;
use App\Models\FootballMatch;
use App\Services\StatisticsService;
use App\Services\SimpleAccuracyService;
use App\Services\CacheService;
use Illuminate\Http\Request;
use Carbon\Carbon;

class StatisticsController extends Controller
{
    public function __construct(private StatisticsService $statisticsService)
    {
    }

    public function index()
    {
        // Get prediction statistics with limits to prevent timeouts
        $statistics = PredictionStatistic::select('prediction_type', 'total_predictions', 'correct_predictions', 'accuracy_percentage')
            ->limit(100)
            ->get()
            ->keyBy('prediction_type');
        
        // Get recent performance data for charts with optimized query
        $recentMatches = FootballMatch::select('id', 'match_date', 'home_team_id', 'away_team_id', 'home_goals', 'away_goals', 'status')
            ->with(['prediction:id,match_id,predicted_outcome,confidence_score'])
            ->where('status', 'finished')
            ->whereHas('prediction')
            ->orderBy('match_date', 'desc')
            ->limit(50)
            ->get();

        // Calculate overall accuracy using SimpleAccuracyService
        $overallAccuracy = SimpleAccuracyService::getCurrentAccuracy();

        // Display names for prediction types
        $displayNames = [
            'match_outcome' => 'Resultado del Partido',
            'both_teams_score_yes' => 'Ambos Anotan - Sí',
            'both_teams_score_no' => 'Ambos Anotan - No',
            'over_2_5' => 'Más de 2.5 Goles',
            'under_2_5' => 'Menos de 2.5 Goles',
            'first_half_over_0_5' => '1er Tiempo +0.5 Goles'
        ];

        return view('statistics.index', compact(
            'statistics', 
            'recentMatches', 
            'overallAccuracy',
            'displayNames'
        ));
    }

    public function debugStats()
    {
        $statistics = PredictionStatistic::select('prediction_type', 'total_predictions', 'correct_predictions', 'accuracy_percentage')
            ->get()
            ->keyBy('prediction_type');
            
        return response()->json([
            'statistics_count' => $statistics->count(),
            'statistics_data' => $statistics->toArray(),
            'prediction_types' => $statistics->keys()->toArray()
        ]);
    }
    
    public function debugView()
    {
        // Get exact same data as index() method
        $statistics = PredictionStatistic::select('prediction_type', 'total_predictions', 'correct_predictions', 'accuracy_percentage')
            ->limit(100)
            ->get()
            ->keyBy('prediction_type');
            
        $displayNames = [
            'match_outcome' => 'Resultado del Partido',
            'both_teams_score_yes' => 'Ambos Anotan - Sí',
            'both_teams_score_no' => 'Ambos Anotan - No',
            'over_2_5' => 'Más de 2.5 Goles',
            'under_2_5' => 'Menos de 2.5 Goles',
            'first_half_over_0_5' => '1er Tiempo +0.5 Goles'
        ];
        
        $debugInfo = [];
        foreach(['match_outcome', 'both_teams_score_yes', 'both_teams_score_no', 'over_2_5', 'under_2_5', 'first_half_over_0_5'] as $type) {
            $stat = $statistics[$type] ?? null;
            $debugInfo[$type] = [
                'stat_exists' => $stat !== null,
                'display_name' => $displayNames[$type] ?? $type,
                'accuracy' => $stat ? $stat->accuracy_percentage . '%' : 'N/A',
                'ratio' => $stat ? $stat->correct_predictions . '/' . $stat->total_predictions : '0/0',
                'raw_data' => $stat ? $stat->toArray() : null
            ];
        }
        
        return response()->json([
            'debug_info' => $debugInfo,
            'all_statistics' => $statistics->toArray()
        ]);
    }
    
    public function cardValues()
    {
        // Get exact same data as index() method
        $statistics = PredictionStatistic::select('prediction_type', 'total_predictions', 'correct_predictions', 'accuracy_percentage')
            ->limit(100)
            ->get()
            ->keyBy('prediction_type');
        
        $cardData = [];
        $types = ['match_outcome', 'both_teams_score_yes', 'both_teams_score_no', 'over_2_5', 'under_2_5', 'first_half_over_0_5'];
        
        $displayNames = [
            'match_outcome' => 'Resultado del Partido',
            'both_teams_score_yes' => 'Ambos Anotan - Sí',
            'both_teams_score_no' => 'Ambos Anotan - No',
            'over_2_5' => 'Más de 2.5 Goles',
            'under_2_5' => 'Menos de 2.5 Goles',
            'first_half_over_0_5' => '1er Tiempo +0.5 Goles'
        ];
        
        foreach($types as $type) {
            $stat = $statistics[$type] ?? null;
            $cardData[] = [
                'type' => $type,
                'name' => $displayNames[$type] ?? $type,
                'percentage' => $stat ? $stat->accuracy_percentage . '%' : 'N/A',
                'ratio' => $stat ? $stat->correct_predictions . '/' . $stat->total_predictions : '0/0',
                'has_data' => $stat !== null
            ];
        }
        
        return response()->json(['cards' => $cardData]);
    }

    public function chartData(Request $request)
    {
        $type = $request->get('type') ?: 'match_outcome'; // Ensure non-null default
        $period = $request->get('period') ?: 'monthly'; // Ensure non-null default
        
        $cacheService = app(CacheService::class);
        $chartData = $cacheService->rememberStatistics("chart:{$type}:{$period}", function () use ($type, $period) {
            return match($period) {
                'daily' => $this->getDailyAccuracyData($type),
                'weekly' => $this->getWeeklyAccuracyData($type),
                'monthly' => $this->getMonthlyAccuracyData($type),
                'yearly' => $this->getYearlyAccuracyData($type),
                default => $this->getMonthlyAccuracyData($type)
            };
        });

        return response()->json($chartData);
    }

    public function leagueData(Request $request)
    {
        try {
            $type = $request->get('type', 'match_outcome');
            
            $stat = PredictionStatistic::where('prediction_type', $type)->first();
            
            if (!$stat || !$stat->league_stats) {
                return response()->json(['labels' => [], 'data' => []]);
            }

        $leagueStats = $stat->league_stats;
        
        // Filter leagues with at least 3 matches for meaningful statistics and clean UTF-8
        $filteredStats = [];
        foreach ($leagueStats as $league => $stats) {
            if (($stats['total'] ?? 0) >= 3) {
                // Clean the league name
                $cleanLeague = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $league);
                $cleanLeague = mb_convert_encoding($cleanLeague, 'UTF-8', 'UTF-8');
                if (!empty($cleanLeague)) {
                    $filteredStats[$cleanLeague] = $stats;
                }
            }
        }
        
        // Sort by accuracy descending
        uasort($filteredStats, function($a, $b) {
            return ($b['accuracy'] ?? 0) <=> ($a['accuracy'] ?? 0);
        });
        
        // Take top 10 leagues
        $topLeagues = array_slice($filteredStats, 0, 10, true);
        
        $labels = [];
        $accuracies = [];
        $colors = [];
        
        // Generate colors for each league
        $colorPalette = [
            'rgba(59, 130, 246, 0.8)',   // Blue
            'rgba(34, 197, 94, 0.8)',    // Green
            'rgba(147, 51, 234, 0.8)',   // Purple
            'rgba(245, 158, 11, 0.8)',   // Yellow
            'rgba(239, 68, 68, 0.8)',    // Red
            'rgba(16, 185, 129, 0.8)',   // Emerald
            'rgba(249, 115, 22, 0.8)',   // Orange
            'rgba(139, 92, 246, 0.8)',   // Violet
            'rgba(236, 72, 153, 0.8)',   // Pink
            'rgba(14, 165, 233, 0.8)',   // Sky
        ];
        
        $colorIndex = 0;
        foreach ($topLeagues as $league => $stats) {
            // League name should already be clean from previous step
            $displayName = strlen($league) > 25 ? substr($league, 0, 22) . '...' : $league;
            
            $labels[] = $displayName;
            $accuracies[] = $stats['accuracy'] ?? 0;
            $colors[] = $colorPalette[$colorIndex % count($colorPalette)];
            $colorIndex++;
        }

            return response()->json([
                'labels' => $labels,
                'data' => $accuracies,
                'colors' => $colors,
                'fullNames' => array_keys($topLeagues) // Already cleaned
            ]);
        } catch (\Exception $e) {
            \Log::error('Error in leagueData: ' . $e->getMessage());
            return response()->json(['labels' => [], 'data' => [], 'error' => $e->getMessage()]);
        }
    }

    public function refresh()
    {
        $this->statisticsService->updateAllStatistics();
        
        return redirect()->route('statistics.index')
            ->with('success', 'Estadísticas actualizadas correctamente');
    }

    public function detailData(Request $request)
    {
        $type = $request->get('type');
        
        $data = match($type) {
            'both_teams_score_yes' => $this->getBothTeamsScoreYesDetails(),
            'both_teams_score_no' => $this->getBothTeamsScoreNoDetails(),
            'over_2_5' => $this->getOver25Details(),
            'under_2_5' => $this->getUnder25Details(),
            'first_half_over_0_5' => $this->getFirstHalfOver05Details(),
            'match_outcome' => $this->getMatchOutcomeDetails(),
            default => null
        };

        return response()->json($data);
    }

    private function getBothTeamsScoreYesDetails()
    {
        $matches = FootballMatch::with(['prediction', 'homeTeam', 'awayTeam'])
            ->where('status', 'finished')
            ->whereHas('prediction', function($query) {
                $query->whereNotNull('both_teams_score_probability');
            })
            ->orderBy('match_date', 'desc')
            ->limit(20)
            ->get();

        $details = [];
        
        foreach ($matches as $match) {
            $actualBothScored = $match->home_goals > 0 && $match->away_goals > 0;
            $predictedBothScore = $match->prediction->both_teams_score_probability > 0.5;
            $correct = $actualBothScored === $predictedBothScore;

            $details[] = [
                'match' => $match->homeTeam->name . ' vs ' . $match->awayTeam->name,
                'date' => $match->match_date->format('d/m/Y'),
                'score' => $match->home_goals . '-' . $match->away_goals,
                'actual' => $actualBothScored ? 'Sí' : 'No',
                'predicted' => $predictedBothScore ? 'Sí' : 'No',
                'confidence' => round($match->prediction->both_teams_score_probability * 100, 1) . '%',
                'correct' => $correct
            ];
        }

        return $details;
    }

    private function getBothTeamsScoreNoDetails()
    {
        $matches = FootballMatch::with(['prediction', 'homeTeam', 'awayTeam'])
            ->where('status', 'finished')
            ->whereHas('prediction', function($query) {
                $query->whereNotNull('both_teams_score_probability');
            })
            ->orderBy('match_date', 'desc')
            ->limit(20)
            ->get();

        $details = [];
        
        foreach ($matches as $match) {
            $actualBothScored = $match->home_goals > 0 && $match->away_goals > 0;
            $actualNotBothScored = !$actualBothScored; // Al menos un equipo no anotó
            $predictedNotBothScore = $match->prediction->both_teams_score_probability <= 0.5;
            $correct = $actualNotBothScored === $predictedNotBothScore;

            $details[] = [
                'match' => $match->homeTeam->name . ' vs ' . $match->awayTeam->name,
                'date' => $match->match_date->format('d/m/Y'),
                'score' => $match->home_goals . '-' . $match->away_goals,
                'actual' => $actualNotBothScored ? 'Sí' : 'No', // "Sí" significa al menos uno no anotó
                'predicted' => $predictedNotBothScore ? 'Sí' : 'No',
                'confidence' => round((1 - $match->prediction->both_teams_score_probability) * 100, 1) . '%',
                'correct' => $correct
            ];
        }

        return $details;
    }

    private function getOver25Details()
    {
        $matches = FootballMatch::with(['prediction', 'homeTeam', 'awayTeam'])
            ->where('status', 'finished')
            ->whereHas('prediction', function($query) {
                $query->whereNotNull('over_2_5_probability')
                      ->where('over_2_5_probability', '>', 0.5); // Solo predicciones Over 2.5
            })
            ->orderBy('match_date', 'desc')
            ->limit(20)
            ->get();

        $details = [];
        
        foreach ($matches as $match) {
            $totalGoals = $match->home_goals + $match->away_goals;
            $actualOver25 = $totalGoals > 2.5;
            $predictedOver25 = true; // Siempre verdadero por el filtro arriba
            $correct = $actualOver25 === $predictedOver25;

            $details[] = [
                'match' => $match->homeTeam->name . ' vs ' . $match->awayTeam->name,
                'date' => $match->match_date->format('d/m/Y'),
                'score' => $match->home_goals . '-' . $match->away_goals,
                'total_goals' => $totalGoals,
                'actual' => $actualOver25 ? 'Sí' : 'No',
                'predicted' => 'Sí', // Siempre "Sí" porque solo mostramos predicciones Over
                'confidence' => round($match->prediction->over_2_5_probability * 100, 1) . '%',
                'correct' => $correct
            ];
        }

        return $details;
    }

    private function getUnder25Details()
    {
        $matches = FootballMatch::with(['prediction', 'homeTeam', 'awayTeam'])
            ->where('status', 'finished')
            ->whereRaw('(home_goals + away_goals) <= 2.5') // Solo partidos que fueron realmente Under 2.5
            ->whereHas('prediction', function($query) {
                $query->whereNotNull('over_2_5_probability'); // Usamos over_2_5 porque under se calcula
            })
            ->orderBy('match_date', 'desc')
            ->limit(20)
            ->get();

        $details = [];
        
        foreach ($matches as $match) {
            $totalGoals = $match->home_goals + $match->away_goals;
            $actualUnder25 = true; // Siempre verdadero por el filtro arriba
            $predictedUnder25 = $match->prediction->over_2_5_probability <= 0.5; // Under = !Over
            $correct = $actualUnder25 === $predictedUnder25;

            $details[] = [
                'match' => $match->homeTeam->name . ' vs ' . $match->awayTeam->name,
                'date' => $match->match_date->format('d/m/Y'),
                'score' => $match->home_goals . '-' . $match->away_goals,
                'total_goals' => $totalGoals,
                'actual' => 'Sí', // Siempre "Sí" porque solo mostramos partidos Under reales
                'predicted' => $predictedUnder25 ? 'Sí' : 'No',
                'confidence' => round((1 - $match->prediction->over_2_5_probability) * 100, 1) . '%',
                'correct' => $correct
            ];
        }

        return $details;
    }

    private function getMatchOutcomeDetails()
    {
        $matches = FootballMatch::with(['prediction', 'homeTeam', 'awayTeam'])
            ->where('status', 'finished')
            ->whereHas('prediction')
            ->whereNotNull('home_goals')
            ->whereNotNull('away_goals')
            ->orderBy('match_date', 'desc')
            ->limit(20)
            ->get();

        $details = [];
        
        foreach ($matches as $match) {
            $details[] = [
                'match' => $match->homeTeam->name . ' vs ' . $match->awayTeam->name,
                'date' => $match->match_date->format('d/m/Y'),
                'score' => $match->home_goals . '-' . $match->away_goals,
                'actual' => $this->getOutcomeText($match),
                'predicted' => $this->getPredictedOutcomeText($match->prediction->predicted_outcome),
                'confidence' => round($match->prediction->confidence_score * 100, 1) . '%',
                'correct' => $match->prediction->is_correct ?? false
            ];
        }

        return $details;
    }

    private function getOutcomeText(FootballMatch $match): string
    {
        if ($match->home_goals > $match->away_goals) {
            return 'Victoria Local';
        } elseif ($match->home_goals < $match->away_goals) {
            return 'Victoria Visitante';
        } else {
            return 'Empate';
        }
    }

    private function getPredictedOutcomeText(string $outcome): string
    {
        return match($outcome) {
            'home_win' => 'Victoria Local',
            'away_win' => 'Victoria Visitante',
            'draw' => 'Empate',
            default => $outcome
        };
    }

    private function getDailyAccuracyData(string $type = 'match_outcome'): array
    {
        $matches = $this->getMatchesForType($type);
        $dailyStats = [];

        foreach ($matches as $match) {
            $date = $match->match_date->format('Y-m-d');
            
            if (!isset($dailyStats[$date])) {
                $dailyStats[$date] = ['total' => 0, 'correct' => 0];
            }
            
            $dailyStats[$date]['total']++;
            
            if ($this->isPredictionCorrect($match, $type)) {
                $dailyStats[$date]['correct']++;
            }
        }

        // Get last 30 days
        $labels = [];
        $accuracies = [];
        
        for ($i = 29; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i)->format('Y-m-d');
            $labels[] = Carbon::now()->subDays($i)->format('d/m');
            
            if (isset($dailyStats[$date]) && $dailyStats[$date]['total'] > 0) {
                $accuracies[] = round(($dailyStats[$date]['correct'] / $dailyStats[$date]['total']) * 100, 1);
            } else {
                $accuracies[] = null;
            }
        }

        return [
            'labels' => $labels,
            'data' => $accuracies
        ];
    }

    private function getWeeklyAccuracyData(string $type = 'match_outcome'): array
    {
        $matches = $this->getMatchesForType($type);
        $weeklyStats = [];

        foreach ($matches as $match) {
            $week = $match->match_date->format('Y-W');
            
            if (!isset($weeklyStats[$week])) {
                $weeklyStats[$week] = ['total' => 0, 'correct' => 0];
            }
            
            $weeklyStats[$week]['total']++;
            
            if ($this->isPredictionCorrect($match, $type)) {
                $weeklyStats[$week]['correct']++;
            }
        }

        // Get last 12 weeks
        $labels = [];
        $accuracies = [];
        
        for ($i = 11; $i >= 0; $i--) {
            $week = Carbon::now()->subWeeks($i)->format('Y-W');
            $labels[] = 'Sem ' . Carbon::now()->subWeeks($i)->format('W');
            
            if (isset($weeklyStats[$week]) && $weeklyStats[$week]['total'] > 0) {
                $accuracies[] = round(($weeklyStats[$week]['correct'] / $weeklyStats[$week]['total']) * 100, 1);
            } else {
                $accuracies[] = null;
            }
        }

        return [
            'labels' => $labels,
            'data' => $accuracies
        ];
    }

    private function getMonthlyAccuracyData(string $type = 'match_outcome'): array
    {
        // For monthly charts, we need ALL historical data in the time range
        $matches = $this->getAllMatchesForType($type);
        $monthlyStats = [];

        foreach ($matches as $match) {
            $month = $match->match_date->format('Y-m');
            
            if (!isset($monthlyStats[$month])) {
                $monthlyStats[$month] = ['total' => 0, 'correct' => 0];
            }
            
            $monthlyStats[$month]['total']++;
            
            if ($this->isPredictionCorrect($match, $type)) {
                $monthlyStats[$month]['correct']++;
            }
        }

        // Generate data from oldest to newest (last 24 months max for performance)
        $labels = [];
        $accuracies = [];
        
        // Get date range from actual data
        $oldestMatch = FootballMatch::whereHas('prediction')->orderBy('match_date')->first();
        $startDate = $oldestMatch ? $oldestMatch->match_date : Carbon::now()->subMonths(24);
        
        // Limit to last 24 months for performance  
        $startDate = max($startDate, Carbon::now()->subMonths(24));
        
        $current = Carbon::parse($startDate)->startOfMonth();
        $end = Carbon::now()->endOfMonth();
        
        while ($current <= $end) {
            $month = $current->format('Y-m');
            $labels[] = $current->format('M Y');
            
            if (isset($monthlyStats[$month]) && $monthlyStats[$month]['total'] > 0) {
                $accuracies[] = round(($monthlyStats[$month]['correct'] / $monthlyStats[$month]['total']) * 100, 1);
            } else {
                $accuracies[] = null;
            }
            
            $current->addMonth();
        }

        return [
            'labels' => $labels,
            'data' => $accuracies
        ];
    }

    private function getYearlyAccuracyData(string $type = 'match_outcome'): array
    {
        // For yearly data, we need ALL historical data, not just recent matches
        $matches = $this->getAllMatchesForType($type);
        $yearlyStats = [];

        foreach ($matches as $match) {
            $year = $match->match_date->format('Y');
            
            if (!isset($yearlyStats[$year])) {
                $yearlyStats[$year] = ['total' => 0, 'correct' => 0];
            }
            
            $yearlyStats[$year]['total']++;
            
            if ($this->isPredictionCorrect($match, $type)) {
                $yearlyStats[$year]['correct']++;
            }
        }

        $labels = [];
        $accuracies = [];
        
        // Sort by year to ensure chronological order
        ksort($yearlyStats);
        
        foreach ($yearlyStats as $year => $stats) {
            $labels[] = $year;
            $accuracies[] = $stats['total'] > 0 ? round(($stats['correct'] / $stats['total']) * 100, 1) : 0;
        }

        return [
            'labels' => $labels,
            'data' => $accuracies
        ];
    }

    private function getMatchesForType(?string $type = null)
    {
        // Default to match_outcome if type is null
        $type = $type ?: 'match_outcome';
        
        // Use select to only get needed fields and limit results to prevent timeouts
        $query = FootballMatch::select('id', 'match_date', 'home_team_id', 'away_team_id', 'home_goals', 'away_goals', 'status', 'league', 'home_goals_first_half', 'away_goals_first_half')
            ->with([
                'prediction:id,match_id,predicted_outcome,both_teams_score_probability,over_2_5_probability,first_half_over_0_5_probability,is_correct,first_half_over_0_5_correct',
                'homeTeam:id,name', 
                'awayTeam:id,name'
            ])
            ->where('status', 'finished')
            ->whereHas('prediction')
            ->whereNotNull('home_goals')
            ->whereNotNull('away_goals');

        if ($type === 'both_teams_score_yes' || $type === 'both_teams_score_no') {
            $query->whereHas('prediction', function($q) {
                $q->whereNotNull('both_teams_score_probability');
            });
        } elseif ($type === 'over_2_5') {
            $query->whereHas('prediction', function($q) {
                $q->whereNotNull('over_2_5_probability');
            });
        } elseif ($type === 'under_2_5') {
            $query->whereHas('prediction', function($q) {
                $q->whereNotNull('over_2_5_probability');
            });
        } elseif ($type === 'first_half_over_0_5') {
            $query->whereHas('prediction', function($q) {
                $q->whereNotNull('first_half_over_0_5_probability');
            });
        }

        // For chart data, we need more history. Limit based on date range instead
        // Only limit for recent data calls, not for historical analysis
        $query->orderBy('match_date', 'desc');
        
        // If we're looking at a specific time range for charts, don't limit too aggressively
        return $query->limit(5000)->get(); // Increased limit for better historical data
    }

    /**
     * Get ALL matches for chart data - no limits for historical analysis
     */
    private function getAllMatchesForType(?string $type = null)
    {
        // Default to match_outcome if type is null
        $type = $type ?: 'match_outcome';
        
        // Get ALL historical data for charts - no limits
        $query = FootballMatch::select('id', 'match_date', 'home_team_id', 'away_team_id', 'home_goals', 'away_goals', 'status', 'league', 'home_goals_first_half', 'away_goals_first_half')
            ->with([
                'prediction:id,match_id,predicted_outcome,both_teams_score_probability,over_2_5_probability,first_half_over_0_5_probability,is_correct,first_half_over_0_5_correct'
            ])
            ->where('status', 'finished')
            ->whereHas('prediction')
            ->whereNotNull('home_goals')
            ->whereNotNull('away_goals');

        // Same type filtering as original method
        if ($type === 'both_teams_score_yes' || $type === 'both_teams_score_no') {
            $query->whereHas('prediction', function($q) {
                $q->whereNotNull('both_teams_score_probability');
            });
        } elseif ($type === 'over_2_5') {
            $query->whereHas('prediction', function($q) {
                $q->whereNotNull('over_2_5_probability');
            });
        } elseif ($type === 'under_2_5') {
            $query->whereHas('prediction', function($q) {
                $q->whereNotNull('over_2_5_probability');
            });
        } elseif ($type === 'first_half_over_0_5') {
            $query->whereHas('prediction', function($q) {
                $q->whereNotNull('first_half_over_0_5_probability');
            });
        }

        // For historical charts, get ALL data ordered by date (oldest first for chronological display)
        return $query->orderBy('match_date', 'asc')->get();
    }

    private function isPredictionCorrect($match, ?string $type = null): bool
    {
        $prediction = $match->prediction;
        
        return match($type ?: 'match_outcome') {
            'match_outcome' => $this->isMatchOutcomeCorrect($match),
            'both_teams_score_yes' => $this->isBothTeamsScoreYesCorrect($match),
            'both_teams_score_no' => $this->isBothTeamsScoreNoCorrect($match),
            'over_2_5' => $this->isOver25Correct($match),
            'under_2_5' => $this->isUnder25Correct($match),
            'first_half_over_0_5' => $this->isFirstHalfOver05Correct($match),
            default => false
        };
    }

    private function isBothTeamsScoreYesCorrect($match): bool
    {
        $actualBothScored = $match->home_goals > 0 && $match->away_goals > 0;
        $predictedBothScore = $match->prediction->both_teams_score_probability > 0.5;
        return $actualBothScored === $predictedBothScore;
    }

    private function isBothTeamsScoreNoCorrect($match): bool
    {
        $actualBothScored = $match->home_goals > 0 && $match->away_goals > 0;
        $predictedNotBothScore = $match->prediction->both_teams_score_probability <= 0.5;
        return (!$actualBothScored) === $predictedNotBothScore;
    }

    private function isOver25Correct($match): bool
    {
        $totalGoals = $match->home_goals + $match->away_goals;
        $actualOver25 = $totalGoals > 2.5;
        $predictedOver25 = $match->prediction->over_2_5_probability > 0.5;
        return $actualOver25 === $predictedOver25;
    }

    private function isUnder25Correct($match): bool
    {
        $totalGoals = $match->home_goals + $match->away_goals;
        $actualUnder25 = $totalGoals <= 2.5;
        $predictedOver25 = $match->prediction->over_2_5_probability > 0.5;
        $predictedUnder25 = !$predictedOver25;
        return $actualUnder25 === $predictedUnder25;
    }

    private function isMatchOutcomeCorrect($match): bool
    {
        if (!$match->prediction || is_null($match->home_goals) || is_null($match->away_goals)) {
            return false;
        }

        // Determine actual outcome
        $actualOutcome = '';
        if ($match->home_goals > $match->away_goals) {
            $actualOutcome = 'home_win';
        } elseif ($match->home_goals < $match->away_goals) {
            $actualOutcome = 'away_win';
        } else {
            $actualOutcome = 'draw';
        }

        return $actualOutcome === $match->prediction->predicted_outcome;
    }

    private function isFirstHalfOver05Correct($match): bool
    {
        // Si no hay predicción o probabilidad, return false
        if (!$match->prediction || is_null($match->prediction->first_half_over_0_5_probability)) {
            return false;
        }

        // Si tenemos datos reales de primer tiempo, calcular dinámicamente
        if (!is_null($match->home_goals_first_half) && !is_null($match->away_goals_first_half)) {
            $firstHalfGoals = $match->home_goals_first_half + $match->away_goals_first_half;
            $actualOver05 = $firstHalfGoals > 0.5;
            $predictedOver05 = $match->prediction->first_half_over_0_5_probability > 0.5;
            return $actualOver05 === $predictedOver05;
        }

        // Si no hay datos de primer tiempo, usar el campo calculado si existe y no es null
        if (!is_null($match->prediction->first_half_over_0_5_correct)) {
            return (bool) $match->prediction->first_half_over_0_5_correct;
        }

        // Como último recurso, return false
        return false;
    }

    private function getFirstHalfOver05Details()
    {
        $matches = FootballMatch::with(['prediction', 'homeTeam', 'awayTeam'])
            ->where('status', 'finished')
            ->whereHas('prediction', function($query) {
                $query->whereNotNull('first_half_over_0_5_probability');
            })
            // Removed ->whereNotNull('home_goals_first_half') to show same matches as other stats
            ->orderBy('match_date', 'desc')
            ->limit(20)  // Same limit as other detail methods
            ->get();

        $details = [];
        
        foreach ($matches as $match) {
            $predictedOver05 = $match->prediction->first_half_over_0_5_probability > 0.5;
            
            // Check if we have real first half data
            if (!is_null($match->home_goals_first_half) && !is_null($match->away_goals_first_half)) {
                // Use REAL first half goals data
                $firstHalfGoals = $match->home_goals_first_half + $match->away_goals_first_half;
                $actualOver05 = $firstHalfGoals > 0.5;
                $correct = $actualOver05 === $predictedOver05;
                $scoreDisplay = $match->home_goals . '-' . $match->away_goals . ' (1T: ' . $match->home_goals_first_half . '-' . $match->away_goals_first_half . ')';
                $actualDisplay = $actualOver05 ? 'Sí' : 'No';
            } else {
                // For matches without first half data, show as pending/unknown
                $correct = null;  // Cannot determine accuracy without data
                $scoreDisplay = $match->home_goals . '-' . $match->away_goals . ' (1T: pendiente)';
                $actualDisplay = 'Pendiente';
            }

            $details[] = [
                'match' => $match->homeTeam->name . ' vs ' . $match->awayTeam->name,
                'date' => $match->match_date->format('d/m/Y'),
                'score' => $scoreDisplay,
                'actual' => $actualDisplay,
                'predicted' => $predictedOver05 ? 'Sí' : 'No',
                'confidence' => round($match->prediction->first_half_over_0_5_probability * 100, 1) . '%',
                'correct' => $correct
            ];
        }

        return $details;
    }

    private function simulateFirstHalfGoals(int $totalGoals, int $matchId): int
    {
        // Use match ID as seed for consistent simulation
        $seed = $matchId % 100;
        
        if ($totalGoals == 0) return 0;
        if ($totalGoals == 1) return $seed < 30 ? 1 : 0; // 30% chance of 1 goal in first half
        if ($totalGoals == 2) {
            if ($seed < 20) return 0;      // 20% chance of 0 goals
            if ($seed < 70) return 1;      // 50% chance of 1 goal
            return 2;                      // 30% chance of 2 goals
        }
        if ($totalGoals >= 3) {
            if ($seed < 10) return 0;      // 10% chance of 0 goals
            if ($seed < 40) return 1;      // 30% chance of 1 goal
            if ($seed < 80) return 2;      // 40% chance of 2 goals
            return min(3, $totalGoals);    // 20% chance of 3+ goals
        }
        
        return min(2, intval($totalGoals * 0.6)); // Fallback
    }

}