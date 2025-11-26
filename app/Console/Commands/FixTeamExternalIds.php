<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\EnhancedFootballApiService;
use App\Models\Team;
use Illuminate\Support\Facades\Log;

class FixTeamExternalIds extends Command
{
    protected $signature = 'data:fix-team-ids {--dry-run : Show what would be fixed without making changes}';
    protected $description = 'Fix synthetic team external_ids with real API-Sports team IDs';

    private EnhancedFootballApiService $apiService;

    public function __construct(EnhancedFootballApiService $apiService)
    {
        parent::__construct();
        $this->apiService = $apiService;
    }

    public function handle(): int
    {
        $this->info('🔍 Analyzing team external_ids for synthetic data...');
        
        $dryRun = $this->option('dry-run');
        
        // Find teams with synthetic/fake external_ids
        $syntheticTeams = Team::where('external_id', 'like', 'pred_%')
            ->orWhere('external_id', 'like', 'team_%')
            ->orWhere('external_id', 'like', 'synthetic_%')
            ->orWhereNull('external_id')
            ->get();

        $this->info("Found {$syntheticTeams->count()} teams with synthetic external_ids");

        if ($syntheticTeams->count() === 0) {
            $this->info('✅ All teams have real external_ids');
            return 0;
        }

        $this->warn("\n🚨 PROBLEM IDENTIFIED:");
        $this->warn("Teams have SYNTHETIC external_ids instead of real API-Sports team IDs");
        $this->warn("This is why 'real' data looks wrong - we're not getting actual team statistics!");

        $this->info("\n📋 Synthetic Teams Found:");
        
        foreach ($syntheticTeams as $team) {
            $this->line("❌ {$team->name}: {$team->external_id}");
        }

        if ($dryRun) {
            $this->info("\n🧪 DRY RUN - Here's what we would do:");
            $this->line("1. Search API-Sports for each team by name");
            $this->line("2. Get the REAL external_id from API-Sports");
            $this->line("3. Update our database with correct IDs");
            $this->line("4. Re-run migration with correct team IDs");
            return 0;
        }

        if (!$this->confirm('Fix these team external_ids with real API-Sports IDs?')) {
            $this->info('Operation cancelled');
            return 0;
        }

        $this->info("\n🔄 Fixing team external_ids...");
        
        $fixed = 0;
        $failed = 0;
        
        foreach ($syntheticTeams->take(10) as $team) { // Limit to 10 to conserve API quota
            try {
                $this->info("🔍 Searching for: {$team->name}");
                
                // Search for team in major leagues
                $realTeamId = $this->findRealTeamId($team->name);
                
                if ($realTeamId) {
                    $team->update(['external_id' => $realTeamId]);
                    $this->info("✅ Fixed: {$team->name} → ID: {$realTeamId}");
                    $fixed++;
                } else {
                    $this->warn("❌ Could not find real ID for: {$team->name}");
                    $failed++;
                }
                
                sleep(1); // Rate limiting
                
            } catch (\Exception $e) {
                $this->error("❌ Error fixing {$team->name}: " . $e->getMessage());
                $failed++;
            }
        }

        $this->info("\n📋 Results:");
        $this->table(
            ['Metric', 'Count'],
            [
                ['Teams Fixed', $fixed],
                ['Teams Failed', $failed],
                ['Remaining Synthetic', $syntheticTeams->count() - $fixed],
            ]
        );

        if ($fixed > 0) {
            $this->info("🎉 Successfully fixed {$fixed} team external_ids!");
            $this->info("💡 Now you can re-run the migration to get REAL team statistics");
            $this->info("Command: php artisan data:migrate-quota --limit=5 --max-requests=50");
        }

        return 0;
    }

    private function findRealTeamId(string $teamName): ?string
    {
        // Major league IDs to search in
        $leagueIds = [
            39,  // Premier League
            140, // La Liga  
            78,  // Bundesliga
            135, // Serie A
            61,  // Ligue 1
        ];

        foreach ($leagueIds as $leagueId) {
            try {
                $teams = $this->apiService->fetchTeams('PL', date('Y')); // Fetch from Premier League first
                
                foreach ($teams as $apiTeam) {
                    if ($this->isTeamMatch($teamName, $apiTeam['name'])) {
                        return $apiTeam['external_id'];
                    }
                }
                
                sleep(1); // Rate limiting between league searches
                
            } catch (\Exception $e) {
                Log::error("Error searching league {$leagueId}: " . $e->getMessage());
                continue;
            }
        }

        return null;
    }

    private function isTeamMatch(string $dbTeamName, string $apiTeamName): bool
    {
        // Normalize names for comparison
        $dbName = strtolower(trim($dbTeamName));
        $apiName = strtolower(trim($apiTeamName));
        
        // Exact match
        if ($dbName === $apiName) {
            return true;
        }
        
        // Partial match (contains)
        if (strpos($apiName, $dbName) !== false || strpos($dbName, $apiName) !== false) {
            return true;
        }
        
        // Common team name variations
        $variations = [
            'man city' => 'manchester city',
            'man united' => 'manchester united',
            'tottenham' => 'tottenham hotspur',
            'wolves' => 'wolverhampton wanderers',
            'brighton' => 'brighton & hove albion',
            'newcastle' => 'newcastle united',
            'west ham' => 'west ham united',
        ];
        
        foreach ($variations as $short => $full) {
            if (($dbName === $short && $apiName === $full) || 
                ($dbName === $full && $apiName === $short)) {
                return true;
            }
        }
        
        return false;
    }
}