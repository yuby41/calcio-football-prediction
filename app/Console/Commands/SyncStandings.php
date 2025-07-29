<?php

namespace App\Console\Commands;

use App\Services\EnhancedFootballApiService;
use Illuminate\Console\Command;

class SyncStandings extends Command
{
    protected $signature = 'football:sync-standings
                           {--leagues=* : Specific leagues (PL, PD, BL1, SA, FL1)}
                           {--season= : Specific season (default: current year)}
                           {--with-stats : Also sync team statistics}';

    protected $description = 'Sync league standings and optionally team statistics (recommended: every 6 hours)';

    public function handle()
    {
        $service = new EnhancedFootballApiService();
        $leagues = $this->option('leagues') ?: ['PL', 'PD', 'BL1', 'SA', 'FL1'];
        $season = (int) ($this->option('season') ?: date('Y'));
        $withStats = $this->option('with-stats');

        $this->info("Syncing standings for season {$season}...");
        
        if ($withStats) {
            $this->warn('Including team statistics will use more API requests.');
        }

        $totalProcessed = 0;
        $bar = $this->output->createProgressBar(count($leagues));

        foreach ($leagues as $league) {
            $this->line("Processing {$league}...");
            
            try {
                // Fetch standings
                $standings = $service->fetchStandings($league, $season);
                
                if (empty($standings)) {
                    $this->warn("No standings found for {$league}");
                    continue;
                }

                $this->info("Found standings for {$league}");
                $leagueData = $standings[0] ?? [];
                $teams = $leagueData['league']['standings'][0] ?? [];
                
                $this->line("Teams in {$league}: " . count($teams));
                
                // Process each team in standings
                foreach ($teams as $teamStanding) {
                    $teamData = $teamStanding['team'] ?? [];
                    $stats = $teamStanding['all'] ?? [];
                    
                    $this->processTeamStanding($teamData, $stats, $league, $season);
                    
                    // Fetch detailed team statistics if requested
                    if ($withStats && isset($teamData['id'])) {
                        $this->fetchTeamStatistics($service, $teamData['id'], $league, $season);
                    }
                    
                    $totalProcessed++;
                }
                
                // Rate limiting between leagues
                if (count($leagues) > 1) {
                    sleep(2); // 2 second delay
                }
                
            } catch (\Exception $e) {
                $this->error("Failed to sync {$league}: " . $e->getMessage());
            }
            
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Standings sync completed! Processed {$totalProcessed} teams.");
        
        // Show rate limit status
        $this->showRateLimitStatus($service);
    }

    private function processTeamStanding(array $teamData, array $stats, string $league, int $season): void
    {
        if (empty($teamData)) return;

        // Here you would update the team standings in your database
        // Example structure:
        $standingData = [
            'team_id' => $teamData['id'],
            'team_name' => $teamData['name'],
            'league' => $league,
            'season' => $season,
            'position' => $stats['rank'] ?? null,
            'points' => $stats['points'] ?? 0,
            'played' => $stats['played'] ?? 0,
            'wins' => $stats['win'] ?? 0,
            'draws' => $stats['draw'] ?? 0,  
            'losses' => $stats['lose'] ?? 0,
            'goals_for' => $stats['goals']['for'] ?? 0,
            'goals_against' => $stats['goals']['against'] ?? 0,
            'goal_difference' => ($stats['goals']['for'] ?? 0) - ($stats['goals']['against'] ?? 0),
            'form' => $stats['form'] ?? null,
        ];

        // Log the data (in real implementation, save to database)
        $this->line(sprintf(
            "%d. %s - %d pts (%d played, GD: %+d)",
            $standingData['position'],
            $standingData['team_name'],
            $standingData['points'],
            $standingData['played'],
            $standingData['goal_difference']
        ));
    }

    private function fetchTeamStatistics(EnhancedFootballApiService $service, int $teamId, string $league, int $season): void
    {
        try {
            $leagueId = $this->getLeagueId($league);
            $teamStats = $service->fetchTeamStatistics($teamId, $leagueId, $season);
            
            if (!empty($teamStats)) {
                // Process detailed team statistics
                $this->processTeamStatistics($teamId, $teamStats);
            }
            
            // Rate limiting for team statistics
            usleep(500000); // 500ms delay
            
        } catch (\Exception $e) {
            $this->error("Failed to fetch stats for team {$teamId}: " . $e->getMessage());
        }
    }

    private function processTeamStatistics(int $teamId, array $teamStats): void
    {
        // Here you would process and store detailed team statistics
        // The API returns comprehensive data including:
        // - Home/away performance
        // - Goals statistics
        // - Cards, corners, etc.
        // - Form and streaks
        
        $fixtures = $teamStats['fixtures'] ?? [];
        $goals = $teamStats['goals'] ?? [];
        $cards = $teamStats['cards'] ?? [];
        $form = $teamStats['form'] ?? '';
        
        // Log summary (in real implementation, save to database)
        $this->line("  Stats: " . 
            ($fixtures['played']['total'] ?? 0) . " played, " .
            "Form: " . ($form ?: 'N/A')
        );
    }

    private function getLeagueId(string $league): int
    {
        return match($league) {
            'PL' => 39,   // Premier League
            'PD' => 140,  // La Liga
            'BL1' => 78,  // Bundesliga
            'SA' => 135,  // Serie A
            'FL1' => 61,  // Ligue 1
            default => 39,
        };
    }

    private function showRateLimitStatus(EnhancedFootballApiService $service): void
    {
        $rateLimit = $service->checkRateLimit();
        
        if (!empty($rateLimit)) {
            $this->newLine();
            $this->info('📊 Rate Limit Status:');
            $this->line("📅 Daily: {$rateLimit['requests_remaining']}/{$rateLimit['requests_limit']} remaining");
            $this->line("⏱️  Per minute: {$rateLimit['requests_per_minute_remaining']}/{$rateLimit['requests_per_minute_limit']} remaining");
            
            // Warning if approaching limits
            $dailyUsage = ($rateLimit['requests_limit'] - $rateLimit['requests_remaining']) / $rateLimit['requests_limit'];
            
            if ($dailyUsage > 0.8) {
                $this->warn('⚠️  Daily rate limit at ' . round($dailyUsage * 100, 1) . '% usage!');
            }
        }
    }
}