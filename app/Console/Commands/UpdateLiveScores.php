<?php

namespace App\Console\Commands;

use App\Services\FootballApiService;
use App\Services\PredictionService;
use Illuminate\Console\Command;

class UpdateLiveScores extends Command
{
    protected $signature = 'football:update-live';
    
    protected $description = 'Update live scores and match results';
    
    public function __construct(
        private FootballApiService $footballApiService,
        private PredictionService $predictionService
    ) {
        parent::__construct();
    }
    
    public function handle(): int
    {
        $this->info('Updating live scores...');
        
        try {
            // Sincronizar partidos en vivo para actualizar resultados
            $liveMatches = $this->footballApiService->fetchLiveScores();
            $liveSynced = 0;
            
            foreach ($liveMatches as $matchData) {
                $this->footballApiService->syncSingleMatch($matchData);
                $liveSynced++;
            }
            
            $this->info("Updated {$liveSynced} live matches");
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error('Failed to update live scores: ' . $e->getMessage());
            
            return Command::FAILURE;
        }
    }
}