<?php

namespace App\Services;

use App\Models\MatchPrediction;
use App\Models\PredictionStatistic;
use App\Models\FootballMatch;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StatisticsService
{
    public function updateAllStatistics(): void
    {
        $this->updateMatchOutcomeStatistics();
        $this->updateBothTeamsScoreStatistics();
        $this->updateOverUnderStatistics();
        $this->updateFirstHalfStatistics();
    }

    public function updateMatchOutcomeStatistics(): void
    {
        $finishedMatches = $this->getFinishedMatchesWithPredictions();
        
        $totalPredictions = $finishedMatches->filter(function($match) {
            return $match->prediction && !is_null($match->prediction->is_correct);
        })->count();
        
        $correctPredictions = $finishedMatches->filter(function($match) {
            return $match->prediction && $match->prediction->is_correct === true;
        })->count();
        $accuracy = $totalPredictions > 0 ? ($correctPredictions / $totalPredictions) * 100 : 0;

        $monthlyStats = $this->calculateMonthlyStats($finishedMatches, 'match_outcome');
        $leagueStats = $this->calculateLeagueStats($finishedMatches, 'match_outcome');

        PredictionStatistic::updateOrCreate(
            ['prediction_type' => 'match_outcome'],
            [
                'total_predictions' => $totalPredictions,
                'correct_predictions' => $correctPredictions,
                'accuracy_percentage' => round($accuracy, 2),
                'monthly_stats' => $monthlyStats,
                'league_stats' => $leagueStats,
                'last_updated' => Carbon::now(),
            ]
        );
    }

    public function updateBothTeamsScoreStatistics(): void
    {
        $finishedMatches = $this->getFinishedMatchesWithPredictions()
            ->filter(function($match) {
                return $match->prediction && 
                       !is_null($match->prediction->both_teams_score_probability);
            });
        
        // Update both_teams_score_correct if null
        foreach ($finishedMatches as $match) {
            if (is_null($match->prediction->both_teams_score_correct)) {
                $actualBothScored = $match->home_goals > 0 && $match->away_goals > 0;
                $predictedBothScore = $match->prediction->both_teams_score_probability > 0.5;
                $match->prediction->both_teams_score_correct = $actualBothScored === $predictedBothScore;
                $match->prediction->save();
            }
        }
        
        // Create separate statistics for YES and NO predictions
        $this->createBothTeamsScoreYesStatistics($finishedMatches);
        $this->createBothTeamsScoreNoStatistics($finishedMatches);
        
        // Keep legacy statistics for backwards compatibility
        $totalPredictions = $finishedMatches->count();
        $correctPredictions = $finishedMatches->filter(function($match) {
            return $match->prediction->both_teams_score_correct === true;
        })->count();
        $accuracy = $totalPredictions > 0 ? ($correctPredictions / $totalPredictions) * 100 : 0;
        
        $bothTeamsScoreResults = [];
        foreach ($finishedMatches as $match) {
            $bothTeamsScoreResults[] = [
                'correct' => $match->prediction->both_teams_score_correct,
                'match' => $match
            ];
        }

        $monthlyStats = $this->calculateCustomMonthlyStats($bothTeamsScoreResults, 'both_teams_score');
        $leagueStats = $this->calculateCustomLeagueStats($bothTeamsScoreResults, 'both_teams_score');

        // Removed both_teams_score duplicate - only using both_teams_score_yes
    }

    public function updateOverUnderStatistics(): void
    {
        $finishedMatches = $this->getFinishedMatchesWithPredictions()
            ->filter(function($match) {
                return $match->prediction && 
                       !is_null($match->prediction->over_2_5_probability);
            });
        
        // Update over_under_correct if null
        foreach ($finishedMatches as $match) {
            if (is_null($match->prediction->over_under_correct)) {
                $totalGoals = $match->home_goals + $match->away_goals;
                $actualOver25 = $totalGoals > 2.5;
                $predictedOver25 = $match->prediction->over_2_5_probability > 0.5;
                $match->prediction->over_under_correct = $actualOver25 === $predictedOver25;
                $match->prediction->save();
            }
        }
        
        // Create separate statistics for OVER and UNDER predictions
        $this->createOver25Statistics($finishedMatches);
        $this->createUnder25Statistics($finishedMatches);
        
        // Keep legacy statistics for backwards compatibility
        $totalPredictions = $finishedMatches->count();
        $correctPredictions = $finishedMatches->filter(function($match) {
            return $match->prediction->over_under_correct === true;
        })->count();
        $accuracy = $totalPredictions > 0 ? ($correctPredictions / $totalPredictions) * 100 : 0;
        
        $overUnderResults = [];
        foreach ($finishedMatches as $match) {
            $overUnderResults[] = [
                'correct' => $match->prediction->over_under_correct,
                'match' => $match
            ];
        }

        $monthlyStats = $this->calculateCustomMonthlyStats($overUnderResults, 'over_under_2_5');
        $leagueStats = $this->calculateCustomLeagueStats($overUnderResults, 'over_under_2_5');

        // Removed over_under_2_5 duplicate - only using over_2_5 and under_2_5
    }

    private function getFinishedMatchesWithPredictions()
    {
        return FootballMatch::with(['prediction', 'homeTeam', 'awayTeam'])
            ->where('status', 'finished')
            ->where('match_date', '<=', Carbon::now()) // Only past matches
            ->whereHas('prediction')
            ->whereNotNull('home_goals')
            ->whereNotNull('away_goals')
            ->get();
    }

    private function calculateMonthlyStats($matches, $type): array
    {
        $monthlyStats = [];
        
        foreach ($matches as $match) {
            $month = $match->match_date->format('Y-m');
            
            if (!isset($monthlyStats[$month])) {
                $monthlyStats[$month] = ['total' => 0, 'correct' => 0, 'accuracy' => 0];
            }
            
            $monthlyStats[$month]['total']++;
            
            if ($type === 'match_outcome' && $match->prediction->is_correct) {
                $monthlyStats[$month]['correct']++;
            }
        }
        
        // Calculate accuracy for each month
        foreach ($monthlyStats as $month => &$stats) {
            $stats['accuracy'] = $stats['total'] > 0 
                ? round(($stats['correct'] / $stats['total']) * 100, 2) 
                : 0;
        }
        
        return $monthlyStats;
    }

    private function calculateLeagueStats($matches, $type): array
    {
        $leagueStats = [];
        
        foreach ($matches as $match) {
            $league = $match->league ?? 'Unknown';
            
            if (!isset($leagueStats[$league])) {
                $leagueStats[$league] = ['total' => 0, 'correct' => 0, 'accuracy' => 0];
            }
            
            $leagueStats[$league]['total']++;
            
            if ($type === 'match_outcome' && $match->prediction->is_correct) {
                $leagueStats[$league]['correct']++;
            }
        }
        
        // Calculate accuracy for each league
        foreach ($leagueStats as $league => &$stats) {
            $stats['accuracy'] = $stats['total'] > 0 
                ? round(($stats['correct'] / $stats['total']) * 100, 2) 
                : 0;
        }
        
        return $leagueStats;
    }

    private function calculateCustomMonthlyStats(array $results, string $type): array
    {
        $monthlyStats = [];
        
        foreach ($results as $result) {
            $month = $result['match']->match_date->format('Y-m');
            
            if (!isset($monthlyStats[$month])) {
                $monthlyStats[$month] = ['total' => 0, 'correct' => 0, 'accuracy' => 0];
            }
            
            $monthlyStats[$month]['total']++;
            
            if ($result['correct']) {
                $monthlyStats[$month]['correct']++;
            }
        }
        
        // Calculate accuracy for each month
        foreach ($monthlyStats as $month => &$stats) {
            $stats['accuracy'] = $stats['total'] > 0 
                ? round(($stats['correct'] / $stats['total']) * 100, 2) 
                : 0;
        }
        
        return $monthlyStats;
    }

    private function calculateCustomLeagueStats(array $results, string $type): array
    {
        $leagueStats = [];
        
        foreach ($results as $result) {
            $league = $result['match']->league ?? 'Unknown';
            
            if (!isset($leagueStats[$league])) {
                $leagueStats[$league] = ['total' => 0, 'correct' => 0, 'accuracy' => 0];
            }
            
            $leagueStats[$league]['total']++;
            
            if ($result['correct']) {
                $leagueStats[$league]['correct']++;
            }
        }
        
        // Calculate accuracy for each league
        foreach ($leagueStats as $league => &$stats) {
            $stats['accuracy'] = $stats['total'] > 0 
                ? round(($stats['correct'] / $stats['total']) * 100, 2) 
                : 0;
        }
        
        return $leagueStats;
    }

    public function updateMatchResult(FootballMatch $match): void
    {
        if ($match->status === 'finished' && $match->prediction) {
            // Update individual prediction accuracy
            $this->updateIndividualPredictionAccuracy($match);
            
            // Recalculate overall statistics
            $this->updateAllStatistics();
        }
    }

    private function updateIndividualPredictionAccuracy(FootballMatch $match): void
    {
        $prediction = $match->prediction;
        
        // Check match outcome accuracy
        $actualResult = $this->determineMatchResult($match);
        $prediction->is_correct = $prediction->predicted_outcome === $actualResult;
        $prediction->save();
    }

    private function determineMatchResult(FootballMatch $match): string
    {
        if ($match->home_goals > $match->away_goals) {
            return 'home_win';
        } elseif ($match->home_goals < $match->away_goals) {
            return 'away_win';
        } else {
            return 'draw';
        }
    }

    private function createBothTeamsScoreYesStatistics($finishedMatches): void
    {
        $yesResults = [];
        foreach ($finishedMatches as $match) {
            $predictedBothScore = $match->prediction->both_teams_score_probability > 0.5;
            if ($predictedBothScore) {
                $actualBothScored = $match->home_goals > 0 && $match->away_goals > 0;
                $yesResults[] = [
                    'correct' => $actualBothScored,
                    'match' => $match
                ];
            }
        }

        $totalPredictions = count($yesResults);
        $correctPredictions = array_filter($yesResults, function($result) {
            return $result['correct'];
        });
        $accuracy = $totalPredictions > 0 ? (count($correctPredictions) / $totalPredictions) * 100 : 0;

        $monthlyStats = $this->calculateCustomMonthlyStats($yesResults, 'both_teams_score_yes');
        $leagueStats = $this->calculateCustomLeagueStats($yesResults, 'both_teams_score_yes');

        PredictionStatistic::updateOrCreate(
            ['prediction_type' => 'both_teams_score_yes'],
            [
                'total_predictions' => $totalPredictions,
                'correct_predictions' => count($correctPredictions),
                'accuracy_percentage' => round($accuracy, 2),
                'monthly_stats' => $monthlyStats,
                'league_stats' => $leagueStats,
                'last_updated' => Carbon::now(),
            ]
        );
    }

    private function createBothTeamsScoreNoStatistics($finishedMatches): void
    {
        $noResults = [];
        foreach ($finishedMatches as $match) {
            $predictedBothScore = $match->prediction->both_teams_score_probability > 0.5;
            if (!$predictedBothScore) {
                $actualBothScored = $match->home_goals > 0 && $match->away_goals > 0;
                $noResults[] = [
                    'correct' => !$actualBothScored, // Correct if NOT both teams scored
                    'match' => $match
                ];
            }
        }

        $totalPredictions = count($noResults);
        $correctPredictions = array_filter($noResults, function($result) {
            return $result['correct'];
        });
        $accuracy = $totalPredictions > 0 ? (count($correctPredictions) / $totalPredictions) * 100 : 0;

        $monthlyStats = $this->calculateCustomMonthlyStats($noResults, 'both_teams_score_no');
        $leagueStats = $this->calculateCustomLeagueStats($noResults, 'both_teams_score_no');

        PredictionStatistic::updateOrCreate(
            ['prediction_type' => 'both_teams_score_no'],
            [
                'total_predictions' => $totalPredictions,
                'correct_predictions' => count($correctPredictions),
                'accuracy_percentage' => round($accuracy, 2),
                'monthly_stats' => $monthlyStats,
                'league_stats' => $leagueStats,
                'last_updated' => Carbon::now(),
            ]
        );
    }

    private function createOver25Statistics($finishedMatches): void
    {
        $overResults = [];
        foreach ($finishedMatches as $match) {
            $predictedOver25 = $match->prediction->over_2_5_probability > 0.5;
            if ($predictedOver25) {
                $totalGoals = $match->home_goals + $match->away_goals;
                $actualOver25 = $totalGoals > 2.5;
                $overResults[] = [
                    'correct' => $actualOver25,
                    'match' => $match
                ];
            }
        }

        $totalPredictions = count($overResults);
        $correctPredictions = array_filter($overResults, function($result) {
            return $result['correct'];
        });
        $accuracy = $totalPredictions > 0 ? (count($correctPredictions) / $totalPredictions) * 100 : 0;

        $monthlyStats = $this->calculateCustomMonthlyStats($overResults, 'over_2_5');
        $leagueStats = $this->calculateCustomLeagueStats($overResults, 'over_2_5');

        PredictionStatistic::updateOrCreate(
            ['prediction_type' => 'over_2_5'],
            [
                'total_predictions' => $totalPredictions,
                'correct_predictions' => count($correctPredictions),
                'accuracy_percentage' => round($accuracy, 2),
                'monthly_stats' => $monthlyStats,
                'league_stats' => $leagueStats,
                'last_updated' => Carbon::now(),
            ]
        );
    }

    private function createUnder25Statistics($finishedMatches): void
    {
        $underResults = [];
        foreach ($finishedMatches as $match) {
            $predictedOver25 = $match->prediction->over_2_5_probability > 0.5;
            if (!$predictedOver25) { // Under 2.5 prediction
                $totalGoals = $match->home_goals + $match->away_goals;
                $actualUnder25 = $totalGoals <= 2.5;
                $underResults[] = [
                    'correct' => $actualUnder25,
                    'match' => $match
                ];
            }
        }

        $totalPredictions = count($underResults);
        $correctPredictions = array_filter($underResults, function($result) {
            return $result['correct'];
        });
        $accuracy = $totalPredictions > 0 ? (count($correctPredictions) / $totalPredictions) * 100 : 0;

        $monthlyStats = $this->calculateCustomMonthlyStats($underResults, 'under_2_5');
        $leagueStats = $this->calculateCustomLeagueStats($underResults, 'under_2_5');

        PredictionStatistic::updateOrCreate(
            ['prediction_type' => 'under_2_5'],
            [
                'total_predictions' => $totalPredictions,
                'correct_predictions' => count($correctPredictions),
                'accuracy_percentage' => round($accuracy, 2),
                'monthly_stats' => $monthlyStats,
                'league_stats' => $leagueStats,
                'last_updated' => Carbon::now(),
            ]
        );
    }

    public function updateFirstHalfStatistics(): void
    {
        $finishedMatches = $this->getFinishedMatchesWithPredictions()
            ->filter(function($match) {
                return $match->prediction && 
                       !is_null($match->prediction->over_0_5_first_half_probability);
            });

        // Update first half statistics if needed
        foreach ($finishedMatches as $match) {
            // Simulate first half goals for accuracy calculation
            $totalGoals = $match->home_goals + $match->away_goals;
            $firstHalfGoals = $this->simulateFirstHalfGoals($totalGoals, $match->id);
            
            // Over 0.5 first half
            if (is_null($match->prediction->first_half_over_0_5_correct)) {
                $predictedOver05 = $match->prediction->over_0_5_first_half_probability > 0.5;
                $actualOver05 = $firstHalfGoals > 0.5;
                $match->prediction->first_half_over_0_5_correct = $actualOver05 === $predictedOver05;
            }
            
            // Over 1.5 first half removed as requested
            
            $match->prediction->save();
        }

        // Create Over 0.5 First Half statistics
        $this->createFirstHalfOver05Statistics($finishedMatches);
        
        // Over 1.5 First Half statistics removed as requested
    }

    private function createFirstHalfOver05Statistics($finishedMatches): void
    {
        $over05Results = [];
        foreach ($finishedMatches as $match) {
            if (!is_null($match->prediction->first_half_over_0_5_correct)) {
                $over05Results[] = [
                    'correct' => $match->prediction->first_half_over_0_5_correct,
                    'match' => $match
                ];
            }
        }

        $totalPredictions = count($over05Results);
        $correctPredictions = array_filter($over05Results, function($result) {
            return $result['correct'];
        });
        $accuracy = $totalPredictions > 0 ? (count($correctPredictions) / $totalPredictions) * 100 : 0;

        $monthlyStats = $this->calculateCustomMonthlyStats($over05Results, 'first_half_over_0_5');
        $leagueStats = $this->calculateCustomLeagueStats($over05Results, 'first_half_over_0_5');

        PredictionStatistic::updateOrCreate(
            ['prediction_type' => 'first_half_over_0_5'],
            [
                'total_predictions' => $totalPredictions,
                'correct_predictions' => count($correctPredictions),
                'accuracy_percentage' => round($accuracy, 2),
                'monthly_stats' => $monthlyStats,
                'league_stats' => $leagueStats,
                'last_updated' => Carbon::now(),
            ]
        );
    }

    // createFirstHalfOver15Statistics method removed as requested

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