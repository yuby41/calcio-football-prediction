<?php

namespace App\Console\Commands;

use App\Models\FootballMatch;
use App\Services\PredictionService;
use Illuminate\Console\Command;

class RegenerateWith3_0Model extends Command
{
    protected $signature = 'predictions:regenerate-with-3.0 {--limit=50 : Number of matches to process}';
    protected $description = 'Regenerate predictions using 3.0 model for matches that only have 2.0 predictions';

    public function handle()
    {
        $this->info('Finding matches that need 3.0 model predictions...');

        $limit = $this->option('limit');

        // Find matches that have only 2.0 predictions and are scheduled or recent
        $matches = FootballMatch::whereHas('prediction', function($query) {
            $query->whereIn('model_version', ['2.0.0-ensemble'])
                  ->whereNotExists(function($subQuery) {
                      $subQuery->selectRaw(1)
                              ->from('match_predictions as mp2')
                              ->whereColumn('mp2.match_id', 'match_predictions.match_id')
                              ->whereIn('mp2.model_version', [
                                  '3.0.0-enhanced-outcomes',
                                  '3.0.0-enhanced-outcomes-fixed'
                              ]);
                  });
        })
        ->where('status', 'scheduled')
        ->where('match_date', '>', now()->subDays(1))
        ->limit($limit)
        ->get();

        $this->info("Found {$matches->count()} matches to regenerate with 3.0 model");

        if ($matches->isEmpty()) {
            $this->info('No matches found that need regeneration.');
            return Command::SUCCESS;
        }

        $predictionService = new PredictionService();
        $processed = 0;
        $successful = 0;

        foreach ($matches as $match) {
            try {
                $this->line("Processing match {$match->id}: {$match->homeTeam->name} vs {$match->awayTeam->name}");
                
                // Delete existing 2.0 prediction
                $match->prediction()->delete();
                
                // Generate new prediction with 3.0 model
                if ($predictionService->predictMatch($match)) {
                    $successful++;
                    $this->info("✅ Successfully generated 3.0 prediction for match {$match->id}");
                } else {
                    $this->warn("⚠️ Failed to generate 3.0 prediction for match {$match->id}");
                }
                
                $processed++;
                
                // Small delay to avoid overwhelming the system
                usleep(100000); // 0.1 seconds
                
            } catch (\Exception $e) {
                $this->error("❌ Error processing match {$match->id}: " . $e->getMessage());
            }
        }

        $this->info("\n📊 Summary:");
        $this->info("Processed: {$processed} matches");
        $this->info("Successful: {$successful} predictions");
        $this->info("Failed: " . ($processed - $successful) . " predictions");

        return Command::SUCCESS;
    }
}