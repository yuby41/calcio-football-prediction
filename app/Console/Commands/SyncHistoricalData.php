<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\EnhancedFootballApiService;
use App\Services\ApiQuotaManager;
use App\Models\FootballMatch;
use App\Models\Team;
use Carbon\Carbon;

class SyncHistoricalData extends Command
{
    protected $signature = 'football:sync-historical 
                           {--seasons=* : Seasons to sync (e.g., 2019,2020,2021)}
                           {--leagues=* : Leagues to sync (e.g., PL,PD,BL1)}
                           {--start-year=2019 : Starting year}
                           {--end-year=2024 : Ending year}
                           {--dry-run : Show what would be synced without executing}
                           {--force : Force sync even if data exists}';

    protected $description = 'Sync historical football data for ML training (optimized for 7500 daily requests)';

    private EnhancedFootballApiService $apiService;
    private ApiQuotaManager $quotaManager;

    // Liga codes with their API IDs
    private const LEAGUE_MAPPING = [
        'PL' => 39,    // Premier League
        'PD' => 140,   // La Liga
        'BL1' => 78,   // Bundesliga
        'SA' => 135,   // Serie A
        'FL1' => 61,   // Ligue 1
        'PPL' => 94,   // Primeira Liga (Liga Portugal)
        'CL' => 2,     // Champions League
        'EL' => 3,     // Europa League
    ];

    public function __construct(
        EnhancedFootballApiService $apiService,
        ApiQuotaManager $quotaManager
    ) {
        parent::__construct();
        $this->apiService = $apiService;
        $this->quotaManager = $quotaManager;
    }

    public function handle(): int
    {
        $this->info('🚀 HISTORICAL DATA SYNC - ML ENHANCEMENT');
        $this->info('=' . str_repeat('=', 50));

        // Parse input parameters
        $seasons = $this->parseSeasons();
        $leagues = $this->parseLeagues();
        $isDryRun = $this->option('dry-run');

        // Display sync plan
        $this->displaySyncPlan($seasons, $leagues, $isDryRun);

        if (!$isDryRun && !$this->option('no-interaction') && !$this->confirm('Proceed with historical data sync?')) {
            $this->info('Sync cancelled');
            return Command::SUCCESS;
        }

        // Execute sync
        return $this->executeSyncPlan($seasons, $leagues, $isDryRun);
    }

    private function parseSeasons(): array
    {
        $seasons = $this->option('seasons');
        
        if (empty($seasons)) {
            // Generate range from start-year to end-year
            $startYear = (int) $this->option('start-year');
            $endYear = (int) $this->option('end-year');
            
            $seasons = range($startYear, $endYear);
        } else {
            // Parse comma-separated seasons
            $seasons = [];
            foreach ($this->option('seasons') as $seasonInput) {
                $seasons = array_merge($seasons, explode(',', $seasonInput));
            }
            $seasons = array_map('intval', $seasons);
        }

        return array_unique($seasons);
    }

    private function parseLeagues(): array
    {
        $leagues = $this->option('leagues');
        
        if (empty($leagues)) {
            // Default priority leagues
            return ['PL', 'PD', 'BL1', 'SA', 'FL1'];
        }

        $parsedLeagues = [];
        foreach ($leagues as $leagueInput) {
            $parsedLeagues = array_merge($parsedLeagues, explode(',', $leagueInput));
        }

        // Validate leagues
        $validLeagues = [];
        foreach ($parsedLeagues as $league) {
            $league = strtoupper(trim($league));
            if (isset(self::LEAGUE_MAPPING[$league])) {
                $validLeagues[] = $league;
            } else {
                $this->warn("Invalid league code: {$league}");
            }
        }

        return $validLeagues;
    }

    private function displaySyncPlan(array $seasons, array $leagues, bool $isDryRun): void
    {
        $this->info('📋 SYNC PLAN:');
        $this->info('───────────────────────────────────────────────');
        
        $this->line(sprintf('<info>Seasons:</info> %s', implode(', ', $seasons)));
        $this->line(sprintf('<info>Leagues:</info> %s', implode(', ', $leagues)));
        $this->line(sprintf('<info>Mode:</info> %s', $isDryRun ? 'DRY RUN' : 'LIVE SYNC'));

        // Estimate requests
        $estimatedRequests = $this->estimateRequests($seasons, $leagues);
        $this->line(sprintf('<info>Estimated Requests:</info> %d', $estimatedRequests));

        // Check quota
        $quotaStats = $this->quotaManager->getUsageStats();
        $this->line(sprintf('<info>Daily Quota Available:</info> %d/%d (%.1f%%)', 
            $quotaStats['daily']['remaining'], 
            $quotaStats['daily']['limit'],
            100 - $quotaStats['daily']['percentage']
        ));

        if ($estimatedRequests > $quotaStats['daily']['remaining']) {
            $this->error('⚠️  Warning: Estimated requests exceed daily quota remaining');
            $this->line('Consider running over multiple days or reducing scope');
        } else {
            $this->info('✅ Sufficient quota available for complete sync');
        }

        $this->info('');
    }

    private function estimateRequests(array $seasons, array $leagues): int
    {
        $requestsPerSeasonPerLeague = 50; // Conservative estimate
        return count($seasons) * count($leagues) * $requestsPerSeasonPerLeague;
    }

    private function executeSyncPlan(array $seasons, array $leagues, bool $isDryRun): int
    {
        $totalRequests = 0;
        $totalMatches = 0;
        $errors = 0;

        $this->info('🔄 Starting historical data sync...');
        $this->newLine();

        foreach ($leagues as $leagueCode) {
            $leagueId = self::LEAGUE_MAPPING[$leagueCode];
            $this->info("📊 Processing {$leagueCode} (ID: {$leagueId})");

            foreach ($seasons as $season) {
                $this->line("  └─ Season {$season}...");

                if ($isDryRun) {
                    $this->line("     [DRY RUN] Would sync {$leagueCode} {$season}");
                    continue;
                }

                try {
                    $result = $this->syncLeagueSeason($leagueCode, $season, $leagueId);
                    $totalRequests += $result['requests'];
                    $totalMatches += $result['matches'];

                    $this->line(sprintf(
                        "     ✅ %d matches, %d requests",
                        $result['matches'],
                        $result['requests']
                    ));

                    // Progressive delay to manage quota
                    sleep(2);

                } catch (\Exception $e) {
                    $errors++;
                    $this->error("     ❌ Error: " . $e->getMessage());
                }
            }

            $this->newLine();
        }

        // Summary
        $this->displaySyncSummary($totalRequests, $totalMatches, $errors, $isDryRun);

        return $errors > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    private function syncLeagueSeason(string $leagueCode, int $season, int $leagueId): array
    {
        $requests = 0;
        $matches = 0;

        // Check if we have capacity
        if (!$this->quotaManager->canMakeRequest("historical_sync_{$leagueCode}_{$season}", 2)) {
            throw new \Exception("Quota exceeded for {$leagueCode} {$season}");
        }

        // Use the optimized sync command for this specific league and season
        $exitCode = $this->call('football:sync-fixtures-optimized', [
            '--type' => 'season',
            '--leagues' => $leagueCode,
            '--season' => $season,
            '--with-stats' => true,
            '--silent' => true
        ]);

        if ($exitCode !== 0) {
            throw new \Exception("Sync command failed for {$leagueCode} {$season}");
        }

        // Count matches synced
        $matchCount = FootballMatch::where('league', $leagueCode)
            ->whereYear('match_date', $season)
            ->count();

        return [
            'requests' => 30, // Estimate based on typical season sync
            'matches' => $matchCount
        ];
    }

    private function displaySyncSummary(int $totalRequests, int $totalMatches, int $errors, bool $isDryRun): void
    {
        $this->info('📈 SYNC SUMMARY:');
        $this->info('═══════════════════════════════════════════════');
        
        if ($isDryRun) {
            $this->line('<comment>DRY RUN COMPLETED</comment>');
        } else {
            $this->line(sprintf('<info>Total Requests Used:</info> %d', $totalRequests));
            $this->line(sprintf('<info>Total Matches Synced:</info> %d', $totalMatches));
            
            if ($errors > 0) {
                $this->line(sprintf('<error>Errors Encountered:</error> %d', $errors));
            } else {
                $this->line('<info>Status:</info> ✅ All operations completed successfully');
            }

            // Show updated quota
            $quotaStats = $this->quotaManager->getUsageStats();
            $this->line(sprintf('<info>Remaining Daily Quota:</info> %d/%d (%.1f%%)', 
                $quotaStats['daily']['remaining'], 
                $quotaStats['daily']['limit'],
                100 - $quotaStats['daily']['percentage']
            ));

            $this->newLine();
            $this->info('🎯 NEXT STEPS:');
            $this->line('1. Run: php artisan ml:train --use-expanded-dataset');
            $this->line('2. Run: php artisan ml:validate --test-historical');
            $this->line('3. Check: php artisan api:monitor --detailed');
        }
    }
}