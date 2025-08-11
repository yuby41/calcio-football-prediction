<?php

namespace App\Console\Commands;

use App\Models\FootballMatch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateBatchPredictions extends Command
{
    protected $signature = 'predictions:generate-batch 
                            {--limit=100 : Number of matches to process}
                            {--days=30 : Only process matches from last N days}
                            {--dry-run : Show what would be processed without generating}';
    
    protected $description = 'Generate predictions for finished matches without predictions in batch';

    public function handle()
    {
        $limit = (int) $this->option('limit');
        $days = (int) $this->option('days');
        $dryRun = $this->option('dry-run');
        
        $this->info('🔮 Generating batch predictions...');
        $this->info("Limit: {$limit} matches, Days: {$days}, Dry run: " . ($dryRun ? 'Yes' : 'No'));

        // Find matches without predictions
        $matches = FootballMatch::where('status', 'finished')
            ->where('match_date', '>=', now()->subDays($days))
            ->whereDoesntHave('prediction')
            ->with(['homeTeam', 'awayTeam'])
            ->limit($limit)
            ->get();

        $this->info("Found {$matches->count()} matches without predictions");

        if ($matches->isEmpty()) {
            $this->info('✅ No matches found that need predictions');
            return 0;
        }

        if ($dryRun) {
            $this->warn('🔍 DRY RUN - Would process:');
            foreach ($matches->take(10) as $match) {
                $this->line("  - {$match->homeTeam->name} vs {$match->awayTeam->name} ({$match->match_date->format('Y-m-d')})");
            }
            if ($matches->count() > 10) {
                $this->line("  ... and " . ($matches->count() - 10) . " more matches");
            }
            return 0;
        }

        $generated = 0;
        $errors = 0;

        // Process matches in batches to avoid memory issues
        foreach ($matches->chunk(10) as $chunk) {
            foreach ($chunk as $match) {
                try {
                    // Call the ML predict command for each match
                    $result = $this->call('ml:predict', ['--match-id' => $match->id]);
                    
                    if ($result === 0) {
                        $generated++;
                        $this->line("✅ Generated prediction for: {$match->homeTeam->name} vs {$match->awayTeam->name}");
                    } else {
                        $errors++;
                        $this->warn("❌ Failed to generate prediction for match ID: {$match->id}");
                    }
                } catch (\Exception $e) {
                    $errors++;
                    $this->error("❌ Error processing match {$match->id}: " . $e->getMessage());
                }
            }
            
            // Small delay to prevent overwhelming the system
            usleep(100000); // 0.1 second
        }

        $this->info("\n📊 Batch prediction generation completed:");
        $this->info("✅ Generated: {$generated}");
        $this->warn("❌ Errors: {$errors}");
        $this->info("📈 Success rate: " . round(($generated / ($generated + $errors)) * 100, 1) . "%");

        return $errors > 0 ? 1 : 0;
    }
}