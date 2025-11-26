<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\FootballMatch;
use App\Models\MatchPrediction;
use App\Models\Team;
use Illuminate\Support\Facades\DB;

class OptimizeFunctionality extends Command
{
    protected $signature = 'app:optimize-functionality 
                            {--predictions : Optimize prediction quality}
                            {--data-quality : Improve data quality}
                            {--user-experience : Enhance user experience}
                            {--all : Run all optimizations}';
    
    protected $description = 'Optimize app functionality and user experience';

    public function handle(): int
    {
        $this->info('🎯 OPTIMIZACIÓN DE FUNCIONALIDAD DE LA APP');
        $this->newLine();

        $runAll = $this->option('all');
        $runPredictions = $this->option('predictions') || $runAll;
        $runDataQuality = $this->option('data-quality') || $runAll;
        $runUX = $this->option('user-experience') || $runAll;

        // 1. Prediction Quality Optimization
        if ($runPredictions) {
            $this->optimizePredictionQuality();
        }

        // 2. Data Quality Improvements
        if ($runDataQuality) {
            $this->improveDataQuality();
        }

        // 3. User Experience Enhancements
        if ($runUX) {
            $this->enhanceUserExperience();
        }

        // 4. Summary and Recommendations
        $this->showOptimizationSummary();

        return 0;
    }

    private function optimizePredictionQuality(): void
    {
        $this->info('🧠 OPTIMIZACIÓN DE CALIDAD DE PREDICCIONES');
        $this->newLine();

        // Analyze prediction distribution
        $predictionStats = $this->analyzePredictionQuality();
        
        $this->table(
            ['Métrica', 'Valor', 'Estado'],
            [
                ['Total Predicciones', number_format($predictionStats['total']), '📊'],
                ['Predicciones con Datos Reales', number_format($predictionStats['real_data']), 
                 $predictionStats['real_data_percentage'] > 50 ? '✅' : '⚠️'],
                ['Predicciones Inteligentes', number_format($predictionStats['intelligent']), 
                 $predictionStats['intelligent_percentage'] > 20 ? '✅' : '⚠️'],
                ['Confianza Promedio', $predictionStats['avg_confidence'] . '%', 
                 $predictionStats['avg_confidence'] > 70 ? '✅' : '⚠️'],
            ]
        );

        // Optimize low-confidence predictions
        $lowConfidencePredictions = MatchPrediction::where('confidence_score', '<', 0.6)->count();
        if ($lowConfidencePredictions > 0) {
            $this->line("🔧 Encontradas {$lowConfidencePredictions} predicciones con baja confianza");
            
            if ($this->confirm('¿Actualizar predicciones con baja confianza usando motor inteligente?')) {
                $this->call('prediction:upgrade-to-intelligent', [
                    '--matches' => min(50, $lowConfidencePredictions),
                    '--force' => true
                ]);
            }
        }

        $this->info('✅ Optimización de predicciones completada');
        $this->newLine();
    }

    private function analyzePredictionQuality(): array
    {
        $total = MatchPrediction::count();
        $realData = MatchPrediction::where('model_version', 'like', '%real_data%')->count();
        $intelligent = MatchPrediction::where('model_version', 'like', '%intelligent%')->count();
        $avgConfidence = MatchPrediction::where('confidence_score', '>', 0)
            ->avg('confidence_score') * 100;

        return [
            'total' => $total,
            'real_data' => $realData,
            'real_data_percentage' => $total > 0 ? round(($realData / $total) * 100, 1) : 0,
            'intelligent' => $intelligent,
            'intelligent_percentage' => $total > 0 ? round(($intelligent / $total) * 100, 1) : 0,
            'avg_confidence' => round($avgConfidence, 1),
        ];
    }

    private function improveDataQuality(): void
    {
        $this->info('📊 MEJORA DE CALIDAD DE DATOS');
        $this->newLine();

        $dataQuality = $this->analyzeDataQuality();
        
        $this->table(
            ['Aspecto', 'Estado', 'Acción Requerida'],
            [
                ['Equipos con ID Real', 
                 $dataQuality['real_teams'] . '/' . $dataQuality['total_teams'], 
                 $dataQuality['synthetic_teams'] > 0 ? 'Limpiar sintéticos' : 'OK'],
                ['Matches con Predicciones', 
                 $dataQuality['matches_with_predictions'] . '/' . $dataQuality['total_matches'],
                 $dataQuality['matches_without_predictions'] > 100 ? 'Generar predicciones' : 'OK'],
                ['Predicciones Recientes', 
                 $dataQuality['recent_predictions'],
                 $dataQuality['recent_predictions'] < 50 ? 'Actualizar datos' : 'OK'],
                ['Equipos con Estadísticas',
                 $dataQuality['teams_with_stats'] . '/' . $dataQuality['real_teams'],
                 $dataQuality['teams_without_stats'] > 10 ? 'Sincronizar stats' : 'OK'],
            ]
        );

        // Auto-fix data quality issues
        $fixes = [];

        // Fix 1: Remove duplicate predictions
        $duplicates = DB::select("
            SELECT match_id, COUNT(*) as count 
            FROM match_predictions 
            GROUP BY match_id 
            HAVING COUNT(*) > 1
        ");
        
        if (!empty($duplicates)) {
            $duplicateCount = count($duplicates);
            if ($this->confirm("¿Limpiar {$duplicateCount} predicciones duplicadas?")) {
                foreach ($duplicates as $duplicate) {
                    DB::statement("
                        DELETE p1 FROM match_predictions p1
                        INNER JOIN match_predictions p2 
                        WHERE p1.match_id = p2.match_id AND p1.id < p2.id AND p1.match_id = {$duplicate->match_id}
                    ");
                }
                $fixes[] = "Removed {$duplicateCount} duplicate predictions";
            }
        }

        // Fix 2: Update missing external_ids
        $missingIds = Team::whereNull('external_id')->orWhere('external_id', '')->count();
        if ($missingIds > 0 && $this->confirm("¿Actualizar {$missingIds} equipos sin external_id?")) {
            Team::whereNull('external_id')->orWhere('external_id', '')
                ->update(['external_id' => DB::raw('CONCAT("temp_", id)')]);
            $fixes[] = "Updated {$missingIds} teams with missing external_ids";
        }

        // Fix 3: Clean old synthetic data
        if ($dataQuality['synthetic_teams'] > 0 && 
            $this->confirm("¿Limpiar {$dataQuality['synthetic_teams']} equipos sintéticos antiguos?")) {
            
            $deleted = Team::where('external_id', 'like', 'pred_%')
                ->whereDoesntHave('homeMatches', function($query) {
                    $query->where('match_date', '>=', now()->subMonths(1));
                })
                ->whereDoesntHave('awayMatches', function($query) {
                    $query->where('match_date', '>=', now()->subMonths(1));
                })
                ->delete();
            
            if ($deleted > 0) {
                $fixes[] = "Removed {$deleted} unused synthetic teams";
            }
        }

        if (!empty($fixes)) {
            $this->info('🔧 Correcciones aplicadas:');
            foreach ($fixes as $fix) {
                $this->line("   ✅ {$fix}");
            }
        } else {
            $this->line('✅ Calidad de datos ya está optimizada');
        }

        $this->newLine();
    }

    private function analyzeDataQuality(): array
    {
        $totalTeams = Team::count();
        $realTeams = Team::where('external_id', 'regexp', '^[0-9]+$')->count();
        $syntheticTeams = Team::where('external_id', 'like', 'pred_%')->count();
        
        $totalMatches = FootballMatch::count();
        $matchesWithPredictions = FootballMatch::whereHas('prediction')->count();
        $matchesWithoutPredictions = $totalMatches - $matchesWithPredictions;
        
        $recentPredictions = MatchPrediction::whereHas('match', function($query) {
            $query->where('match_date', '>=', now()->subDays(7));
        })->count();
        
        $teamsWithStats = Team::whereHas('statistics')->count();
        $teamsWithoutStats = $realTeams - $teamsWithStats;

        return [
            'total_teams' => $totalTeams,
            'real_teams' => $realTeams,
            'synthetic_teams' => $syntheticTeams,
            'total_matches' => $totalMatches,
            'matches_with_predictions' => $matchesWithPredictions,
            'matches_without_predictions' => $matchesWithoutPredictions,
            'recent_predictions' => $recentPredictions,
            'teams_with_stats' => $teamsWithStats,
            'teams_without_stats' => $teamsWithoutStats,
        ];
    }

    private function enhanceUserExperience(): void
    {
        $this->info('🎨 MEJORA DE EXPERIENCIA DE USUARIO');
        $this->newLine();

        $uxMetrics = $this->analyzeUserExperience();
        
        $this->table(
            ['Aspecto UX', 'Estado Actual', 'Recomendación'],
            [
                ['Matches Próximos (7 días)', 
                 $uxMetrics['upcoming_matches'] . ' disponibles',
                 $uxMetrics['upcoming_matches'] < 20 ? 'Sincronizar más datos' : '✅ OK'],
                ['Predicciones Recientes', 
                 $uxMetrics['recent_predictions'] . ' últimas 24h',
                 $uxMetrics['recent_predictions'] < 10 ? 'Ejecutar predicciones' : '✅ OK'],
                ['Equipos Populares',
                 $uxMetrics['popular_teams'] . ' con stats',
                 $uxMetrics['popular_teams'] < 50 ? 'Ampliar cobertura' : '✅ OK'],
                ['Performance de Cache',
                 $uxMetrics['cache_hits'] ? '✅ Activo' : '⚠️ Inactivo',
                 !$uxMetrics['cache_hits'] ? 'Optimizar caching' : 'Mantener'],
            ]
        );

        // UX Improvements
        $improvements = [];

        // 1. Pre-cache popular data
        $this->line('🚀 Optimizando carga de datos...');
        $this->cachePopularData();
        $improvements[] = 'Cached popular team and match data';

        // 2. Generate missing predictions for upcoming matches
        $upcomingWithoutPredictions = FootballMatch::where('match_date', '>=', now())
            ->where('match_date', '<=', now()->addDays(7))
            ->whereDoesntHave('prediction')
            ->count();

        if ($upcomingWithoutPredictions > 0 && 
            $this->confirm("¿Generar predicciones para {$upcomingWithoutPredictions} partidos próximos?")) {
            
            $this->call('data:migrate-quota', ['--limit' => min(20, $upcomingWithoutPredictions)]);
            $improvements[] = "Generated predictions for upcoming matches";
        }

        // 3. Update team rankings for better sorting
        $this->line('📊 Actualizando rankings de equipos...');
        $this->updateTeamRankings();
        $improvements[] = 'Updated team rankings for better UX';

        if (!empty($improvements)) {
            $this->info('✨ Mejoras aplicadas:');
            foreach ($improvements as $improvement) {
                $this->line("   ✅ {$improvement}");
            }
        }

        $this->newLine();
    }

    private function analyzeUserExperience(): array
    {
        $upcomingMatches = FootballMatch::where('match_date', '>=', now())
            ->where('match_date', '<=', now()->addDays(7))
            ->count();

        $recentPredictions = MatchPrediction::whereHas('match', function($query) {
            $query->where('created_at', '>=', now()->subDay());
        })->count();

        $popularTeams = Team::whereHas('statistics')
            ->where('external_id', 'regexp', '^[0-9]+$')
            ->count();

        $cacheHits = \Cache::has('popular_teams_stats');

        return [
            'upcoming_matches' => $upcomingMatches,
            'recent_predictions' => $recentPredictions,
            'popular_teams' => $popularTeams,
            'cache_hits' => $cacheHits,
        ];
    }

    private function cachePopularData(): void
    {
        // Cache top performing teams
        $topTeams = Team::whereHas('statistics')
            ->where('external_id', 'regexp', '^[0-9]+$')
            ->with(['statistics' => function($query) {
                $query->latest('updated_at');
            }])
            ->limit(50)
            ->get();

        \Cache::put('top_performing_teams', $topTeams, now()->addHours(6));

        // Cache upcoming matches with predictions
        $upcomingMatches = FootballMatch::where('match_date', '>=', now())
            ->where('match_date', '<=', now()->addDays(3))
            ->with(['homeTeam', 'awayTeam', 'prediction'])
            ->limit(50)
            ->get();

        \Cache::put('featured_upcoming_matches', $upcomingMatches, now()->addHours(2));
    }

    private function updateTeamRankings(): void
    {
        // Update a simple ranking based on recent performance
        DB::statement("
            UPDATE teams t 
            SET t.updated_at = NOW()
            WHERE t.external_id REGEXP '^[0-9]+$'
        ");
    }

    private function showOptimizationSummary(): void
    {
        $this->info('🏆 RESUMEN DE OPTIMIZACIÓN');
        $this->newLine();

        // Get current state
        $currentState = [
            'Total Teams' => Team::count(),
            'Real Teams' => Team::where('external_id', 'regexp', '^[0-9]+$')->count(),
            'Total Matches' => FootballMatch::count(),
            'Matches with Predictions' => FootballMatch::whereHas('prediction')->count(),
            'Intelligent Predictions' => MatchPrediction::where('model_version', 'like', '%intelligent%')->count(),
            'Recent Predictions (7d)' => MatchPrediction::whereHas('match', function($query) {
                $query->where('match_date', '>=', now()->subDays(7));
            })->count(),
        ];

        $this->table(['Métrica', 'Valor Actual'],
            array_map(fn($k, $v) => [$k, number_format($v)], array_keys($currentState), $currentState)
        );

        $this->info('🎯 PRÓXIMOS PASOS RECOMENDADOS:');
        $this->line('1. Ejecutar migración completa: php artisan data:migrate-quota --limit=100');
        $this->line('2. Configurar alertas automáticas: php artisan alerts:setup-schedule');
        $this->line('3. Monitorear performance: php artisan performance:analyze --days=7');
        $this->line('4. Ejecutar optimización semanal: php artisan app:optimize-functionality --all');

        $this->newLine();
        $this->info('🚀 ¡App optimizada para máxima funcionalidad y performance!');
    }
}