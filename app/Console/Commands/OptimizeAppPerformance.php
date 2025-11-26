<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Artisan;

class OptimizeAppPerformance extends Command
{
    protected $signature = 'app:optimize-performance 
                            {--cache : Optimize caching}
                            {--db : Optimize database}
                            {--cleanup : Clean up old data}
                            {--all : Run all optimizations}';
    
    protected $description = 'Comprehensive app performance optimization';

    public function handle(): int
    {
        $this->info('⚡ OPTIMIZACIÓN DE PERFORMANCE DE LA APP');
        $this->newLine();

        $runAll = $this->option('all');
        $runCache = $this->option('cache') || $runAll;
        $runDb = $this->option('db') || $runAll;
        $runCleanup = $this->option('cleanup') || $runAll;

        // 1. Cache Optimization
        if ($runCache) {
            $this->optimizeCaching();
        }

        // 2. Database Optimization
        if ($runDb) {
            $this->optimizeDatabase();
        }

        // 3. Data Cleanup
        if ($runCleanup) {
            $this->cleanupOldData();
        }

        // 4. Performance Summary
        $this->showPerformanceSummary();

        return 0;
    }

    private function optimizeCaching(): void
    {
        $this->info('🚀 OPTIMIZACIÓN DE CACHE');
        $this->newLine();

        // Clear all caches first
        $this->line('🧹 Limpiando caches...');
        Artisan::call('cache:clear');
        Artisan::call('config:clear');
        Artisan::call('route:clear');
        Artisan::call('view:clear');

        // Optimize for production
        $this->line('⚙️ Optimizando para producción...');
        Artisan::call('config:cache');
        Artisan::call('route:cache');
        Artisan::call('view:cache');

        // Cache frequently accessed data
        $this->line('📊 Cacheando datos frecuentes...');
        $this->cacheFrequentData();

        $this->info('✅ Optimización de cache completada');
        $this->newLine();
    }

    private function cacheFrequentData(): void
    {
        // Cache team statistics for quick access
        $teams = DB::table('teams')
            ->join('team_statistics', 'teams.id', '=', 'team_statistics.team_id')
            ->where('teams.external_id', 'regexp', '^[0-9]+$')
            ->select('teams.*', 'team_statistics.avg_goals_for', 'team_statistics.avg_goals_against')
            ->limit(100)
            ->get();

        Cache::put('popular_teams_stats', $teams, now()->addHours(6));

        // Cache recent match predictions
        $recentPredictions = DB::table('match_predictions')
            ->join('matches', 'match_predictions.match_id', '=', 'matches.id')
            ->where('matches.match_date', '>=', now()->subDays(3))
            ->where('matches.match_date', '<=', now()->addDays(7))
            ->select('match_predictions.*')
            ->limit(200)
            ->get();

        Cache::put('recent_predictions', $recentPredictions, now()->addHours(2));

        $this->line('   ✅ Cached team stats and recent predictions');
    }

    private function optimizeDatabase(): void
    {
        $this->info('🗄️ OPTIMIZACIÓN DE BASE DE DATOS');
        $this->newLine();

        // Analyze table sizes
        $this->line('📊 Analizando tamaños de tablas...');
        $tableSizes = $this->getTableSizes();
        
        $this->table(
            ['Tabla', 'Registros', 'Tamaño (MB)', 'Estado'],
            $tableSizes
        );

        // Optimize tables
        $this->line('⚙️ Optimizando tablas...');
        $criticalTables = ['matches', 'match_predictions', 'team_statistics', 'teams'];
        
        foreach ($criticalTables as $table) {
            DB::statement("OPTIMIZE TABLE {$table}");
            $this->line("   ✅ Optimized {$table}");
        }

        // Check and create missing indexes
        $this->line('📈 Verificando índices...');
        $this->ensureCriticalIndexes();

        $this->info('✅ Optimización de base de datos completada');
        $this->newLine();
    }

    private function getTableSizes(): array
    {
        $tables = [
            'teams' => \App\Models\Team::count(),
            'matches' => \App\Models\FootballMatch::count(),
            'match_predictions' => \App\Models\MatchPrediction::count(),
            'team_statistics' => \App\Models\TeamStatistic::count(),
        ];

        $sizes = [];
        foreach ($tables as $table => $count) {
            $size = DB::select("
                SELECT 
                    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'size_mb'
                FROM information_schema.tables 
                WHERE table_schema = DATABASE() 
                AND table_name = '{$table}'
            ")[0]->size_mb ?? 0;

            $status = $count > 10000 ? '⚠️ Large' : '✅ OK';
            $sizes[] = [$table, number_format($count), $size, $status];
        }

        return $sizes;
    }

    private function ensureCriticalIndexes(): void
    {
        $indexes = [
            'matches' => [
                'idx_match_date' => 'match_date',
                'idx_teams' => ['home_team_id', 'away_team_id'],
                'idx_external_id' => 'external_id',
                'idx_status' => 'match_status',
            ],
            'match_predictions' => [
                'idx_match_id' => 'match_id',
                'idx_confidence' => 'confidence_score',
                'idx_model' => 'model_version',
            ],
            'teams' => [
                'idx_external_id' => 'external_id',
                'idx_league' => 'league',
            ],
            'team_statistics' => [
                'idx_team_season' => ['team_id', 'season'],
                'idx_updated' => 'last_updated',
            ]
        ];

        foreach ($indexes as $table => $tableIndexes) {
            foreach ($tableIndexes as $indexName => $columns) {
                try {
                    if (is_array($columns)) {
                        $columnList = implode(', ', $columns);
                        DB::statement("CREATE INDEX IF NOT EXISTS {$indexName} ON {$table} ({$columnList})");
                    } else {
                        DB::statement("CREATE INDEX IF NOT EXISTS {$indexName} ON {$table} ({$columns})");
                    }
                    $this->line("   ✅ Index {$indexName} on {$table}");
                } catch (\Exception $e) {
                    $this->line("   ⚠️ Index {$indexName} already exists or failed");
                }
            }
        }
    }

    private function cleanupOldData(): void
    {
        $this->info('🧹 LIMPIEZA DE DATOS ANTIGUOS');
        $this->newLine();

        $cleanupActions = [];

        // Clean old predictions (keep last 6 months)
        $oldPredictions = \App\Models\MatchPrediction::whereHas('match', function($query) {
            $query->where('match_date', '<', now()->subMonths(6));
        })->count();

        if ($oldPredictions > 0 && $this->confirm("Delete {$oldPredictions} old predictions (>6 months)?")) {
            $deleted = \App\Models\MatchPrediction::whereHas('match', function($query) {
                $query->where('match_date', '<', now()->subMonths(6));
            })->delete();
            $cleanupActions[] = "Deleted {$deleted} old predictions";
        }

        // Clean synthetic teams (keeping real ones)
        $syntheticTeams = \App\Models\Team::where('external_id', 'like', 'pred_%')->count();
        if ($syntheticTeams > 0 && $this->confirm("Clean up {$syntheticTeams} synthetic teams?")) {
            // Only delete if they have no recent matches
            $deleted = \App\Models\Team::where('external_id', 'like', 'pred_%')
                ->whereDoesntHave('homeMatches', function($query) {
                    $query->where('match_date', '>=', now()->subMonths(3));
                })
                ->whereDoesntHave('awayMatches', function($query) {
                    $query->where('match_date', '>=', now()->subMonths(3));
                })
                ->delete();
            $cleanupActions[] = "Deleted {$deleted} unused synthetic teams";
        }

        // Clear old cached data
        Cache::flush();
        $cleanupActions[] = "Cleared all cached data";

        // Clean logs older than 30 days
        $logFiles = glob(storage_path('logs/*.log'));
        $cleanedLogs = 0;
        foreach ($logFiles as $file) {
            if (filemtime($file) < time() - (30 * 24 * 60 * 60)) {
                unlink($file);
                $cleanedLogs++;
            }
        }
        if ($cleanedLogs > 0) {
            $cleanupActions[] = "Deleted {$cleanedLogs} old log files";
        }

        // Show cleanup results
        if (!empty($cleanupActions)) {
            $this->info('🎯 Limpieza completada:');
            foreach ($cleanupActions as $action) {
                $this->line("   ✅ {$action}");
            }
        } else {
            $this->line('✅ No cleanup needed - data is already optimized');
        }

        $this->newLine();
    }

    private function showPerformanceSummary(): void
    {
        $this->info('📊 RESUMEN DE PERFORMANCE');
        $this->newLine();

        // Database stats
        $dbStats = [
            'Teams' => \App\Models\Team::count(),
            'Matches' => \App\Models\FootballMatch::count(),
            'Predictions' => \App\Models\MatchPrediction::count(),
            'Team Statistics' => \App\Models\TeamStatistic::count(),
        ];

        // Cache status
        $cacheStatus = [
            'Config' => Cache::has('config_cached') ? '✅' : '❌',
            'Routes' => file_exists(base_path('bootstrap/cache/routes-v7.php')) ? '✅' : '❌',
            'Views' => !empty(glob(storage_path('framework/views/*'))) ? '✅' : '❌',
        ];

        $this->table(['Database', 'Records'], 
            array_map(fn($k, $v) => [$k, number_format($v)], array_keys($dbStats), $dbStats)
        );

        $this->table(['Cache Type', 'Status'],
            array_map(fn($k, $v) => [$k, $v], array_keys($cacheStatus), $cacheStatus)
        );

        // Performance recommendations
        $this->info('💡 RECOMENDACIONES:');
        $this->line('1. Configure Redis for better cache performance');
        $this->line('2. Set up queue workers for background processing');
        $this->line('3. Enable OPcache in PHP for better performance');
        $this->line('4. Run this optimization weekly: php artisan app:optimize-performance --all');

        $this->newLine();
        $this->info('🚀 App optimizada para máximo rendimiento!');
    }
}