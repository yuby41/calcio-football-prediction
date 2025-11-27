<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\RealDataService;
use App\Models\FootballMatch;
use App\Models\MatchPrediction;
use App\Models\TeamStatistic;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MigrateSyntheticToRealData extends Command
{
    protected $signature = 'data:migrate-to-real 
                            {--matches : Migrate match predictions}
                            {--teams : Migrate team statistics}
                            {--odds : Migrate odds data}
                            {--all : Migrate all data types}
                            {--limit=50 : Limit number of records to process}
                            {--force : Force migration without confirmation}';

    protected $description = 'Migrate synthetic/AI-generated data to real data from API-Sports';

    private RealDataService $realDataService;
    private int $processed = 0;
    private int $successful = 0;
    private int $failed = 0;

    public function __construct(RealDataService $realDataService)
    {
        parent::__construct();
        $this->realDataService = $realDataService;
    }

    public function handle(): int
    {
        $this->info('🚀 Starting migration from synthetic to real data...');
        
        if (!$this->option('force') && !$this->confirm('This will replace synthetic data with real API data. Continue?')) {
            $this->info('Migration cancelled.');
            return 0;
        }

        $limit = (int) $this->option('limit');
        $startTime = microtime(true);

        try {
            // Check API connection first
            $this->info('🔍 Checking API connection...');
            if (!$this->checkApiConnection()) {
                $this->error('❌ Cannot connect to API-Sports. Aborting migration.');
                return 1;
            }
            $this->info('✅ API connection successful');

            // Migrate based on options
            if ($this->option('all') || $this->option('matches')) {
                $this->migrateMatchPredictions($limit);
            }

            if ($this->option('all') || $this->option('teams')) {
                $this->migrateTeamStatistics($limit);
            }

            if ($this->option('all') || $this->option('odds')) {
                $this->migrateOddsData($limit);
            }

            $this->displayResults($startTime);
            
            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Migration failed: " . $e->getMessage());
            Log::error("Migration error", ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return 1;
        }
    }

    private function checkApiConnection(): bool
    {
        try {
            // Test with a simple API call
            $testData = $this->realDataService->getRealFormFromRecentMatches(1, 1);
            return true;
        } catch (\Exception $e) {
            Log::error("API connection test failed: " . $e->getMessage());
            return false;
        }
    }

    private function migrateMatchPredictions(int $limit): void
    {
        $this->info('📊 Migrating match predictions from synthetic to real data...');
        
        // Get matches with synthetic predictions
        $matches = FootballMatch::whereHas('prediction', function ($query) {
            $query->where('model_version', 'like', '%enhanced%')
                  ->orWhere('model_version', 'like', '%simple%')
                  ->orWhere('features_used', 'like', '%synthetic%');
        })
        ->with('prediction')
        ->limit($limit)
        ->get();

        $this->info("Found {$matches->count()} matches with synthetic predictions");

        $progressBar = $this->output->createProgressBar($matches->count());
        $progressBar->start();

        foreach ($matches as $match) {
            $this->processed++;
            
            try {
                // Attempt to get real data for this match
                $migrated = $this->realDataService->migrateMatchPredictionsToRealData($match->external_id);
                
                if ($migrated) {
                    $this->successful++;
                    
                    // Mark the original prediction as migrated
                    $match->prediction->update([
                        'model_version' => 'real_data_v1.0_migrated',
                        'features_used' => json_encode([
                            'migration_date' => now(),
                            'original_version' => $match->prediction->model_version,
                            'data_source' => 'api_sports_real_data'
                        ])
                    ]);
                    
                } else {
                    $this->failed++;
                    Log::warning("Could not migrate match prediction", [
                        'match_id' => $match->id,
                        'external_id' => $match->external_id
                    ]);
                }
                
            } catch (\Exception $e) {
                $this->failed++;
                Log::error("Error migrating match prediction", [
                    'match_id' => $match->id,
                    'error' => $e->getMessage()
                ]);
            }
            
            $progressBar->advance();
            
            // Rate limiting - pause between requests
            usleep(100000); // 0.1 second pause
        }

        $progressBar->finish();
        $this->newLine();
        $this->info("✅ Match predictions migration completed");
    }

    private function migrateTeamStatistics(int $limit): void
    {
        $this->info('📈 Migrating team statistics from calculated to real data...');
        
        // Get teams with calculated statistics
        $teamStats = TeamStatistic::where(function ($query) {
            $query->whereNotNull('avg_goals_for')
                  ->orWhereNotNull('avg_goals_against')
                  ->whereRaw('JSON_EXTRACT(form, "$.calculated") = true');
        })
        ->limit($limit)
        ->get();

        $this->info("Found {$teamStats->count()} team statistics to migrate");

        $progressBar = $this->output->createProgressBar($teamStats->count());
        $progressBar->start();

        foreach ($teamStats as $stats) {
            $this->processed++;
            
            try {
                // Get real statistics from API
                $realStats = $this->realDataService->getRealTeamStatistics(
                    $stats->team->external_id,
                    $this->getLeagueIdFromTeam($stats->team),
                    $stats->season ?? date('Y')
                );
                
                if ($realStats) {
                    // Update with real data
                    $stats->update([
                        'matches_played' => $realStats['matches_played'],
                        'wins' => $realStats['wins'],
                        'draws' => $realStats['draws'],
                        'losses' => $realStats['losses'],
                        'goals_for' => $realStats['goals_for'],
                        'goals_against' => $realStats['goals_against'],
                        'avg_goals_for' => $realStats['real_avg_goals_for'],
                        'avg_goals_against' => $realStats['real_avg_goals_against'],
                        'form' => json_encode([
                            'real_form' => $realStats['real_form'],
                            'data_source' => 'api_sports_real_data',
                            'migration_date' => now()
                        ]),
                        'home_stats' => json_encode($realStats['home_stats']),
                        'away_stats' => json_encode($realStats['away_stats']),
                    ]);
                    
                    $this->successful++;
                } else {
                    $this->failed++;
                }
                
            } catch (\Exception $e) {
                $this->failed++;
                Log::error("Error migrating team statistics", [
                    'team_id' => $stats->team_id,
                    'error' => $e->getMessage()
                ]);
            }
            
            $progressBar->advance();
            usleep(200000); // 0.2 second pause for team stats (heavier API calls)
        }

        $progressBar->finish();
        $this->newLine();
        $this->info("✅ Team statistics migration completed");
    }

    private function migrateOddsData(int $limit): void
    {
        $this->info('💰 Migrating synthetic odds to real bookmaker odds...');
        
        // Get recent matches without real odds
        $matches = FootballMatch::where('match_date', '>=', now()->subDays(30))
            ->where('match_date', '<=', now()->addDays(7))
            ->whereNull('odds')
            ->limit($limit)
            ->get();

        $this->info("Found {$matches->count()} matches needing real odds data");

        $progressBar = $this->output->createProgressBar($matches->count());
        $progressBar->start();

        foreach ($matches as $match) {
            $this->processed++;
            
            try {
                $realOdds = $this->realDataService->getRealOddsForFixture($match->external_id);
                
                if ($realOdds) {
                    $match->update([
                        'odds' => json_encode($realOdds['odds']),
                    ]);
                    
                    $this->successful++;
                } else {
                    $this->failed++;
                }
                
            } catch (\Exception $e) {
                $this->failed++;
                Log::error("Error migrating odds data", [
                    'match_id' => $match->id,
                    'error' => $e->getMessage()
                ]);
            }
            
            $progressBar->advance();
            usleep(150000); // 0.15 second pause
        }

        $progressBar->finish();
        $this->newLine();
        $this->info("✅ Odds data migration completed");
    }

    private function getLeagueIdFromTeam($team): int
    {
        // Map team leagues to API league IDs
        $leagueMap = [
            'Premier League' => 39,
            'La Liga' => 140,
            'Bundesliga' => 78,
            'Serie A' => 135,
            'Ligue 1' => 61,
        ];

        return $leagueMap[$team->league] ?? 39; // Default to Premier League
    }

    private function displayResults(float $startTime): void
    {
        $executionTime = round(microtime(true) - $startTime, 2);
        
        $this->newLine(2);
        $this->info('📋 Migration Results:');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Processed', $this->processed],
                ['Successful Migrations', $this->successful],
                ['Failed Migrations', $this->failed],
                ['Success Rate', $this->processed > 0 ? round(($this->successful / $this->processed) * 100, 1) . '%' : '0%'],
                ['Execution Time', $executionTime . ' seconds'],
            ]
        );

        if ($this->successful > 0) {
            $this->info("🎉 Successfully migrated {$this->successful} records to real data!");
        }

        if ($this->failed > 0) {
            $this->warn("⚠️  {$this->failed} migrations failed - check logs for details");
        }

        // Show next steps
        $this->newLine();
        $this->info('🎯 Next Steps:');
        $this->line('1. Review migration logs for any failed records');
        $this->line('2. Test the application with real data');
        $this->line('3. Update frontend components to handle real data format');
        $this->line('4. Set up automated real data sync with: php artisan schedule:work');
    }
}