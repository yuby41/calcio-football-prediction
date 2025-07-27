<?php

namespace App\Observers;

use App\Models\FootballMatch;
use App\Services\StatisticsService;
use Illuminate\Support\Facades\Cache;

class FootballMatchObserver
{
    /**
     * Handle the FootballMatch "updated" event.
     */
    public function updated(FootballMatch $footballMatch): void
    {
        // Clear accuracy cache when a match is updated
        // This ensures fresh calculations when match status or scores change
        if ($this->shouldClearCache($footballMatch)) {
            Cache::forget('prediction_accuracy');
            
            // Update prediction accuracy if match is finished
            if ($footballMatch->status === 'finished' && 
                $footballMatch->prediction && 
                !is_null($footballMatch->home_goals) && 
                !is_null($footballMatch->away_goals) &&
                is_null($footballMatch->prediction->is_correct)) {
                
                $this->updatePredictionAccuracy($footballMatch);
            }
        }
    }

    /**
     * Handle the FootballMatch "saved" event.
     */
    public function saved(FootballMatch $footballMatch): void
    {
        // Clear accuracy cache when a match is saved with new data
        if ($this->shouldClearCache($footballMatch)) {
            Cache::forget('prediction_accuracy');
        }
    }

    /**
     * Determine if we should clear the cache based on what changed
     */
    private function shouldClearCache(FootballMatch $footballMatch): bool
    {
        // Clear cache if status changed to finished or if goals were updated
        return $footballMatch->wasChanged(['status', 'home_goals', 'away_goals']);
    }
    
    /**
     * Update prediction accuracy for a finished match
     */
    private function updatePredictionAccuracy(FootballMatch $footballMatch): void
    {
        $prediction = $footballMatch->prediction;
        
        // Determine actual result
        $actualResult = $this->determineMatchResult($footballMatch);
        
        // Update prediction accuracy
        $prediction->is_correct = ($prediction->predicted_outcome === $actualResult);
        
        // Update additional prediction fields if they exist
        if (!is_null($prediction->both_teams_score_probability)) {
            $bothTeamsScored = ($footballMatch->home_goals > 0 && $footballMatch->away_goals > 0);
            $predictedBothTeamsScore = $prediction->both_teams_score_probability > 0.5;
            $prediction->both_teams_score_correct = ($bothTeamsScored === $predictedBothTeamsScore);
        }
        
        if (!is_null($prediction->over_2_5_probability)) {
            $totalGoals = $footballMatch->home_goals + $footballMatch->away_goals;
            $isOver25 = $totalGoals > 2.5;
            $predictedOver25 = $prediction->over_2_5_probability > $prediction->under_2_5_probability;
            $prediction->over_under_correct = ($isOver25 === $predictedOver25);
        }
        
        $prediction->save();
        
        // Update global statistics after updating individual prediction
        // Using direct call to avoid mbstring dependency
        try {
            \Artisan::call('statistics:update-sql');
        } catch (\Exception $e) {
            \Log::warning('Failed to update statistics automatically: ' . $e->getMessage());
        }
    }
    
    /**
     * Determine the actual match result
     */
    private function determineMatchResult(FootballMatch $footballMatch): string
    {
        if ($footballMatch->home_goals > $footballMatch->away_goals) {
            return 'home_win';
        } elseif ($footballMatch->home_goals < $footballMatch->away_goals) {
            return 'away_win';
        } else {
            return 'draw';
        }
    }
}