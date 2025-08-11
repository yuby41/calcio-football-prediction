<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\FootballMatch;

class DetectNPlusOne extends Command
{
    protected $signature = 'queries:detect-n-plus-1 
                            {--limit=100 : Number of matches to analyze}
                            {--show-queries : Show actual SQL queries}';
    
    protected $description = 'Detect and analyze N+1 query problems in the application';

    public function handle()
    {
        $limit = (int) $this->option('limit');
        $showQueries = $this->option('show-queries');

        $this->info('🔍 Detecting N+1 query patterns...');

        // Enable query logging
        DB::enableQueryLog();

        $this->testScenario1($limit, $showQueries);
        $this->testScenario2($limit, $showQueries);
        $this->testScenario3($limit, $showQueries);

        $this->info('✅ N+1 detection completed');
        return 0;
    }

    private function testScenario1($limit, $showQueries): void
    {
        $this->line('');
        $this->info('📊 Scenario 1: Loading matches WITHOUT eager loading (BAD)');
        
        DB::flushQueryLog();
        
        $matches = FootballMatch::limit($limit)->get();
        
        // This will cause N+1 queries
        $teamNames = [];
        foreach ($matches as $match) {
            $teamNames[] = $match->homeTeam->name . ' vs ' . $match->awayTeam->name;
        }
        
        $queries = DB::getQueryLog();
        $queryCount = count($queries);
        
        $this->warn("  ❌ Executed {$queryCount} queries (1 + {$limit} * 2 for teams)");
        
        if ($showQueries && $queryCount <= 20) {
            foreach ($queries as $query) {
                $this->line("    SQL: " . substr($query['query'], 0, 100) . '...');
            }
        }
    }

    private function testScenario2($limit, $showQueries): void
    {
        $this->line('');
        $this->info('📊 Scenario 2: Loading matches WITH eager loading (GOOD)');
        
        DB::flushQueryLog();
        
        $matches = FootballMatch::with(['homeTeam', 'awayTeam'])
            ->limit($limit)
            ->get();
        
        $teamNames = [];
        foreach ($matches as $match) {
            $teamNames[] = $match->homeTeam->name . ' vs ' . $match->awayTeam->name;
        }
        
        $queries = DB::getQueryLog();
        $queryCount = count($queries);
        
        $this->info("  ✅ Executed only {$queryCount} queries (1 for matches + 2 for teams)");
        
        if ($showQueries) {
            foreach ($queries as $query) {
                $this->line("    SQL: " . substr($query['query'], 0, 100) . '...');
            }
        }
    }

    private function testScenario3($limit, $showQueries): void
    {
        $this->line('');
        $this->info('📊 Scenario 3: Loading matches with predictions WITH eager loading (OPTIMAL)');
        
        DB::flushQueryLog();
        
        $matches = FootballMatch::with(['homeTeam', 'awayTeam', 'prediction'])
            ->whereHas('prediction')
            ->limit($limit)
            ->get();
        
        $data = [];
        foreach ($matches as $match) {
            $data[] = [
                'teams' => $match->homeTeam->name . ' vs ' . $match->awayTeam->name,
                'prediction' => $match->prediction?->predicted_outcome ?? 'N/A',
                'confidence' => $match->prediction?->confidence_score ?? 0
            ];
        }
        
        $queries = DB::getQueryLog();
        $queryCount = count($queries);
        
        $this->info("  ✅ Executed only {$queryCount} queries for {$matches->count()} complete match records");
        
        if ($showQueries) {
            foreach ($queries as $query) {
                $this->line("    SQL: " . substr($query['query'], 0, 100) . '...');
            }
        }

        $this->line('');
        $this->info('💡 Performance Tips:');
        $this->line('  1. Always use ->with([relations]) when you know you\'ll need them');
        $this->line('  2. Use ->load([relations]) if you need to eager load after initial query');
        $this->line('  3. Consider using ->select() to limit columns if you don\'t need all data');
        $this->line('  4. Use pagination for large datasets instead of ->get()');
        $this->line('  5. Database indexes have been optimized for common query patterns');
    }
}