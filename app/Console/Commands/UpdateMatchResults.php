<?php

namespace App\Console\Commands;

use App\Models\FootballMatch;
use App\Services\StatisticsService;
use App\Services\FootballApiService;
use Illuminate\Console\Command;
use Carbon\Carbon;

class UpdateMatchResults extends Command
{
    protected $signature = 'matches:update-results';
    
    protected $description = 'Update match results and recalculate prediction accuracy';
    
    public function __construct(
        private FootballApiService $footballApiService,
        private StatisticsService $statisticsService
    ) {
        parent::__construct();
    }
    
    public function handle(): int
    {
        $this->info('Updating match results...');
        
        try {
            // Get matches from the last 3 days that might have finished
            $matches = FootballMatch::with(['prediction'])
                ->where('status', '!=', 'finished')
                ->whereBetween('match_date', [
                    Carbon::now()->subDays(3),
                    Carbon::now()
                ])
                ->get();

            $updatedMatches = 0;

            foreach ($matches as $match) {
                // Check if match should be updated from API
                if ($this->updateMatchFromApi($match)) {
                    $updatedMatches++;
                    
                    // If match is now finished, update prediction accuracy
                    if ($match->status === 'finished') {
                        $this->statisticsService->updateMatchResult($match);
                        $this->info("Updated match: {$match->homeTeam->name} vs {$match->awayTeam->name}");
                    }
                }
            }

            $this->info("Updated {$updatedMatches} matches");
            $this->info('Prediction statistics updated successfully!');
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error('Failed to update match results: ' . $e->getMessage());
            
            return Command::FAILURE;
        }
    }
    
    private function updateMatchFromApi(FootballMatch $match): bool
    {
        try {
            // This would typically fetch from the API to get updated match data
            // For now, we'll simulate updating finished matches
            
            // If the match date was more than 2 hours ago and it's not finished, mark it as finished
            if ($match->match_date->addHours(2)->isPast() && $match->status !== 'finished') {
                // In a real scenario, you'd fetch this from the API
                // For simulation, we'll generate random scores for finished matches
                if ($match->status === 'live' || $match->status === 'scheduled') {
                    $match->status = 'finished';
                    
                    // Only generate scores if they don't exist
                    if (is_null($match->home_goals) || is_null($match->away_goals)) {
                        $match->home_goals = rand(0, 4);
                        $match->away_goals = rand(0, 4);
                    }
                    
                    $match->save();
                    return true;
                }
            }
            
            return false;
            
        } catch (\Exception $e) {
            $this->error("Failed to update match {$match->id}: " . $e->getMessage());
            return false;
        }
    }
}