<?php

namespace App\Console\Commands;

use App\Services\FootballApiService;
use App\Services\PredictionService;
use App\Services\StatisticsService;
use Illuminate\Console\Command;

class UpdateTodayMatchesScheduled extends Command
{
    protected $signature = 'football:update-today {--silent} {--no-predictions} {--no-statistics}';
    
    protected $description = 'Update today\'s matches, live scores, generate predictions and update statistics';
    
    public function __construct(
        private FootballApiService $footballApiService,
        private PredictionService $predictionService,
        private StatisticsService $statisticsService
    ) {
        parent::__construct();
    }
    
    public function handle(): int
    {
        $quiet = $this->option('silent');
        $noPredictions = $this->option('no-predictions');
        $noStatistics = $this->option('no-statistics');
        
        if (!$quiet) {
            $this->info('Updating today\'s matches and live scores...');
        }
        
        try {
            // Sincronizar partidos de hoy
            $matchesSynced = $this->footballApiService->syncTodayMatches();
            
            // Sincronizar partidos en vivo para actualizar resultados
            $liveMatches = $this->footballApiService->fetchLiveScores();
            $liveSynced = 0;
            
            foreach ($liveMatches as $matchData) {
                $this->footballApiService->syncSingleMatch($matchData);
                $liveSynced++;
            }
            
            $predictionsGenerated = 0;
            
            // Generar predicciones automáticamente si no se especifica --no-predictions
            if (!$noPredictions) {
                if (!$quiet) {
                    $this->info('Generating predictions...');
                }
                $predictionsGenerated = $this->predictionService->generatePredictionsForTodayMatches();
            }
            
            // Actualizar estadísticas automáticamente si no se especifica --no-statistics
            if (!$noStatistics) {
                if (!$quiet) {
                    $this->info('Updating statistics...');
                }
                $this->statisticsService->updateAllStatistics();
            }
            
            if (!$quiet) {
                $this->info("Updated {$matchesSynced} today's matches, {$liveSynced} live matches, generated {$predictionsGenerated} predictions and updated statistics");
            }
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            if (!$quiet) {
                $this->error('Failed to update matches: ' . $e->getMessage());
            }
            
            return Command::FAILURE;
        }
    }
}