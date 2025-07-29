<?php

namespace App\Console\Commands;

use App\Services\EnhancedFootballApiService;
use Illuminate\Console\Command;

class SyncLeaguesAndTeams extends Command
{
    protected $signature = 'football:sync-leagues-teams 
                           {--leagues=* : Specific leagues to sync (PL, PD, BL1, SA, FL1)}
                           {--season= : Specific season (default: current year)}
                           {--force : Force refresh cached data}';

    protected $description = 'Sync leagues and teams data (recommended: once per season)';

    public function handle()
    {
        $service = new EnhancedFootballApiService();
        $leagues = $this->option('leagues') ?: ['PL', 'PD', 'BL1', 'SA', 'FL1'];
        $season = $this->option('season') ?: date('Y');
        $force = $this->option('force');

        if ($force) {
            $this->info('Forcing refresh of cached data...');
            // Clear relevant caches
            cache()->flush();
        }

        $this->info("Syncing leagues and teams for season {$season}...");
        
        $totalTeams = 0;
        $bar = $this->output->createProgressBar(count($leagues));

        foreach ($leagues as $league) {
            $this->line("Syncing {$league}...");
            
            try {
                // Fetch teams for this league
                $teams = $service->fetchTeams($league, $season);
                $this->info("Found " . count($teams) . " teams for {$league}");
                
                // Here you would sync teams to database
                // $totalTeams += $this->syncTeamsToDatabase($teams, $league);
                
                $totalTeams += count($teams);
                
                // Rate limiting - wait between leagues to avoid hitting limits
                if (count($leagues) > 1) {
                    sleep(1); // 1 second delay between leagues
                }
                
            } catch (\Exception $e) {
                $this->error("Failed to sync {$league}: " . $e->getMessage());
            }
            
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Completed! Processed {$totalTeams} teams across " . count($leagues) . " leagues.");
        
        // Show rate limit status
        $this->showRateLimitStatus($service);
    }

    private function showRateLimitStatus(EnhancedFootballApiService $service): void
    {
        $rateLimit = $service->checkRateLimit();
        
        if (!empty($rateLimit)) {
            $this->info('Rate Limit Status:');
            $this->line("Daily: {$rateLimit['requests_remaining']}/{$rateLimit['requests_limit']} remaining");
            $this->line("Per minute: {$rateLimit['requests_per_minute_remaining']}/{$rateLimit['requests_per_minute_limit']} remaining");
        }
    }
}