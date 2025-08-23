<?php

namespace App\Console\Commands;

use App\Models\FootballMatch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class GenerateHistoricalPredictions extends Command
{
    protected $signature = 'predictions:generate-historical 
                            {--limit=1000 : Number of matches to process}
                            {--chunk=50 : Process matches in chunks of N}
                            {--from= : Start date (YYYY-MM-DD), defaults to 1 year ago}
                            {--to= : End date (YYYY-MM-DD), defaults to today}
                            {--dry-run : Show what would be processed without generating}
                            {--force : Process all historical matches regardless of date}';
    
    protected $description = 'Generate predictions for all historical finished matches without predictions';

    public function handle()
    {
        $limit = (int) $this->option('limit');
        $chunkSize = (int) $this->option('chunk');
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');
        
        // Set date range
        if ($force) {
            $fromDate = null;
            $toDate = now();
            $this->warn('🚨 FORCE mode: Processing ALL historical matches');
        } else {
            $fromDate = $this->option('from') ? Carbon::parse($this->option('from')) : now()->subYear();
            $toDate = $this->option('to') ? Carbon::parse($this->option('to')) : now();
        }

        $this->info('🔮 Generating historical predictions...');
        $this->info("Limit: {$limit}, Chunk size: {$chunkSize}, Dry run: " . ($dryRun ? 'Yes' : 'No'));
        
        if (!$force) {
            $this->info("Date range: {$fromDate->format('Y-m-d')} to {$toDate->format('Y-m-d')}");
        }

        // Count total matches without predictions
        $query = FootballMatch::where('status', 'finished')
            ->whereDoesntHave('prediction')
            ->whereNotNull('home_goals')
            ->whereNotNull('away_goals');

        if (!$force) {
            $query->whereBetween('match_date', [$fromDate, $toDate]);
        }

        $totalMatches = $query->count();
        $this->info("📊 Total matches without predictions: {$totalMatches}");

        if ($totalMatches === 0) {
            $this->info('✅ No matches found that need predictions');
            return 0;
        }

        // Get matches to process
        $matches = $query->with(['homeTeam', 'awayTeam'])
            ->orderBy('match_date', 'desc')
            ->limit($limit)
            ->get();

        $this->info("📝 Processing {$matches->count()} matches");

        if ($dryRun) {
            $this->warn('🔍 DRY RUN - Would process:');
            foreach ($matches->take(10) as $match) {
                $this->line("  - {$match->homeTeam->name} vs {$match->awayTeam->name} ({$match->match_date->format('Y-m-d')}) - {$match->home_goals}:{$match->away_goals}");
            }
            if ($matches->count() > 10) {
                $this->line("  ... and " . ($matches->count() - 10) . " more matches");
            }
            
            $this->info("\n📈 Estimated processing time: " . ceil($matches->count() / $chunkSize * 0.5) . " minutes");
            return 0;
        }

        // Confirmation for large batches
        if ($matches->count() > 500 && !$this->confirm("⚠️  You're about to process {$matches->count()} matches. This may take a while. Continue?")) {
            $this->info('Operation cancelled.');
            return 0;
        }

        $generated = 0;
        $errors = 0;
        $startTime = microtime(true);

        // Process matches in chunks
        $progressBar = $this->output->createProgressBar($matches->count());
        $progressBar->start();

        foreach ($matches->chunk($chunkSize) as $chunkIndex => $chunk) {
            $this->info("\n🔄 Processing chunk " . ($chunkIndex + 1) . " (" . $chunk->count() . " matches)");
            
            foreach ($chunk as $match) {
                try {
                    // Call the ML predict command for each match
                    $result = $this->call('ml:predict', ['--match-id' => $match->id], $this->output);
                    
                    if ($result === 0) {
                        $generated++;
                    } else {
                        $errors++;
                        $this->newLine();
                        $this->warn("❌ Failed prediction for match ID: {$match->id}");
                    }
                } catch (\Exception $e) {
                    $errors++;
                    $this->newLine();
                    $this->error("❌ Error processing match {$match->id}: " . $e->getMessage());
                }
                
                $progressBar->advance();
            }
            
            // Progress update
            $elapsed = microtime(true) - $startTime;
            $processed = $generated + $errors;
            $rate = $processed > 0 ? $processed / $elapsed : 0;
            $remaining = $matches->count() - $processed;
            $eta = $rate > 0 ? $remaining / $rate : 0;
            
            $this->info("\n📊 Progress: {$processed}/{$matches->count()} | Success: {$generated} | Errors: {$errors} | ETA: " . gmdate('H:i:s', $eta));
            
            // Small delay between chunks
            if ($chunkIndex < $matches->chunk($chunkSize)->count() - 1) {
                usleep(200000); // 0.2 second
            }
        }

        $progressBar->finish();
        
        $totalTime = microtime(true) - $startTime;
        $successRate = ($generated + $errors) > 0 ? round(($generated / ($generated + $errors)) * 100, 1) : 0;
        
        $this->info("\n\n🎉 Historical prediction generation completed!");
        $this->info("⏱️  Total time: " . gmdate('H:i:s', $totalTime));
        $this->info("✅ Generated: {$generated}");
        $this->warn("❌ Errors: {$errors}");
        $this->info("📈 Success rate: {$successRate}%");
        $this->info("⚡ Average rate: " . round($rate, 1) . " predictions/second");

        // Summary of what was accomplished
        if ($generated > 0) {
            $this->info("\n📋 Summary:");
            $this->info("• {$generated} historical matches now have predictions");
            $this->info("• Statistics will now be more comprehensive");
            $this->info("• ML model accuracy can be better evaluated");
            
            // Suggest next steps
            $this->info("\n💡 Recommended next steps:");
            $this->info("• Run: php artisan statistics:update");
            $this->info("• Run: php artisan predictions:update-accuracy");
        }

        return $errors > 0 ? 1 : 0;
    }
}