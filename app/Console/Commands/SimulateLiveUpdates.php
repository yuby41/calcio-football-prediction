<?php

namespace App\Console\Commands;

use App\Models\FootballMatch;
use App\Events\MatchScoreUpdated;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SimulateLiveUpdates extends Command
{
    protected $signature = 'matches:simulate-live {--count=3 : Number of matches to simulate}';
    
    protected $description = 'Simulate live match updates for testing real-time features';

    public function handle()
    {
        $count = $this->option('count');
        
        $this->info("Simulating live updates for {$count} matches...");
        
        // Get some finished matches to simulate as live
        $matches = FootballMatch::with(['homeTeam', 'awayTeam', 'prediction'])
            ->where('status', 'finished')
            ->whereNotNull('home_goals')
            ->whereNotNull('away_goals')
            ->limit($count)
            ->get();

        if ($matches->isEmpty()) {
            $this->error('No suitable matches found for simulation');
            return Command::FAILURE;
        }

        foreach ($matches as $match) {
            $this->simulateMatch($match);
        }

        $this->info('✓ Live simulation completed!');
        return Command::SUCCESS;
    }

    private function simulateMatch(FootballMatch $match)
    {
        $this->line("Simulating: {$match->homeTeam->name} vs {$match->awayTeam->name}");
        
        // Reset match to live status
        $originalHomeGoals = $match->home_goals;
        $originalAwayGoals = $match->away_goals;
        
        $match->update([
            'status' => 'live',
            'home_goals' => 0,
            'away_goals' => 0,
            'minute' => 1
        ]);

        // Simulate score progression
        $homeGoals = 0;
        $awayGoals = 0;
        $minute = 1;

        // Simulate goals over time
        for ($i = 0; $i < 10; $i++) {
            sleep(1); // Wait 1 second between updates
            
            $minute = min(90, $minute + rand(5, 15));
            
            // Randomly add goals
            if (rand(1, 4) === 1 && $homeGoals < $originalHomeGoals) {
                $homeGoals++;
                $this->line("  ⚽ Goal! {$match->homeTeam->name} {$homeGoals}-{$awayGoals} {$match->awayTeam->name} ({$minute}')");
            }
            
            if (rand(1, 4) === 1 && $awayGoals < $originalAwayGoals) {
                $awayGoals++;
                $this->line("  ⚽ Goal! {$match->homeTeam->name} {$homeGoals}-{$awayGoals} {$match->awayTeam->name} ({$minute}')");
            }

            // Update match
            $match->update([
                'home_goals' => $homeGoals,
                'away_goals' => $awayGoals,
                'minute' => $minute
            ]);

            // Broadcast update
            event(new MatchScoreUpdated($match));
            
            if ($minute >= 90) {
                break;
            }
        }

        // Finish the match
        $match->update([
            'status' => 'finished',
            'home_goals' => $originalHomeGoals,
            'away_goals' => $originalAwayGoals,
            'minute' => null
        ]);

        event(new MatchScoreUpdated($match));
        $this->line("  🏁 Final: {$match->homeTeam->name} {$originalHomeGoals}-{$originalAwayGoals} {$match->awayTeam->name}");
    }
}