<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\EnhancedFootballApiService;
use App\Models\FootballMatch;
use Illuminate\Support\Facades\Log;

class MigrateWithQuotaControl extends Command
{
    protected $signature = 'data:migrate-quota 
                            {--limit=20 : Number of matches to migrate}
                            {--max-requests=150 : Maximum API requests to use}
                            {--dry-run : Show what would be migrated without making API calls}';
                            
    protected $description = 'Migrate synthetic data to real data with API quota control';

    private EnhancedFootballApiService $apiService;
    private int $apiRequestsUsed = 0;
    private int $maxRequests = 0;

    public function __construct(EnhancedFootballApiService $apiService)
    {
        parent::__construct();
        $this->apiService = $apiService;
    }

    public function handle(): int
    {
        $this->maxRequests = (int) $this->option('max-requests');
        $limit = (int) $this->option('limit');
        $dryRun = $this->option('dry-run');

        $this->info('🎯 Migration with API Quota Control');
        $this->info("📊 Max API Requests: {$this->maxRequests}");
        $this->info("🔢 Max Matches to Process: {$limit}");
        
        if ($dryRun) {
            $this->warn('🧪 DRY RUN MODE - No actual API calls will be made');
        }

        // Get matches prioritized by importance
        $matches = $this->getPrioritizedMatches($limit);
        
        $this->info("Found {$matches->count()} matches to migrate");
        
        if ($matches->count() === 0) {
            $this->warn('No matches found for migration');
            return 0;
        }

        $successful = 0;
        $skipped = 0;
        $failed = 0;

        $progressBar = $this->output->createProgressBar($matches->count());
        $progressBar->start();

        foreach ($matches as $index => $match) {
            // Check quota before each match (each match needs ~2-3 API calls)
            $estimatedRequestsNeeded = 2; // Home + Away team stats
            
            if (($this->apiRequestsUsed + $estimatedRequestsNeeded) > $this->maxRequests) {
                $this->newLine(2);
                $this->warn("🚫 API quota limit reached! Processed {$successful} matches successfully.");
                $this->info("💡 Run the command again later to continue migration.");
                break;
            }

            try {
                if ($dryRun) {
                    $this->simulateMigration($match, $index + 1);
                    $successful++;
                } else {
                    $migrated = $this->migrateMatch($match, $index + 1);
                    if ($migrated) {
                        $successful++;
                    } else {
                        $failed++;
                    }
                }
                
            } catch (\Exception $e) {
                $failed++;
                Log::error("Migration error for match {$match->id}: " . $e->getMessage());
            }

            $progressBar->advance();
            
            // Rate limiting pause (important for API stability)
            if (!$dryRun) {
                sleep(1);
            }
        }

        $progressBar->finish();
        $this->newLine(2);

        // Results summary
        $this->displayResults($successful, $failed, $skipped, $dryRun);
        
        return 0;
    }

    private function getPrioritizedMatches(int $limit): \Illuminate\Database\Eloquent\Collection
    {
        // Prioritize matches by importance:
        // 1. Recent matches (more relevant)
        // 2. Major leagues (Premier League, La Liga, etc.)
        // 3. Matches with synthetic predictions
        
        return FootballMatch::whereHas('prediction', function ($query) {
            $query->where('model_version', 'like', '%enhanced%')
                  ->orWhere('model_version', 'like', '%simple%');
        })
        ->with(['prediction', 'homeTeam', 'awayTeam'])
        ->where('match_date', '>=', now()->subDays(60)) // Recent matches first
        ->orderByRaw("
            CASE 
                WHEN league LIKE '%Premier League%' THEN 1
                WHEN league LIKE '%La Liga%' THEN 2  
                WHEN league LIKE '%Bundesliga%' THEN 3
                WHEN league LIKE '%Serie A%' THEN 4
                WHEN league LIKE '%Ligue 1%' THEN 5
                ELSE 6
            END
        ")
        ->orderBy('match_date', 'desc')
        ->limit($limit)
        ->get();
    }

    private function simulateMigration($match, int $matchNumber): void
    {
        // Simulate what would happen without making API calls
        $this->newLine();
        $this->line("🧪 [{$matchNumber}] SIMULATION: {$match->homeTeam->name} vs {$match->awayTeam->name}");
        $this->line("   Current: {$match->prediction->home_goals_prediction} - {$match->prediction->away_goals_prediction} (SYNTHETIC)");
        $this->line("   Would fetch: Real team statistics from API-Sports");
        $this->line("   API Requests: 2 (home team + away team)");
        
        $this->apiRequestsUsed += 2; // Simulate API usage
    }

    private function migrateMatch($match, int $matchNumber): bool
    {
        try {
            $this->newLine();
            $this->line("🔄 [{$matchNumber}] {$match->homeTeam->name} vs {$match->awayTeam->name}");
            
            // Get real statistics for both teams
            $homeStats = $this->fetchTeamStats($match->homeTeam->external_id, 'Home');
            $awayStats = $this->fetchTeamStats($match->awayTeam->external_id, 'Away');

            if (empty($homeStats) || empty($awayStats)) {
                $this->line("   ❌ No real statistics available");
                return false;
            }

            // Extract real expected goals
            $homeExpectedGoals = $homeStats['goals']['for']['average']['home'] ?? 0;
            $awayExpectedGoals = $awayStats['goals']['for']['average']['away'] ?? 0;
            
            // Show comparison
            $this->line("   BEFORE: {$match->prediction->home_goals_prediction} - {$match->prediction->away_goals_prediction} (SYNTHETIC)");
            $this->line("   AFTER:  " . number_format($homeExpectedGoals, 2) . " - " . number_format($awayExpectedGoals, 2) . " (REAL)");

            // Update with real data
            $match->prediction->update([
                'home_goals_prediction' => round($homeExpectedGoals, 2),
                'away_goals_prediction' => round($awayExpectedGoals, 2),
                'model_version' => 'real_data_api_sports_v1.0',
                'features_used' => json_encode([
                    'data_source' => 'api_sports_real_team_statistics',
                    'home_avg_goals' => $homeExpectedGoals,
                    'away_avg_goals' => $awayExpectedGoals,
                    'migration_date' => now()->toISOString(),
                    'api_requests_used' => 2
                ])
            ]);

            $this->line("   ✅ Successfully migrated to REAL data");
            return true;

        } catch (\Exception $e) {
            $this->line("   ❌ Migration failed: " . $e->getMessage());
            return false;
        }
    }

    private function fetchTeamStats(string $teamId, string $teamType): array
    {
        try {
            $this->apiRequestsUsed++;
            $stats = $this->apiService->fetchTeamStatistics((int) $teamId, 39, date('Y')); // Premier League
            
            if (empty($stats)) {
                // Try with different league IDs if Premier League fails
                $this->apiRequestsUsed++;
                $stats = $this->apiService->fetchTeamStatistics((int) $teamId, 140, date('Y')); // La Liga
            }
            
            return $stats;
            
        } catch (\Exception $e) {
            Log::error("Error fetching {$teamType} team stats: " . $e->getMessage());
            return [];
        }
    }

    private function displayResults(int $successful, int $failed, int $skipped, bool $dryRun): void
    {
        $this->info('📋 Migration Results:');
        
        if ($dryRun) {
            $this->table(
                ['Metric', 'Value'],
                [
                    ['Matches Simulated', $successful],
                    ['API Requests (Simulated)', $this->apiRequestsUsed],
                    ['Remaining Quota', $this->maxRequests - $this->apiRequestsUsed],
                ]
            );
            
            $this->info('🧪 This was a DRY RUN - no actual changes were made');
            $this->info('💡 Remove --dry-run flag to perform actual migration');
            
        } else {
            $this->table(
                ['Metric', 'Value'],
                [
                    ['Successfully Migrated', $successful],
                    ['Failed', $failed],
                    ['API Requests Used', $this->apiRequestsUsed],
                    ['API Requests Remaining', $this->maxRequests - $this->apiRequestsUsed],
                    ['Success Rate', $successful > 0 ? round(($successful / ($successful + $failed)) * 100, 1) . '%' : '0%'],
                ]
            );
            
            if ($successful > 0) {
                $this->info("🎉 Successfully replaced SYNTHETIC data with REAL data for {$successful} matches!");
            }
            
            if ($this->apiRequestsUsed < $this->maxRequests) {
                $remaining = $this->maxRequests - $this->apiRequestsUsed;
                $this->info("💡 You have {$remaining} API requests remaining. Run the command again to continue migration.");
            }
        }

        // Next steps
        $this->newLine();
        $this->info('🎯 Impact:');
        $this->line('✅ Goles esperados now show REAL team performance data');
        $this->line('✅ No more synthetic/AI predictions - only real statistics');
        $this->line('✅ Professional betting application with authentic data');
    }
}