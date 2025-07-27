<?php

namespace App\Console\Commands;

use App\Services\PredictionService;
use Illuminate\Console\Command;

class GeneratePredictionsForFinishedMatches extends Command
{
    protected $signature = 'predictions:generate-finished {--limit=500 : Number of finished matches to process}';
    
    protected $description = 'Generate predictions for finished matches that don\'t have them';

    public function handle()
    {
        $limit = (int) $this->option('limit');
        
        $this->info("Generating predictions for finished matches (limit: {$limit})...");
        
        $predictionService = new PredictionService();
        
        $generated = $predictionService->generatePredictionsForFinishedMatches($limit);
        
        if ($generated > 0) {
            $this->info("✓ Generated {$generated} predictions for finished matches");
        } else {
            $this->warn("No predictions generated (all finished matches may already have predictions)");
        }
        
        return Command::SUCCESS;
    }
}