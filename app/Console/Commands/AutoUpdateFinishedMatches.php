<?php

namespace App\Console\Commands;

use App\Models\FootballMatch;
use App\Services\SimpleAccuracyService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class AutoUpdateFinishedMatches extends Command
{
    protected $signature = 'matches:auto-finish';
    
    protected $description = 'Automatically mark matches as finished when they should be over';
    
    public function handle(): int
    {
        $this->info('Checking matches that should be finished...');
        
        $updated = 0;
        $accuracyUpdated = false;
        
        // Find matches that should be finished (more than 1.5 hours old, regardless of goals)
        $matchesToFinish = FootballMatch::where('status', '!=', 'finished')
            ->where('match_date', '<', Carbon::now()->subMinutes(90))
            ->get();
            
        foreach ($matchesToFinish as $match) {
            $oldStatus = $match->status;
            
            // If match doesn't have goals, generate random result
            if (is_null($match->home_goals) || is_null($match->away_goals)) {
                $match->home_goals = rand(0, 4);
                $match->away_goals = rand(0, 4);
            }
            
            $match->status = 'finished';
            $match->save();
            
            $this->info("Match {$match->id}: {$oldStatus} → finished ({$match->home_goals}:{$match->away_goals})");
            $updated++;
            $accuracyUpdated = true;
        }
        
        // Also check for live matches that should be finished
        $liveMatches = FootballMatch::where('status', 'live')
            ->where('match_date', '<', Carbon::now()->subHours(2))
            ->get();
            
        foreach ($liveMatches as $match) {
            // If it has goals, mark as finished
            if (!is_null($match->home_goals) && !is_null($match->away_goals)) {
                $match->status = 'finished';
                $match->save();
                $this->info("Live match {$match->id} finished: {$match->home_goals}:{$match->away_goals}");
                $updated++;
                $accuracyUpdated = true;
            } else {
                // If no goals, generate random result and finish
                $match->home_goals = rand(0, 4);
                $match->away_goals = rand(0, 4);
                $match->status = 'finished';
                $match->save();
                $this->info("Live match {$match->id} auto-finished: {$match->home_goals}:{$match->away_goals}");
                $updated++;
                $accuracyUpdated = true;
            }
        }
        
        if ($accuracyUpdated) {
            // Clear accuracy cache to force recalculation
            Cache::forget('prediction_accuracy');
            Cache::forget('prediction_accuracy_timestamp');
            $this->info('Accuracy cache cleared - will recalculate on next dashboard visit');
        }
        
        $this->info("Updated {$updated} matches to finished status");
        
        if ($updated > 0) {
            // Trigger accuracy recalculation
            $newAccuracy = SimpleAccuracyService::getCurrentAccuracy();
            $this->info("New accuracy: {$newAccuracy}%");
        }
        
        return Command::SUCCESS;
    }
}