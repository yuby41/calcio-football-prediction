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
            // Predict upcoming matches AND live matches (prioritize live)
            $upcomingDays = (int) $this->option('upcoming-days');
            $matches = FootballMatch::where(function($query) use ($upcomingDays) {
                // Include live matches (highest priority)
                $query->where('status', 'live')
                      // Include scheduled matches
                      ->orWhere(function($subQuery) use ($upcomingDays) {
                          $subQuery->where('status', 'scheduled')
                                   ->whereBetween('match_date', [
                                       Carbon::now(),
                                       Carbon::now()->addDays($upcomingDays)
                                   ]);
                      });
            })
            // Only get matches without predictions
            ->whereDoesntHave('prediction')
            // Order by priority: live first, then scheduled by date
            ->orderByRaw("CASE status WHEN 'live' THEN 1 WHEN 'scheduled' THEN 2 ELSE 3 END")
            ->orderBy('match_date')
            ->get();
            
            if ($matches->isEmpty()) {
                $this->info('No upcoming matches found for prediction');
                return Command::SUCCESS;
            }
            
            $liveCount = $matches->where('status', 'live')->count();
            $scheduledCount = $matches->where('status', 'scheduled')->count();
            
            $this->info("Found {$matches->count()} matches: {$liveCount} live, {$scheduledCount} scheduled");
            
            if ($liveCount > 0) {
                $this->info("🔴 Prioritizing {$liveCount} live matches");
            }
            
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