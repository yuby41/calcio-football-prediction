<?php

namespace App\Console\Commands;

use App\Models\FootballMatch;
use App\Services\SimpleAccuracyService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class SimulateMatchUpdates extends Command
{
    protected $signature = 'matches:simulate-updates';
    
    protected $description = 'Simulate match updates to test automatic accuracy updates';
    
    public function handle(): int
    {
        $this->info('Simulating match updates...');
        
        // Find some scheduled matches to convert to live, then finished
        $scheduledMatches = FootballMatch::where('status', 'scheduled')
            ->whereHas('prediction')
            ->take(2)
            ->get();
            
        if ($scheduledMatches->count() == 0) {
            $this->warn('No scheduled matches with predictions found');
            return Command::SUCCESS;
        }
        
        foreach ($scheduledMatches as $match) {
            $this->info("Processing match {$match->id}...");
            
            // Step 1: Mark as live
            $match->status = 'live';
            $match->save();
            $this->info("→ Match {$match->id} is now LIVE");
            
            // Step 2: Add some goals (simulate match progress)
            $match->home_goals = rand(0, 3);
            $match->away_goals = rand(0, 3);
            $match->save();
            $this->info("→ Score update: {$match->home_goals}:{$match->away_goals}");
            
            // Step 3: Mark as finished
            $match->status = 'finished';
            $match->save();
            $this->info("→ Match {$match->id} FINISHED: {$match->home_goals}:{$match->away_goals}");
            
            // Clear cache to force accuracy recalculation
            Cache::forget('prediction_accuracy');
            Cache::forget('prediction_accuracy_timestamp');
        }
        
        $this->info('Simulating dashboard visit to trigger accuracy update...');
        $newAccuracy = SimpleAccuracyService::getCurrentAccuracy();
        $this->info("New accuracy after match updates: {$newAccuracy}%");
        
        $this->info('✅ Simulation complete! Visit the dashboard to see changes.');
        
        return Command::SUCCESS;
    }
}