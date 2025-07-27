<?php

namespace App\Console\Commands;

use App\Models\FootballMatch;
use App\Models\MatchPrediction;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateHistoricalData extends Command
{
    protected $signature = 'data:generate-historical';
    
    protected $description = 'Generate historical match data for testing statistics charts';
    
    public function handle(): int
    {
        $this->info('Generating historical match data for statistics...');
        
        $updated = 0;
        
        // Get some finished matches and spread them across different dates
        $matches = FootballMatch::with('prediction')
            ->where('status', 'finished')
            ->whereHas('prediction')
            ->take(50)
            ->get();
            
        foreach ($matches as $index => $match) {
            // Spread matches across last 60 days
            $daysAgo = rand(1, 60);
            $newDate = Carbon::now()->subDays($daysAgo)->setHour(rand(14, 22))->setMinute(rand(0, 59));
            
            $match->match_date = $newDate;
            $match->save();
            
            $updated++;
            
            if ($updated % 10 == 0) {
                $this->info("Updated {$updated} matches...");
            }
        }
        
        $this->info("Generated historical data for {$updated} matches across the last 60 days");
        $this->info('This will provide data for daily, weekly, and monthly charts');
        
        return Command::SUCCESS;
    }
}