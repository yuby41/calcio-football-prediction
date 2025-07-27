<?php

namespace App\Console\Commands;

use App\Services\FootballApiService;
use App\Services\PredictionService;
use Illuminate\Console\Command;

class SyncTodayMatches extends Command
{
    protected $signature = 'football:sync-today {--no-predictions}';
    
    protected $description = 'Sync today\'s matches from all leagues and generate predictions';
    
    public function __construct(
        private FootballApiService $footballApiService,
        private PredictionService $predictionService
    ) {
        parent::__construct();
    }
    
    public function handle(): int
    {
        $this->info('Syncing today\'s matches from all leagues...');
        
        // Sincronizar partidos de hoy de todas las ligas
        $matchesSynced = $this->footballApiService->syncTodayMatches();
        $this->info("Synced {$matchesSynced} matches for today");
        
        // También sincronizar partidos en vivo
        $this->info('Syncing live matches...');
        $liveMatches = $this->footballApiService->fetchLiveScores();
        $liveSynced = 0;
        
        foreach ($liveMatches as $matchData) {
            $this->footballApiService->syncSingleMatch($matchData);
            $liveSynced++;
        }
        
        $this->info("Updated {$liveSynced} live matches");
        
        // Generar predicciones automáticamente si no se especifica --no-predictions
        if (!$this->option('no-predictions')) {
            $this->info('Generating predictions for today\'s matches...');
            $predictionsGenerated = $this->predictionService->generatePredictionsForTodayMatches();
            $this->info("Generated {$predictionsGenerated} predictions");
        }
        
        $this->info('Today\'s matches sync completed successfully!');
        
        return Command::SUCCESS;
    }
}