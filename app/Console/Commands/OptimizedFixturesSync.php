<?php

namespace App\Console\Commands;

use App\Services\EnhancedFootballApiService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class OptimizedFixturesSync extends Command
{
    protected $signature = 'football:sync-fixtures-optimized
                           {--type=today : Type of sync (today, live, week, season)}
                           {--leagues=* : Specific leagues (PL, PD, BL1, SA, FL1)}
                           {--date= : Specific date (Y-m-d format)}
                           {--with-events : Include match events}
                           {--with-stats : Include match statistics}';

    protected $description = 'Optimized fixtures sync with smart caching and rate limiting';

    private EnhancedFootballApiService $service;

    public function handle()
    {
        $this->service = new EnhancedFootballApiService();
        $type = $this->option('type');
        
        $this->info("Starting optimized fixtures sync: {$type}");
        
        match ($type) {
            'today' => $this->syncTodayMatches(),
            'live' => $this->syncLiveMatches(),
            'week' => $this->syncWeekMatches(),
            'season' => $this->syncSeasonMatches(),
            default => $this->error("Invalid sync type: {$type}")
        };
        
        $this->showRateLimitStatus();
    }

    /**
     * Sync today's matches - Cache for 15 minutes
     * Recommended frequency: Every 15-30 minutes
     */
    private function syncTodayMatches(): void
    {
        $this->info('Syncing today\'s matches...');
        
        try {
            $matches = $this->service->fetchTodayMatches();
            $this->info("Found " . count($matches) . " matches for today");
            
            // Process matches with events if requested
            if ($this->option('with-events')) {
                $this->info('Fetching match events...');
                $this->syncMatchEvents($matches);
            }
            
            // Process matches with statistics if requested  
            if ($this->option('with-stats')) {
                $this->info('Fetching match statistics...');
                $this->syncMatchStatistics($matches);
            }
            
            $this->info('Today\'s matches sync completed successfully!');
            
        } catch (\Exception $e) {
            $this->error('Failed to sync today\'s matches: ' . $e->getMessage());
        }
    }

    /**
     * Sync live matches - No cache, real-time
     * Recommended frequency: Every 15 seconds during match days
     */
    private function syncLiveMatches(): void
    {
        $this->info('Syncing live matches (real-time)...');
        
        try {
            $liveMatches = $this->service->fetchLiveMatches();
            
            if (empty($liveMatches)) {
                $this->info('No live matches currently.');
                return;
            }
            
            $this->info("Found " . count($liveMatches) . " live matches");
            
            $bar = $this->output->createProgressBar(count($liveMatches));
            
            foreach ($liveMatches as $match) {
                $this->line("Live: {$match['home_team_name']} vs {$match['away_team_name']}");
                
                // Always fetch events for live matches
                $this->syncSingleMatchEvents($match['external_id']);
                
                // Rate limiting for live updates
                usleep(200000); // 200ms delay between matches
                $bar->advance();
            }
            
            $bar->finish();
            $this->newLine();
            $this->info('Live matches sync completed!');
            
        } catch (\Exception $e) {
            $this->error('Failed to sync live matches: ' . $e->getMessage());
        }
    }

    /**
     * Sync week's matches - Cache for 1 hour for future, 1 week for past
     * Recommended frequency: Every 2-6 hours
     */
    private function syncWeekMatches(): void
    {
        $this->info('Syncing week\'s matches...');
        
        $from = Carbon::now()->subDays(3)->format('Y-m-d');
        $to = Carbon::now()->addDays(4)->format('Y-m-d');
        
        $leagues = $this->option('leagues') ?: ['PL', 'PD', 'BL1', 'SA', 'FL1'];
        
        $totalMatches = 0;
        
        foreach ($leagues as $league) {
            try {
                $this->line("Fetching {$league} matches from {$from} to {$to}...");
                
                $matches = $this->service->fetchMatchesByDateRange($from, $to, $league);
                $matchCount = count($matches);
                $totalMatches += $matchCount;
                
                $this->info("Found {$matchCount} matches for {$league}");
                
                // Rate limiting between leagues
                if (count($leagues) > 1) {
                    sleep(2); // 2 second delay between leagues
                }
                
            } catch (\Exception $e) {
                $this->error("Failed to sync {$league}: " . $e->getMessage());
            }
        }
        
        $this->info("Week sync completed! Total matches: {$totalMatches}");
    }

    /**
     * Sync full season - Cache for 1 week (historical) or 1 day (current season)
     * Recommended frequency: Once per day or less
     */
    private function syncSeasonMatches(): void
    {
        $this->info('Syncing season matches...');
        
        $leagues = $this->option('leagues') ?: ['PL'];
        $season = date('Y');
        
        $this->warn("WARNING: Season sync uses many API requests!");
        $this->warn("Estimated requests: " . (count($leagues) * 50) . " requests");
        
        if (!$this->confirm('Continue with season sync?')) {
            return;
        }
        
        $totalMatches = 0;
        
        foreach ($leagues as $league) {
            try {
                $this->line("Syncing full season for {$league}...");
                
                $matches = $this->service->fetchFixtures($league, $season);
                $matchCount = count($matches);
                $totalMatches += $matchCount;
                
                $this->info("Found {$matchCount} matches for {$league} season {$season}");
                
                // Longer delay for season sync to avoid rate limits
                if (count($leagues) > 1) {
                    $this->info('Waiting 10 seconds before next league...');
                    sleep(10);
                }
                
            } catch (\Exception $e) {
                $this->error("Failed to sync {$league} season: " . $e->getMessage());
            }
        }
        
        $this->info("Season sync completed! Total matches: {$totalMatches}");
    }

    /**
     * Sync match events for multiple matches
     */
    private function syncMatchEvents(array $matches): void
    {
        $liveOrFinished = array_filter($matches, function ($match) {
            return in_array($match['status'], ['live', 'finished']);
        });
        
        if (empty($liveOrFinished)) {
            $this->info('No matches requiring event sync.');
            return;
        }
        
        $bar = $this->output->createProgressBar(count($liveOrFinished));
        
        foreach ($liveOrFinished as $match) {
            $this->syncSingleMatchEvents($match['external_id']);
            
            // Rate limiting for events
            usleep(500000); // 500ms delay
            $bar->advance();
        }
        
        $bar->finish();
        $this->newLine();
    }

    /**
     * Sync events for a single match
     */
    private function syncSingleMatchEvents(string $fixtureId): void
    {
        try {
            $events = $this->service->fetchFixtureEvents((int) $fixtureId);
            // Here you would process and store events
            // $this->processMatchEvents($fixtureId, $events);
            
        } catch (\Exception $e) {
            $this->error("Failed to sync events for fixture {$fixtureId}: " . $e->getMessage());
        }
    }

    /**
     * Sync match statistics for multiple matches
     */
    private function syncMatchStatistics(array $matches): void
    {
        $finishedMatches = array_filter($matches, function ($match) {
            return $match['status'] === 'finished';
        });
        
        if (empty($finishedMatches)) {
            $this->info('No finished matches for statistics sync.');
            return;
        }
        
        $bar = $this->output->createProgressBar(count($finishedMatches));
        
        foreach ($finishedMatches as $match) {
            try {
                $stats = $this->service->fetchFixtureStatistics((int) $match['external_id']);
                // Here you would process and store statistics
                // $this->processMatchStatistics($match['external_id'], $stats);
                
                // Rate limiting for statistics
                usleep(750000); // 750ms delay
                
            } catch (\Exception $e) {
                $this->error("Failed to sync stats for match {$match['external_id']}: " . $e->getMessage());
            }
            
            $bar->advance();
        }
        
        $bar->finish();
        $this->newLine();
    }

    /**
     * Show current rate limit status
     */
    private function showRateLimitStatus(): void
    {
        $rateLimit = $this->service->checkRateLimit();
        
        if (!empty($rateLimit)) {
            $this->newLine();
            $this->info('📊 Rate Limit Status:');
            $this->line("📅 Daily: {$rateLimit['requests_remaining']}/{$rateLimit['requests_limit']} remaining");
            $this->line("⏱️  Per minute: {$rateLimit['requests_per_minute_remaining']}/{$rateLimit['requests_per_minute_limit']} remaining");
            
            // Warning if getting close to limits
            $dailyUsage = ($rateLimit['requests_limit'] - $rateLimit['requests_remaining']) / $rateLimit['requests_limit'];
            $minuteUsage = ($rateLimit['requests_per_minute_limit'] - $rateLimit['requests_per_minute_remaining']) / $rateLimit['requests_per_minute_limit'];
            
            if ($dailyUsage > 0.8) {
                $this->warn('⚠️  Daily rate limit at ' . round($dailyUsage * 100, 1) . '% usage!');
            }
            
            if ($minuteUsage > 0.7) {
                $this->warn('⚠️  Per-minute rate limit at ' . round($minuteUsage * 100, 1) . '% usage!');
            }
        }
    }
}