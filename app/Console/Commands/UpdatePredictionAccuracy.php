<?php

namespace App\Console\Commands;

use App\Models\FootballMatch;
use App\Models\MatchPrediction;
use Illuminate\Console\Command;

class UpdatePredictionAccuracy extends Command
{
    protected $signature = 'predictions:update-accuracy {--force}';
    
    protected $description = 'Update prediction accuracy for all finished matches';
    
    public function handle(): int
    {
        $this->info('Updating prediction accuracy for finished matches...');
        
        $force = $this->option('force');
        
        // Get finished matches with predictions
        $query = FootballMatch::with(['prediction'])
            ->where('status', 'finished')
            ->whereHas('prediction')
            ->whereNotNull('home_goals')
            ->whereNotNull('away_goals');
            
        if (!$force) {
            // Only update predictions where is_correct is null
            $query->whereHas('prediction', function($q) {
                $q->whereNull('is_correct');
            });
        }
        
        $matches = $query->get();
        
        if ($matches->isEmpty()) {
            $this->info('No matches need accuracy updates.');
            return Command::SUCCESS;
        }
        
        $updated = 0;
        
        foreach ($matches as $match) {
            $prediction = $match->prediction;
            
            // Determine actual match result
            $actualResult = $this->determineMatchResult($match);
            
            // Update match outcome prediction
            $prediction->is_correct = $prediction->predicted_outcome === $actualResult;
            
            // Update both teams score prediction
            if (!is_null($prediction->both_teams_score_probability)) {
                $actualBothScored = $match->home_goals > 0 && $match->away_goals > 0;
                $predictedBothScore = $prediction->both_teams_score_probability > 0.5;
                $prediction->both_teams_score_correct = $actualBothScored === $predictedBothScore;
            }
            
            // Update over/under 2.5 prediction
            if (!is_null($prediction->over_2_5_probability)) {
                $totalGoals = $match->home_goals + $match->away_goals;
                $actualOver25 = $totalGoals > 2.5;
                $predictedOver25 = $prediction->over_2_5_probability > ($prediction->under_2_5_probability ?? 0);
                $prediction->over_under_correct = $actualOver25 === $predictedOver25;
            }
            
            $prediction->save();
            $updated++;
        }
        
        $this->info("Updated accuracy for {$updated} predictions.");
        
        return Command::SUCCESS;
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