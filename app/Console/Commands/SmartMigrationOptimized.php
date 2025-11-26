<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\MatchPrediction;
use App\Models\FootballMatch;
use App\Services\RealDataService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SmartMigrationOptimized extends Command
{
    protected $signature = 'migrate:smart-optimized 
                           {--limit=2000 : Maximum number of predictions to migrate}
                           {--batch=50 : Batch size for processing}
                           {--strategy=mixed : Migration strategy: recent|diverse|mixed}';

    protected $description = 'Optimized migration strategy to minimize API requests while maximizing ML training value';

    private RealDataService $realDataService;
    private int $apiRequestsUsed = 0;
    private int $maxApiRequests;

    public function __construct()
    {
        parent::__construct();
        $this->realDataService = app(RealDataService::class);
    }

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $batchSize = (int) $this->option('batch');
        $strategy = $this->option('strategy');
        $this->maxApiRequests = floor($limit / 2); // Conservative API usage

        $this->info("🚀 MIGRACIÓN INTELIGENTE OPTIMIZADA");
        $this->info("Límite: {$limit} predicciones");
        $this->info("Estrategia: {$strategy}");
        $this->info("Requests API máximas: {$this->maxApiRequests}");
        
        $syntheticPredictions = $this->selectOptimalPredictions($limit, $strategy);
        $this->info("✅ Seleccionadas " . $syntheticPredictions->count() . " predicciones para migrar");

        $migrated = 0;
        $errors = 0;

        foreach ($syntheticPredictions->chunk($batchSize) as $batch) {
            if ($this->apiRequestsUsed >= $this->maxApiRequests) {
                $this->warn("⚠️ Límite de API alcanzado. Deteniendo migración.");
                break;
            }

            foreach ($batch as $prediction) {
                try {
                    if ($this->migratePredictionOptimized($prediction)) {
                        $migrated++;
                        if ($migrated % 50 == 0) {
                            $this->line("✅ Migradas: {$migrated} | API usada: {$this->apiRequestsUsed}");
                        }
                    }
                } catch (\Exception $e) {
                    $errors++;
                    Log::error("Migration error for prediction {$prediction->id}: " . $e->getMessage());
                }
            }
        }

        $this->displayResults($migrated, $errors);
        return Command::SUCCESS;
    }

    private function selectOptimalPredictions(int $limit, string $strategy)
    {
        $query = MatchPrediction::where('model_version', 'not like', '%real%');

        switch ($strategy) {
            case 'recent':
                // Predicciones más recientes (mejor para entrenar con datos actuales)
                return $query->orderBy('created_at', 'desc')->limit($limit)->get();

            case 'diverse':
                // Predicciones diversas por confianza (mejor variedad para ML)
                return $query->orderBy('confidence_score', 'desc')
                           ->orderBy('created_at', 'desc')
                           ->limit($limit)->get();

            case 'mixed':
            default:
                // Estrategia mixta: 60% recientes + 40% alta confianza
                $recentLimit = floor($limit * 0.6);
                $confidenceLimit = $limit - $recentLimit;

                $recent = $query->orderBy('created_at', 'desc')
                               ->limit($recentLimit)->get();

                $highConfidence = MatchPrediction::where('model_version', 'not like', '%real%')
                                               ->where('confidence_score', '>', 0.7)
                                               ->whereNotIn('id', $recent->pluck('id'))
                                               ->orderBy('confidence_score', 'desc')
                                               ->limit($confidenceLimit)->get();

                return $recent->merge($highConfidence);
        }
    }

    private function migratePredictionOptimized(MatchPrediction $prediction): bool
    {
        // Obtener información del match relacionado
        $match = $prediction->match ?? FootballMatch::find($prediction->match_id);
        if (!$match) {
            Log::warning("No match found for prediction {$prediction->id}");
            return false;
        }

        // Estrategia de cache: verificar si ya tenemos datos reales similares
        $existingReal = MatchPrediction::where('model_version', 'like', '%real%')
                                      ->whereHas('match', function($q) use ($match) {
                                          $q->where('home_team', $match->home_team)
                                            ->where('away_team', $match->away_team);
                                      })
                                      ->where('created_at', '>=', now()->subDays(90))
                                      ->first();

        if ($existingReal) {
            // Reutilizar datos reales existentes (0 API requests)
            $this->reuseExistingRealData($prediction, $existingReal);
            return true;
        }

        // Si no hay datos similares, hacer request a API
        if ($this->apiRequestsUsed >= $this->maxApiRequests) {
            return false;
        }

        try {
            $realData = $this->realDataService->getMatchData(
                $match->home_team, 
                $match->away_team
            );

            if ($realData) {
                $this->updatePredictionWithRealData($prediction, $realData);
                $this->apiRequestsUsed += 2; // Típicamente 2 requests por match
                return true;
            }
        } catch (\Exception $e) {
            Log::error("API request failed for prediction {$prediction->id}: " . $e->getMessage());
        }

        return false;
    }

    private function reuseExistingRealData(MatchPrediction $synthetic, MatchPrediction $real): void
    {
        $synthetic->update([
            'model_version' => 'v3.0.0-real-reused-' . now()->format('Ymd'),
            'home_goals_prediction' => $real->home_goals_prediction,
            'away_goals_prediction' => $real->away_goals_prediction,
            'both_teams_score_prediction' => $real->both_teams_score_prediction,
            'confidence_score' => $real->confidence_score * 0.95, // Slight penalty for reused data
            'updated_at' => now()
        ]);
    }

    private function updatePredictionWithRealData(MatchPrediction $prediction, array $realData): void
    {
        $prediction->update([
            'model_version' => 'v3.0.0-real-optimized-' . now()->format('Ymd'),
            'home_goals_prediction' => $realData['home_goals'] ?? $prediction->home_goals_prediction,
            'away_goals_prediction' => $realData['away_goals'] ?? $prediction->away_goals_prediction,
            'both_teams_score_prediction' => $realData['both_score'] ?? $prediction->both_teams_score_prediction,
            'confidence_score' => min($realData['confidence'] ?? 0.8, 0.95),
            'updated_at' => now()
        ]);
    }

    private function displayResults(int $migrated, int $errors): void
    {
        $this->info("🎉 MIGRACIÓN COMPLETADA");
        $this->table(['Métrica', 'Valor'], [
            ['Predicciones migradas', $migrated],
            ['Errores', $errors],
            ['API requests usadas', $this->apiRequestsUsed],
            ['Eficiencia', round(($migrated / max($this->apiRequestsUsed, 1)), 2) . ' predicciones/request']
        ]);

        // Calcular nuevo porcentaje de datos reales
        $totalReal = MatchPrediction::where('model_version', 'like', '%real%')->count();
        $totalPredictions = MatchPrediction::count();
        $realPercentage = round(($totalReal / $totalPredictions) * 100, 2);

        $this->info("📊 PROGRESO TOTAL: {$realPercentage}% datos reales ({$totalReal}/{$totalPredictions})");

        if ($realPercentage >= 20) {
            $this->info("🤖 ¡LISTO PARA ENTRENAR MODELOS ML! (≥20% datos reales)");
            $this->info("Ejecuta: php artisan ml:train-enhanced");
        } else {
            $this->info("🎯 Objetivo para entrenamiento: " . (20 - $realPercentage) . "% más de datos reales");
        }
    }
}