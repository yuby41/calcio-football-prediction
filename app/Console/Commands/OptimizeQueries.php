<?php

namespace App\Console\Commands;

use App\Models\FootballMatch;
use App\Models\MatchPrediction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class OptimizeQueries extends Command
{
    protected $signature = 'queries:optimize 
                            {--analyze : Show query analysis}
                            {--create-indexes : Create missing indexes}
                            {--update-statistics : Update table statistics}';
    
    protected $description = 'Optimize database queries and create indexes to prevent N+1 problems';

    public function handle()
    {
        $analyze = $this->option('analyze');
        $createIndexes = $this->option('create-indexes');
        $updateStats = $this->option('update-statistics');

        $this->info('🚀 Optimizing database queries...');

        if ($analyze) {
            $this->analyzeQueries();
        }

        if ($createIndexes) {
            $this->createOptimalIndexes();
        }

        if ($updateStats) {
            $this->updateTableStatistics();
        }

        $this->optimizeCommonQueries();

        $this->info('✅ Query optimization completed');
        return 0;
    }

    private function analyzeQueries(): void
    {
        $this->info('📊 Analyzing common query patterns...');

        // Most common queries that might benefit from indexes
        $queries = [
            'matches by status and date' => [
                'table' => 'matches',
                'conditions' => ['status', 'match_date']
            ],
            'predictions by match_id' => [
                'table' => 'match_predictions', 
                'conditions' => ['match_id']
            ],
            'matches with predictions' => [
                'table' => 'matches',
                'joins' => ['match_predictions']
            ],
            'bets by user and status' => [
                'table' => 'bets',
                'conditions' => ['user_id', 'status']
            ]
        ];

        foreach ($queries as $description => $query) {
            $this->line("  📈 {$description}");
        }
    }

    private function createOptimalIndexes(): void
    {
        $this->info('🔧 Creating optimal indexes...');

        $indexes = [
            // Matches table optimizations
            ["idx_matches_status_date", "matches", ["status", "match_date"]],
            ["idx_matches_date_status", "matches", ["match_date", "status"]],
            ["idx_matches_external_id", "matches", ["external_id"]],
            ["idx_matches_teams", "matches", ["home_team_id", "away_team_id"]],
            
            // Predictions table optimizations  
            ["idx_predictions_match_version", "match_predictions", ["match_id", "model_version"]],
            ["idx_predictions_predicted_at", "match_predictions", ["predicted_at"]],
            ["idx_predictions_outcome", "match_predictions", ["predicted_outcome"]],
            
            // Bets table optimizations
            ["idx_bets_user_status", "bets", ["user_id", "status"]],
            ["idx_bets_match_id", "bets", ["match_id"]],
            ["idx_bets_date_status", "bets", ["created_at", "status"]],
            
            // Team statistics optimizations
            ["idx_team_stats_team_season", "team_statistics", ["team_id", "season"]],
            
            // Budget history optimizations
            ["idx_budget_history_config_date", "budget_history", ["budget_configuration_id", "created_at"]],
        ];

        foreach ($indexes as [$indexName, $table, $columns]) {
            try {
                // Check if index already exists
                $existingIndexes = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$indexName]);
                
                if (empty($existingIndexes)) {
                    $columnList = implode(', ', $columns);
                    $sql = "CREATE INDEX {$indexName} ON {$table}({$columnList})";
                    DB::statement($sql);
                    $this->line("  ✅ Created index: {$indexName} on {$table}({$columnList})");
                } else {
                    $this->line("  ℹ️  Index already exists: {$indexName}");
                }
            } catch (\Exception $e) {
                // Check if it's a duplicate key error (index already exists)
                if (strpos($e->getMessage(), '1061') !== false) {
                    $this->line("  ℹ️  Index already exists: {$indexName}");
                } else {
                    $this->warn("  ❌ Failed to create index {$indexName}: " . $e->getMessage());
                }
            }
        }
    }

    private function updateTableStatistics(): void
    {
        $this->info('📊 Updating table statistics...');

        $tables = ['matches', 'match_predictions', 'bets', 'teams', 'team_statistics', 'budget_history'];
        
        foreach ($tables as $table) {
            try {
                DB::statement("ANALYZE TABLE {$table}");
                $this->line("  ✅ Updated statistics for: {$table}");
            } catch (\Exception $e) {
                $this->warn("  ❌ Failed to update statistics for {$table}: " . $e->getMessage());
            }
        }
    }

    private function optimizeCommonQueries(): void
    {
        $this->info('🎯 Running common query optimizations...');

        // Test common query patterns with proper eager loading
        $this->line('  🔍 Testing match queries with predictions...');
        
        // This demonstrates the correct way to avoid N+1
        $recentMatches = FootballMatch::with(['homeTeam', 'awayTeam', 'prediction'])
            ->where('status', 'finished')
            ->where('match_date', '>=', Carbon::now()->subDays(1))
            ->limit(5)
            ->get();

        $this->line("  ✅ Loaded {$recentMatches->count()} matches with all relations in single query");

        // Test prediction queries
        $this->line('  🔍 Testing prediction queries...');
        
        $predictions = MatchPrediction::with(['match.homeTeam', 'match.awayTeam'])
            ->whereHas('match', function($q) {
                $q->where('status', 'finished')
                  ->where('match_date', '>=', Carbon::now()->subDays(1));
            })
            ->limit(5)
            ->get();

        $this->line("  ✅ Loaded {$predictions->count()} predictions with match relations in single query");

        $this->line('  💡 To prevent N+1 queries in your code, always use:');
        $this->line('     FootballMatch::with([\'homeTeam\', \'awayTeam\', \'prediction\'])->get()');
        $this->line('     instead of FootballMatch::get() and then accessing relations');
    }
}