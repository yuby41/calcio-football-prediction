<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\FootballMatch;
use App\Models\MatchPrediction;
use App\Models\Team;
use App\Models\TeamStatistic;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class AppHealthCheck extends Command
{
    protected $signature = 'app:health-check 
                            {--detailed : Show detailed analysis}
                            {--fix : Auto-fix issues found}';
    
    protected $description = 'Comprehensive health check of the football betting app';

    public function handle(): int
    {
        $this->info('🏥 HEALTH CHECK DE LA APLICACIÓN DE APUESTAS');
        $this->newLine();

        $detailed = $this->option('detailed');
        $autoFix = $this->option('fix');

        $healthData = $this->gatherHealthData();
        $issues = $this->identifyIssues($healthData);

        // 1. System Overview
        $this->showSystemOverview($healthData);

        // 2. Data Quality Assessment
        $this->showDataQuality($healthData);

        // 3. Performance Metrics
        $this->showPerformanceMetrics($healthData);

        // 4. Feature Status
        $this->showFeatureStatus($healthData);

        // 5. Issues and Recommendations
        $this->showIssuesAndRecommendations($issues, $autoFix);

        // 6. Overall Health Score
        $overallScore = $this->calculateHealthScore($healthData, $issues);
        $this->showHealthScore($overallScore);

        return count($issues['critical']) > 0 ? 1 : 0;
    }

    private function gatherHealthData(): array
    {
        return [
            'database' => [
                'total_teams' => Team::count(),
                'real_teams' => Team::where('external_id', 'regexp', '^[0-9]+$')->count(),
                'synthetic_teams' => Team::where('external_id', 'like', 'pred_%')->count(),
                'total_matches' => FootballMatch::count(),
                'matches_with_predictions' => FootballMatch::whereHas('prediction')->count(),
                'recent_matches' => FootballMatch::where('match_date', '>=', now()->subDays(7))->count(),
                'upcoming_matches' => FootballMatch::where('match_date', '>=', now())->where('match_date', '<=', now()->addDays(7))->count(),
                'total_predictions' => MatchPrediction::count(),
                'intelligent_predictions' => MatchPrediction::where('model_version', 'like', '%intelligent%')->count(),
                'real_data_predictions' => MatchPrediction::where('model_version', 'like', '%real_data%')->count(),
                'high_confidence_predictions' => MatchPrediction::where('confidence_score', '>', 0.8)->count(),
                'teams_with_stats' => Team::whereHas('statistics')->count(),
            ],
            'performance' => [
                'avg_prediction_confidence' => MatchPrediction::where('confidence_score', '>', 0)->avg('confidence_score') * 100,
                'cache_status' => Cache::has('popular_teams_stats'),
                'db_size_mb' => $this->getDatabaseSize(),
                'recent_predictions_24h' => MatchPrediction::where('created_at', '>=', now()->subDay())->count(),
            ],
            'features' => [
                'migration_system' => class_exists(\App\Console\Commands\MigrateSyntheticToRealData::class),
                'intelligent_engine' => class_exists(\App\Services\IntelligentPredictionEngine::class),
                'alert_system' => class_exists(\App\Services\IntelligentAlertSystem::class),
                'performance_analyzer' => class_exists(\App\Services\AdvancedPerformanceAnalyzer::class),
                'multi_source_stats' => class_exists(\App\Services\MultiSourceStatsService::class),
            ],
        ];
    }

    private function getDatabaseSize(): float
    {
        $size = DB::select("
            SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb
            FROM information_schema.tables 
            WHERE table_schema = DATABASE()
        ");
        
        return $size[0]->size_mb ?? 0;
    }

    private function identifyIssues(array $data): array
    {
        $issues = [
            'critical' => [],
            'warning' => [],
            'info' => [],
        ];

        $db = $data['database'];
        $perf = $data['performance'];

        // Critical Issues
        if ($db['matches_with_predictions'] / max($db['total_matches'], 1) < 0.8) {
            $issues['critical'][] = 'Menos del 80% de matches tienen predicciones';
        }
        
        if ($db['real_teams'] / max($db['total_teams'], 1) < 0.9) {
            $issues['critical'][] = 'Más del 10% de equipos son sintéticos';
        }

        if ($perf['avg_prediction_confidence'] < 50) {
            $issues['critical'][] = 'Confianza promedio de predicciones muy baja (<50%)';
        }

        // Warning Issues
        if ($db['intelligent_predictions'] / max($db['total_predictions'], 1) < 0.1) {
            $issues['warning'][] = 'Pocas predicciones usan motor inteligente (<10%)';
        }

        if ($db['upcoming_matches'] < 20) {
            $issues['warning'][] = 'Pocos matches próximos disponibles (<20)';
        }

        if (!$perf['cache_status']) {
            $issues['warning'][] = 'Sistema de cache no está activo';
        }

        if ($perf['recent_predictions_24h'] < 5) {
            $issues['warning'][] = 'Pocas predicciones generadas en últimas 24h';
        }

        // Info Issues
        if ($db['synthetic_teams'] > 0) {
            $issues['info'][] = $db['synthetic_teams'] . ' equipos sintéticos pueden ser limpiados';
        }

        if ($perf['db_size_mb'] > 100) {
            $issues['info'][] = 'Base de datos grande (' . $perf['db_size_mb'] . 'MB) - considera optimización';
        }

        return $issues;
    }

    private function showSystemOverview(array $data): void
    {
        $this->info('📊 RESUMEN DEL SISTEMA');
        
        $db = $data['database'];
        $coverage = round(($db['matches_with_predictions'] / max($db['total_matches'], 1)) * 100, 1);
        $realTeamPercentage = round(($db['real_teams'] / max($db['total_teams'], 1)) * 100, 1);
        
        $this->table(
            ['Métrica', 'Valor', 'Estado'],
            [
                ['Total de Equipos', number_format($db['total_teams']), '📈'],
                ['Equipos Reales', number_format($db['real_teams']) . " ({$realTeamPercentage}%)", 
                 $realTeamPercentage > 90 ? '✅' : '⚠️'],
                ['Total de Matches', number_format($db['total_matches']), '⚽'],
                ['Cobertura de Predicciones', "{$coverage}%", $coverage > 90 ? '✅' : '⚠️'],
                ['Matches Próximos (7 días)', $db['upcoming_matches'], $db['upcoming_matches'] > 20 ? '✅' : '⚠️'],
                ['Predicciones Inteligentes', number_format($db['intelligent_predictions']), 
                 $db['intelligent_predictions'] > 50 ? '✅' : '⚠️'],
            ]
        );
        $this->newLine();
    }

    private function showDataQuality(array $data): void
    {
        $this->info('🎯 CALIDAD DE DATOS');
        
        $db = $data['database'];
        $perf = $data['performance'];
        
        $intelligentPercentage = round(($db['intelligent_predictions'] / max($db['total_predictions'], 1)) * 100, 1);
        $realDataPercentage = round(($db['real_data_predictions'] / max($db['total_predictions'], 1)) * 100, 1);
        $highConfidencePercentage = round(($db['high_confidence_predictions'] / max($db['total_predictions'], 1)) * 100, 1);
        
        $this->table(
            ['Aspecto', 'Valor', 'Objetivo', 'Estado'],
            [
                ['Confianza Promedio', round($perf['avg_prediction_confidence'], 1) . '%', '>70%',
                 $perf['avg_prediction_confidence'] > 70 ? '✅' : '⚠️'],
                ['Predicciones Inteligentes', "{$intelligentPercentage}%", '>20%',
                 $intelligentPercentage > 20 ? '✅' : '⚠️'],
                ['Predicciones con Datos Reales', "{$realDataPercentage}%", '>50%',
                 $realDataPercentage > 50 ? '✅' : '⚠️'],
                ['Alta Confianza (>80%)', "{$highConfidencePercentage}%", '>30%',
                 $highConfidencePercentage > 30 ? '✅' : '⚠️'],
                ['Equipos con Estadísticas', number_format($db['teams_with_stats']), 'Máximo',
                 $db['teams_with_stats'] > $db['real_teams'] * 0.9 ? '✅' : '⚠️'],
            ]
        );
        $this->newLine();
    }

    private function showPerformanceMetrics(array $data): void
    {
        $this->info('⚡ MÉTRICAS DE PERFORMANCE');
        
        $perf = $data['performance'];
        $db = $data['database'];
        
        $this->table(
            ['Métrica', 'Valor', 'Estado'],
            [
                ['Tamaño de Base de Datos', $perf['db_size_mb'] . ' MB', 
                 $perf['db_size_mb'] < 100 ? '✅' : '⚠️'],
                ['Cache Activo', $perf['cache_status'] ? '✅ Sí' : '❌ No', 
                 $perf['cache_status'] ? '✅' : '⚠️'],
                ['Predicciones Últimas 24h', $perf['recent_predictions_24h'],
                 $perf['recent_predictions_24h'] > 10 ? '✅' : '⚠️'],
                ['Matches Recientes (7d)', $db['recent_matches'], '📊'],
                ['Equipos Sintéticos', $db['synthetic_teams'],
                 $db['synthetic_teams'] < 100 ? '✅' : '⚠️'],
            ]
        );
        $this->newLine();
    }

    private function showFeatureStatus(array $data): void
    {
        $this->info('🚀 ESTADO DE FUNCIONALIDADES');
        
        $features = $data['features'];
        
        $this->table(
            ['Funcionalidad', 'Estado', 'Descripción'],
            [
                ['Sistema de Migración', $features['migration_system'] ? '✅' : '❌', 'Migración sintético → real'],
                ['Motor Inteligente', $features['intelligent_engine'] ? '✅' : '❌', 'Predicciones avanzadas'],
                ['Sistema de Alertas', $features['alert_system'] ? '✅' : '❌', 'Alertas de valor'],
                ['Análisis de Performance', $features['performance_analyzer'] ? '✅' : '❌', 'Métricas ROI/precisión'],
                ['Multi-Source Stats', $features['multi_source_stats'] ? '✅' : '❌', 'Datos de múltiples APIs'],
            ]
        );
        $this->newLine();
    }

    private function showIssuesAndRecommendations(array $issues, bool $autoFix): void
    {
        $this->info('🔧 PROBLEMAS Y RECOMENDACIONES');
        
        if (!empty($issues['critical'])) {
            $this->error('❌ CRÍTICOS:');
            foreach ($issues['critical'] as $issue) {
                $this->line("   • {$issue}");
            }
            $this->newLine();
        }
        
        if (!empty($issues['warning'])) {
            $this->warn('⚠️ ADVERTENCIAS:');
            foreach ($issues['warning'] as $issue) {
                $this->line("   • {$issue}");
            }
            $this->newLine();
        }
        
        if (!empty($issues['info'])) {
            $this->info('ℹ️ INFORMACIÓN:');
            foreach ($issues['info'] as $issue) {
                $this->line("   • {$issue}");
            }
            $this->newLine();
        }

        // Auto-fix suggestions
        if ($autoFix && (!empty($issues['critical']) || !empty($issues['warning']))) {
            $this->info('🛠️ SOLUCIONES AUTOMÁTICAS:');
            $this->line('1. php artisan data:migrate-quota --limit=100  # Migrar más datos reales');
            $this->line('2. php artisan prediction:upgrade-to-intelligent --matches=50  # Más predicciones inteligentes');
            $this->line('3. php artisan app:optimize-performance --all  # Optimizar performance');
            $this->line('4. php artisan app:optimize-functionality --all  # Mejorar funcionalidad');
            $this->newLine();
        }
    }

    private function calculateHealthScore(array $data, array $issues): int
    {
        $score = 100;
        
        // Penalize for issues
        $score -= count($issues['critical']) * 20;
        $score -= count($issues['warning']) * 10;
        $score -= count($issues['info']) * 5;
        
        // Bonus for good metrics
        $db = $data['database'];
        $perf = $data['performance'];
        
        if ($db['matches_with_predictions'] / max($db['total_matches'], 1) > 0.95) $score += 5;
        if ($perf['avg_prediction_confidence'] > 75) $score += 5;
        if ($db['intelligent_predictions'] > 100) $score += 5;
        if ($perf['cache_status']) $score += 5;
        
        return max(0, min(100, $score));
    }

    private function showHealthScore(int $score): void
    {
        $this->newLine();
        
        if ($score >= 90) {
            $this->info("🏆 PUNTUACIÓN DE SALUD: {$score}/100 - EXCELENTE");
            $this->info('¡La aplicación está en estado óptimo!');
        } elseif ($score >= 75) {
            $this->info("🥈 PUNTUACIÓN DE SALUD: {$score}/100 - BUENO");
            $this->info('La aplicación funciona bien con mejoras menores necesarias.');
        } elseif ($score >= 60) {
            $this->warn("🥉 PUNTUACIÓN DE SALUD: {$score}/100 - REGULAR");
            $this->warn('Se requieren algunas mejoras para optimizar el rendimiento.');
        } else {
            $this->error("🚨 PUNTUACIÓN DE SALUD: {$score}/100 - CRÍTICO");
            $this->error('Se requieren mejoras urgentes en la aplicación.');
        }
        
        $this->newLine();
        $this->info('💡 Ejecuta: php artisan app:health-check --fix para ver soluciones automáticas');
    }
}