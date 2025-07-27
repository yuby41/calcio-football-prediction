<?php

namespace App\Console\Commands;

use App\Models\FootballMatch;
use App\Services\PredictionService;
use Illuminate\Console\Command;
use Carbon\Carbon;

class GeneratePredictions extends Command
{
    protected $signature = 'ml:predict {--match-id=} {--upcoming-days=7}';
    
    protected $description = 'Generate match predictions using ML models';
    
    public function __construct(private PredictionService $predictionService)
    {
        parent::__construct();
    }
    
    public function handle(): int
    {
        $this->info('Generating match predictions...');
        
        $matchId = $this->option('match-id');
        
        if ($matchId) {
            // Predict specific match
            $match = FootballMatch::find($matchId);
            if (!$match) {
                $this->error("Match with ID {$matchId} not found");
                return Command::FAILURE;
            }
            
            $success = $this->predictionService->predictMatch($match);
            if ($success) {
                $this->info("Prediction generated for match {$matchId}");
            } else {
                $this->error("Failed to generate prediction for match {$matchId}");
                return Command::FAILURE;
            }
        } else {
            // Predict upcoming matches
            $upcomingDays = (int) $this->option('upcoming-days');
            $matches = FootballMatch::where('status', 'scheduled')
                ->whereBetween('match_date', [
                    Carbon::now(),
                    Carbon::now()->addDays($upcomingDays)
                ])
                ->get();
            
            if ($matches->isEmpty()) {
                $this->info('No upcoming matches found for prediction');
                return Command::SUCCESS;
            }
            
            $this->info("Found {$matches->count()} upcoming matches");
            
            $progress = $this->output->createProgressBar($matches->count());
            $progress->start();
            
            $predictionsGenerated = $this->predictionService->generatePredictionsForMatches($matches);
            
            $progress->finish();
            $this->newLine();
            
            $this->info("Generated {$predictionsGenerated} predictions successfully!");
        }
        
        return Command::SUCCESS;
    }
}