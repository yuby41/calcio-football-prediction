<?php

namespace App\Services;

use App\Models\FootballMatch;
use App\Models\MatchPrediction;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SimpleAccuracyService
{
    public static function getCurrentAccuracy(): float
    {
        try {
            // Check cache first (cache for 2 minutes for more frequent updates)
            $cacheKey = 'prediction_accuracy';
            $lastUpdate = Cache::get($cacheKey . '_timestamp');
            $cachedAccuracy = Cache::get($cacheKey);
            
            // Force update every 30 seconds for betting decisions
            if ($cachedAccuracy === null || !$lastUpdate || $lastUpdate->diffInSeconds(Carbon::now()) >= 30) {
                
                // Auto-update finished matches first
                self::autoUpdateFinishedMatches();
                
                // Calculate accuracy
                $accuracy = self::calculateAccuracy();
                
                // Cache the result for 5 minutes but check every 30 seconds
                Cache::put($cacheKey, $accuracy, 300);
                Cache::put($cacheKey . '_timestamp', Carbon::now(), 300);
                
                Log::info("Accuracy updated: {$accuracy}%");
                
                return $accuracy;
            }
            
            return $cachedAccuracy;
            
        } catch (\Exception $e) {
            Log::warning('Error calculating accuracy: ' . $e->getMessage());
            return 0;
        }
    }
    
    private static function autoUpdateFinishedMatches(): void
    {
        try {
            // Get matches that should be finished (older than 2 hours) but aren't marked as finished
            $matchesToUpdate = FootballMatch::where('status', '!=', 'finished')
                ->where('match_date', '<', Carbon::now()->subHours(2))
                ->whereNotNull('home_goals')
                ->whereNotNull('away_goals')
                ->limit(50) // Limit to avoid performance issues
                ->get();

            foreach ($matchesToUpdate as $match) {
                $match->status = 'finished';
                $match->save();
            }
        } catch (\Exception $e) {
            Log::warning('Error auto-updating finished matches: ' . $e->getMessage());
        }
    }
    
    private static function calculateAccuracy(): float
    {
        // Get finished matches with predictions
        $finishedMatches = FootballMatch::with(['prediction'])
            ->where('status', 'finished')
            ->whereHas('prediction')
            ->whereNotNull('home_goals')
            ->whereNotNull('away_goals')
            ->limit(1000) // Limit for performance
            ->get();

        if ($finishedMatches->isEmpty()) {
            return 0;
        }

        $correctPredictions = 0;
        $totalPredictions = $finishedMatches->count();

        foreach ($finishedMatches as $match) {
            $prediction = $match->prediction;
            
            // Determine actual result
            $actualResult = self::determineMatchResult($match);
            
            // Always update prediction accuracy for consistency
            $prediction->is_correct = $prediction->predicted_outcome === $actualResult;
            
            // Also update other prediction types
            if (!is_null($prediction->both_teams_score_probability)) {
                $actualBothScored = $match->home_goals > 0 && $match->away_goals > 0;
                $predictedBothScore = $prediction->both_teams_score_probability > 0.5;
                $prediction->both_teams_score_correct = $actualBothScored === $predictedBothScore;
            }
            
            if (!is_null($prediction->over_2_5_probability)) {
                $totalGoals = $match->home_goals + $match->away_goals;
                $actualOver25 = $totalGoals > 2.5;
                $predictedOver25 = $prediction->over_2_5_probability > $prediction->under_2_5_probability;
                $prediction->over_under_correct = $actualOver25 === $predictedOver25;
            }
            
            $prediction->save();
            
            if ($prediction->is_correct) {
                $correctPredictions++;
            }
        }

        return $totalPredictions > 0 ? round(($correctPredictions / $totalPredictions) * 100, 1) : 0;
    }
    
    private static function determineMatchResult(FootballMatch $match): string
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