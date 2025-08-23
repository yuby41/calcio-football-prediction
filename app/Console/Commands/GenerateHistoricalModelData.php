<?php

namespace App\Console\Commands;

use App\Models\FootballMatch;
use App\Models\MatchPrediction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Carbon\Carbon;

class GenerateHistoricalModelData extends Command
{
    protected $signature = 'ml:generate-historical-data 
                            {--model= : Specific model to generate data for (enhanced, hybrid, base, all)}
                            {--limit=500 : Number of matches to process}
                            {--chunk=25 : Process matches in chunks of N}
                            {--from= : Start date (YYYY-MM-DD), defaults to 6 months ago}
                            {--to= : End date (YYYY-MM-DD), defaults to today}
                            {--overwrite : Overwrite existing predictions}
                            {--dry-run : Show what would be processed without generating}';
    
    protected $description = 'Generate historical predictions data for Enhanced and Hybrid models to improve comparison accuracy';

    private $availableModels = [
        'enhanced' => 'enhanced_football_predictor.py',
        'hybrid' => 'hybrid_predictor.py', 
        'base' => 'football_predictor.py'
    ];

    public function handle()
    {
        $model = $this->option('model');
        $limit = (int) $this->option('limit');
        $chunkSize = (int) $this->option('chunk');
        $overwrite = $this->option('overwrite');
        $dryRun = $this->option('dry-run');
        
        // Set date range
        $fromDate = $this->option('from') ? Carbon::parse($this->option('from')) : now()->subMonths(6);
        $toDate = $this->option('to') ? Carbon::parse($this->option('to')) : now();

        $this->info('🔮 Generando datos históricos para modelos ML...');
        $this->info("Fecha: {$fromDate->format('Y-m-d')} a {$toDate->format('Y-m-d')}");
        $this->info("Límite: {$limit}, Lotes: {$chunkSize}, Sobrescribir: " . ($overwrite ? 'Sí' : 'No'));

        // Determine which models to process
        $modelsToProcess = $this->getModelsToProcess($model);
        
        if (empty($modelsToProcess)) {
            $this->error('No hay modelos válidos para procesar');
            return 1;
        }

        $this->info("Modelos a procesar: " . implode(', ', array_keys($modelsToProcess)));
        $this->line('');

        foreach ($modelsToProcess as $modelName => $scriptName) {
            $this->info("🤖 Procesando modelo: {$modelName}");
            
            $matches = $this->getMatchesForModel($modelName, $fromDate, $toDate, $limit, $overwrite);
            
            if ($matches->isEmpty()) {
                $this->info("✅ No hay partidos que procesar para {$modelName}");
                continue;
            }

            $this->info("📝 Encontrados {$matches->count()} partidos para procesar");

            if ($dryRun) {
                $this->warn('🔍 DRY RUN - Mostraría:');
                foreach ($matches->take(5) as $match) {
                    $this->line("  - {$match->homeTeam->name} vs {$match->awayTeam->name} ({$match->match_date->format('Y-m-d')})");
                }
                if ($matches->count() > 5) {
                    $this->line("  ... y " . ($matches->count() - 5) . " partidos más");
                }
                continue;
            }

            $this->processMatchesForModel($modelName, $scriptName, $matches, $chunkSize);
        }

        $this->info("\n🎉 Generación de datos históricos completada!");
        return 0;
    }

    private function getModelsToProcess($model): array
    {
        if ($model === 'all') {
            return $this->availableModels;
        } elseif ($model && isset($this->availableModels[$model])) {
            return [$model => $this->availableModels[$model]];
        } elseif (!$model) {
            // Default to enhanced and hybrid
            return [
                'enhanced' => $this->availableModels['enhanced'],
                'hybrid' => $this->availableModels['hybrid']
            ];
        } else {
            $this->error("Modelo no válido: {$model}");
            $this->info("Modelos disponibles: " . implode(', ', array_keys($this->availableModels)));
            return [];
        }
    }

    private function getMatchesForModel($modelName, $fromDate, $toDate, $limit, $overwrite): \Illuminate\Database\Eloquent\Collection
    {
        $query = FootballMatch::where('status', 'finished')
            ->whereNotNull('home_goals')
            ->whereNotNull('away_goals')
            ->whereBetween('match_date', [$fromDate, $toDate])
            ->with(['homeTeam', 'awayTeam']);

        // PROFESSIONAL DATA REQUIREMENT: Enhanced model only processes matches with real team statistics
        if ($modelName === 'enhanced') {
            $query->whereExists(function($subq) {
                $subq->select(\DB::raw(1))
                     ->from('team_statistics')
                     ->whereColumn('team_statistics.team_id', 'matches.home_team_id')
                     ->where('matches_played', '>=', 3); // Minimum games for reliable stats
            })->whereExists(function($subq) {
                $subq->select(\DB::raw(1))
                     ->from('team_statistics')
                     ->whereColumn('team_statistics.team_id', 'matches.away_team_id')
                     ->where('matches_played', '>=', 3); // Minimum games for reliable stats
            });
        }

        if (!$overwrite) {
            // Only get matches that don't have predictions for this model version
            $modelVersions = $this->getModelVersions($modelName);
            $query->whereDoesntHave('prediction', function($q) use ($modelVersions) {
                $q->whereIn('model_version', $modelVersions);
            });
        }

        return $query->orderBy('match_date', 'desc')
                    ->limit($limit)
                    ->get();
    }

    private function getModelVersions($modelName): array
    {
        switch ($modelName) {
            case 'enhanced':
                return ['3.0.0-enhanced-outcomes'];
            case 'hybrid':
                return ['hybrid_ensemble_v1.0', 'hybrid_enhanced_only', 'hybrid_simple_only'];
            case 'base':
                return ['2.1.0-ensemble-calibrated', '2.0.0-ensemble'];
            default:
                return [];
        }
    }

    private function processMatchesForModel($modelName, $scriptName, $matches, $chunkSize)
    {
        $generated = 0;
        $errors = 0;
        $startTime = microtime(true);

        // Progress bar
        $progressBar = $this->output->createProgressBar($matches->count());
        $progressBar->start();

        foreach ($matches->chunk($chunkSize) as $chunkIndex => $chunk) {
            $this->newLine();
            $this->info("🔄 Procesando lote " . ($chunkIndex + 1) . " ({$chunk->count()} partidos)");
            
            foreach ($chunk as $match) {
                try {
                    $result = $this->generatePredictionForMatch($modelName, $scriptName, $match);
                    
                    if ($result) {
                        $this->storePrediction($match, $result);
                        $generated++;
                    } else {
                        $errors++;
                        $this->warn("❌ Falló predicción para partido {$match->id}");
                    }
                } catch (\Exception $e) {
                    $errors++;
                    $this->error("❌ Error en partido {$match->id}: " . $e->getMessage());
                }
                
                $progressBar->advance();
            }
            
            // Progress update
            $elapsed = microtime(true) - $startTime;
            $processed = $generated + $errors;
            $rate = $processed > 0 ? $processed / $elapsed : 0;
            $remaining = $matches->count() - $processed;
            $eta = $rate > 0 ? $remaining / $rate : 0;
            
            $this->info("📊 Progreso: {$processed}/{$matches->count()} | Éxitos: {$generated} | Errores: {$errors} | ETA: " . gmdate('H:i:s', $eta));
            
            // Small delay between chunks
            if ($chunkIndex < $matches->chunk($chunkSize)->count() - 1) {
                usleep(100000); // 0.1 second
            }
        }

        $progressBar->finish();
        
        $totalTime = microtime(true) - $startTime;
        $successRate = ($generated + $errors) > 0 ? round(($generated / ($generated + $errors)) * 100, 1) : 0;
        
        $this->newLine();
        $this->info("✅ Completado para {$modelName}:");
        $this->info("⏱️  Tiempo total: " . gmdate('H:i:s', $totalTime));
        $this->info("✅ Generados: {$generated}");
        $this->warn("❌ Errores: {$errors}");
        $this->info("📈 Tasa de éxito: {$successRate}%");
        $this->info("⚡ Velocidad promedio: " . round($rate, 1) . " predicciones/segundo");
    }

    private function generatePredictionForMatch($modelName, $scriptName, $match): ?array
    {
        $basePath = base_path();
        $command = [
            '/bin/bash', '-c',
            "cd {$basePath} && source ml_env/bin/activate && python ml/{$scriptName} predict {$match->home_team_id} {$match->away_team_id}"
        ];

        $process = new Process($command);
        $process->setTimeout(60); // 1 minute timeout per prediction
        $process->run();

        if (!$process->isSuccessful()) {
            $this->warn("Proceso falló para {$modelName}: " . $process->getErrorOutput());
            return null;
        }

        $output = $process->getOutput();
        
        // Extract JSON from output (handle debug info or mixed output)
        $jsonText = null;
        
        // Find the complete JSON object - look for opening and closing braces
        $openBrace = strpos($output, '{');
        if ($openBrace !== false) {
            $braceCount = 0;
            $jsonEnd = $openBrace;
            
            for ($i = $openBrace; $i < strlen($output); $i++) {
                if ($output[$i] === '{') {
                    $braceCount++;
                } elseif ($output[$i] === '}') {
                    $braceCount--;
                    if ($braceCount === 0) {
                        $jsonEnd = $i + 1;
                        break;
                    }
                }
            }
            
            $jsonText = substr($output, $openBrace, $jsonEnd - $openBrace);
        } else {
            // Final fallback
            $jsonText = $output;
        }
        
        $result = json_decode($jsonText, true);

        if (!$result) {
            $this->warn("JSON inválido de {$modelName}: " . $jsonText);
            return null;
        }
        
        if (!isset($result['predicted_outcome'])) {
            $this->warn("Falta 'predicted_outcome' en {$modelName}. Claves disponibles: " . implode(', ', array_keys($result)));
            return null;
        }

        return $result;
    }

    private function storePrediction($match, $predictionData)
    {
        // Check if this specific model version already exists
        $existingPrediction = MatchPrediction::where('match_id', $match->id)
            ->where('model_version', $predictionData['model_version'])
            ->first();

        if ($existingPrediction && !$this->option('overwrite')) {
            return; // Skip if exists and not overwriting
        }

        // Remove existing prediction if overwriting
        if ($existingPrediction && $this->option('overwrite')) {
            $existingPrediction->delete();
        }

        // Create new prediction
        MatchPrediction::create([
            'match_id' => $match->id,
            'predicted_outcome' => $predictionData['predicted_outcome'],
            'confidence_score' => $predictionData['confidence_score'] ?? 0.5,
            'model_version' => $predictionData['model_version'],
            
            // Goals predictions
            'home_goals_prediction' => $predictionData['home_goals_prediction'] ?? null,
            'away_goals_prediction' => $predictionData['away_goals_prediction'] ?? null,
            
            // Probabilities
            'home_win_probability' => $predictionData['home_win_probability'] ?? null,
            'draw_probability' => $predictionData['draw_probability'] ?? null,
            'away_win_probability' => $predictionData['away_win_probability'] ?? null,
            'both_teams_score_probability' => $predictionData['both_teams_score_probability'] ?? null,
            'over_2_5_probability' => $predictionData['over_2_5_probability'] ?? null,
            'under_2_5_probability' => $predictionData['under_2_5_probability'] ?? null,
            'first_half_over_0_5_probability' => $predictionData['first_half_over_0_5_probability'] ?? null,
            
            // First half goals
            'home_goals_first_half_prediction' => $predictionData['home_goals_first_half_prediction'] ?? null,
            'away_goals_first_half_prediction' => $predictionData['away_goals_first_half_prediction'] ?? null,
            
            // Derived predictions
            'over_2_5_prediction' => ($predictionData['over_2_5_probability'] ?? 0) > 0.5,
            'over_2_5_confidence' => $predictionData['over_2_5_probability'] ?? null,
            'over_0_5_first_half_prediction' => ($predictionData['first_half_over_0_5_probability'] ?? 0) > 0.5,
            'first_half_confidence' => $predictionData['first_half_over_0_5_probability'] ?? null,
            'both_teams_score_prediction' => ($predictionData['both_teams_score_probability'] ?? 0) > 0.5,
            'both_teams_score_confidence' => $predictionData['both_teams_score_probability'] ?? null,
            
            // Metadata
            'prediction_type' => 'historical_generation',
            'predicted_at' => now(),
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }
}