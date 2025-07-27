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
                'last_updated' => Carbon::today(),
            ]
        );
    }

    public function updateBothTeamsScoreStatistics(): void
    {
        $finishedMatches = $this->getFinishedMatchesWithPredictions()
            ->filter(function($match) {
                return $match->prediction && 
                       !is_null($match->prediction->both_teams_score_probability) &&
                       !is_null($match->prediction->both_teams_score_correct);
            });
        
        $totalPredictions = $finishedMatches->count();
        $correctPredictions = $finishedMatches->filter(function($match) {
            return $match->prediction->both_teams_score_correct === true;
        })->count();
        $accuracy = $totalPredictions > 0 ? ($correctPredictions / $totalPredictions) * 100 : 0;
        
        // Convert to array for legacy methods
        $bothTeamsScoreResults = [];
        foreach ($finishedMatches as $match) {
            $bothTeamsScoreResults[] = [
                'correct' => $match->prediction->both_teams_score_correct,
                'match' => $match
            ];
        }

        $monthlyStats = $this->calculateCustomMonthlyStats($bothTeamsScoreResults, 'both_teams_score');
        $leagueStats = $this->calculateCustomLeagueStats($bothTeamsScoreResults, 'both_teams_score');

        PredictionStatistic::updateOrCreate(
            ['prediction_type' => 'both_teams_score'],
            [
                'total_predictions' => $totalPredictions,
                'correct_predictions' => $correctPredictions,
                'accuracy_percentage' => round($accuracy, 2),
                'monthly_stats' => $monthlyStats,
                'league_stats' => $leagueStats,
                'last_updated' => Carbon::today(),
            ]
        );
    }

    public function updateOverUnderStatistics(): void
    {
        $finishedMatches = $this->getFinishedMatchesWithPredictions()
            ->filter(function($match) {
                return $match->prediction && 
                       !is_null($match->prediction->over_2_5_probability) &&
                       !is_null($match->prediction->over_under_correct);
            });
        
        $totalPredictions = $finishedMatches->count();
        $correctPredictions = $finishedMatches->filter(function($match) {
            return $match->prediction->over_under_correct === true;
        })->count();
        $accuracy = $totalPredictions > 0 ? ($correctPredictions / $totalPredictions) * 100 : 0;
        
        // Convert to array for legacy methods
        $overUnderResults = [];
        foreach ($finishedMatches as $match) {
            $overUnderResults[] = [
                'correct' => $match->prediction->over_under_correct,
                'match' => $match
            ];
        }

        $monthlyStats = $this->calculateCustomMonthlyStats($overUnderResults, 'over_under_2_5');
        $leagueStats = $this->calculateCustomLeagueStats($overUnderResults, 'over_under_2_5');

        PredictionStatistic::updateOrCreate(
            ['prediction_type' => 'over_under_2_5'],
            [
                'total_predictions' => $totalPredictions,
                'correct_predictions' => $correctPredictions,
                'accuracy_percentage' => round($accuracy, 2),
                'monthly_stats' => $monthlyStats,
                'league_stats' => $leagueStats,
                'last_updated' => Carbon::today(),
            ]
        );
    }

    private function getFinishedMatchesWithPredictions()
    {
        return FootballMatch::with(['prediction', 'homeTeam', 'awayTeam'])
            ->where('status', 'finished')
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
}