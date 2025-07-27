<?php

namespace App\Console\Commands;

use App\Services\FootballApiService;
use App\Services\PredictionService;
use Illuminate\Console\Command;

class SyncFootballData extends Command
{
    protected $signature = 'football:sync {league=PL} {--season=} {--no-predictions}';
    
    protected $description = 'Sync football data from external API and generate predictions';
    
    public function __construct(
        private FootballApiService $footballApiService,
        private PredictionService $predictionService
    ) {
        parent::__construct();
    }
    
    public function handle(): int
    {
        $league = $this->argument('league');
        $season = $this->option('season');
        $noPredictions = $this->option('no-predictions');
        
        $this->info("Syncing football data for league: {$league}");
        
        // Sync teams
        $this->info('Syncing teams...');
        $teamsSynced = $this->footballApiService->syncTeams($league);
        $this->info("Synced {$teamsSynced} teams");
        
        // Sync matches
        $this->info('Syncing matches...');
        $matchesSynced = $this->footballApiService->syncMatches($league, $season);
        $this->info("Synced {$matchesSynced} matches");
        
        // Generar predicciones automáticamente si no se especifica --no-predictions
        if (!$noPredictions) {
            $this->info('Generating predictions for upcoming matches...');
            $predictionsGenerated = $this->predictionService->generatePredictionsForUpcomingMatches();
            $this->info("Generated {$predictionsGenerated} predictions");
        }
        
        $this->info('Football data sync completed successfully!');
        
        return Command::SUCCESS;
    }
}