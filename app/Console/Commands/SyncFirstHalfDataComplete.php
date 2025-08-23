<?php

namespace App\Console\Commands;

use App\Models\FootballMatch;
use App\Services\StatisticsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SyncFirstHalfDataComplete extends Command
{
    protected $signature = 'matches:sync-first-half-complete 
                            {--months=12 : Number of months back to sync (default: 12, use 0 for all data)}
                            {--batch=20 : Matches per batch} 
                            {--delay=200 : Delay in milliseconds between requests}
                            {--limit=1000 : Maximum number of matches to process}
                            {--force : Force update even if first half data exists}';
    
    protected $description = 'Complete sync of first half data from API-Sports including historical data';

    public function handle()
    {
        $this->info('🚨 SINCRONIZACIÓN COMPLETA DE DATOS PRIMER TIEMPO');
        $this->info('===============================================');
        
        $months = $this->option('months');
        $forceUpdate = $this->option('force');
        $limit = $this->option('limit');
        
        // Build query
        $query = FootballMatch::where('status', 'finished')
            ->whereNotNull('external_id')
            ->orderBy('match_date', 'desc');

        // Date filter
        if ($months > 0) {
            $query->where('match_date', '>=', now()->subMonths($months));
            $this->info("📅 Analizando últimos {$months} meses");
        } else {
            $this->info("📅 Analizando TODOS los datos históricos");
        }

        // Force filter
        if (!$forceUpdate) {
            $query->where(function($q) {
                $q->whereNull('home_goals_first_half')
                  ->orWhereNull('away_goals_first_half');
            });
            $this->info("🔍 Solo partidos SIN datos primer tiempo");
        } else {
            $this->info("🔄 Forzando actualización de TODOS los partidos");
        }

        $query->limit($limit);
        $matches = $query->get();

        if ($matches->isEmpty()) {
            $this->info('✅ No se encontraron partidos para sincronizar');
            return Command::SUCCESS;
        }

        $this->info("📊 Encontrados {$matches->count()} partidos para verificar y corregir");
        
        // Show date range
        $firstMatch = $matches->last();
        $lastMatch = $matches->first();
        $this->info("📅 Rango: {$firstMatch->match_date->format('Y-m-d')} a {$lastMatch->match_date->format('Y-m-d')}");
        
        if (!$this->confirm('Esto realizará llamadas a la API. ¿Continuar?')) {
            return Command::SUCCESS;
        }

        $apiKey = config('services.football_api.key');
        $baseUrl = config('services.football_api.base_url', 'https://v3.football.api-sports.io');
        $batchSize = $this->option('batch');
        $delay = $this->option('delay') * 1000; // Convert to microseconds
        
        $processed = 0;
        $corrected = 0;
        $errors = 0;
        $statisticsChanges = [];
        $apiCalls = 0;

        $progressBar = $this->output->createProgressBar($matches->count());
        $progressBar->start();

        foreach ($matches->chunk($batchSize) as $batch) {
            foreach ($batch as $match) {
                try {
                    // Store original data for comparison
                    $originalHome1T = $match->home_goals_first_half;
                    $originalAway1T = $match->away_goals_first_half;
                    
                    // Fetch from API
                    $response = Http::withHeaders([
                        'x-apisports-key' => $apiKey,
                        'Accept' => 'application/json',
                    ])->get($baseUrl . '/fixtures', [
                        'id' => $match->external_id
                    ]);

                    $apiCalls++;

                    if ($response->successful()) {
                        $data = $response->json();
                        if (!empty($data['response'])) {
                            $fixture = $data['response'][0];
                            
                            $realHome1T = $fixture['score']['halftime']['home'] ?? null;
                            $realAway1T = $fixture['score']['halftime']['away'] ?? null;
                            
                            if ($realHome1T !== null && $realAway1T !== null) {
                                // Check if data is different or if forcing update
                                $needsUpdate = $forceUpdate || 
                                              $originalHome1T !== $realHome1T || 
                                              $originalAway1T !== $realAway1T ||
                                              is_null($originalHome1T) ||
                                              is_null($originalAway1T);
                                
                                if ($needsUpdate) {
                                    // Update match with real API data
                                    $match->home_goals_first_half = $realHome1T;
                                    $match->away_goals_first_half = $realAway1T;
                                    
                                    // Also verify full-time score
                                    $realHomeFT = $fixture['goals']['home'] ?? $match->home_goals;
                                    $realAwayFT = $fixture['goals']['away'] ?? $match->away_goals;
                                    $match->home_goals = $realHomeFT;
                                    $match->away_goals = $realAwayFT;
                                    
                                    $match->save();
                                    
                                    // Update prediction accuracy
                                    if ($match->prediction && !is_null($match->prediction->first_half_over_0_5_probability)) {
                                        $oldFirstHalfGoals = ($originalHome1T ?? 0) + ($originalAway1T ?? 0);
                                        $newFirstHalfGoals = $realHome1T + $realAway1T;
                                        
                                        $oldActualOver05 = $oldFirstHalfGoals > 0.5;
                                        $newActualOver05 = $newFirstHalfGoals > 0.5;
                                        $predictedOver05 = $match->prediction->first_half_over_0_5_probability > 0.5;
                                        
                                        $oldCorrect = $oldActualOver05 === $predictedOver05;
                                        $newCorrect = $newActualOver05 === $predictedOver05;
                                        
                                        $match->prediction->first_half_over_0_5_correct = $newCorrect;
                                        $match->prediction->save();
                                        
                                        // Track statistical changes
                                        if ($oldCorrect !== $newCorrect) {
                                            $statisticsChanges[] = [
                                                'match_id' => $match->id,
                                                'was_correct' => $oldCorrect,
                                                'now_correct' => $newCorrect,
                                                'old_1t' => ($originalHome1T ?? 'null') . "-" . ($originalAway1T ?? 'null'),
                                                'new_1t' => "{$realHome1T}-{$realAway1T}",
                                                'match_date' => $match->match_date->format('Y-m-d')
                                            ];
                                        }
                                    }
                                    
                                    $corrected++;
                                }
                            }
                        }
                    } else {
                        $errors++;
                    }
                    
                    $processed++;
                    $progressBar->advance();
                    
                    // Rate limiting - more conservative for historical data
                    usleep($delay);
                    
                } catch (\Exception $e) {
                    $errors++;
                    $progressBar->advance();
                }
            }
        }

        $progressBar->finish();
        $this->newLine(2);
        
        $this->info("📊 RESUMEN DE SINCRONIZACIÓN COMPLETA:");
        $this->info("====================================");
        $this->info("✅ Procesados: {$processed}");
        $this->info("🔧 Corregidos: {$corrected}");
        $this->info("❌ Errores: {$errors}");
        $this->info("🌐 Llamadas API: {$apiCalls}");
        $this->info("📈 Cambios estadísticos: " . count($statisticsChanges));
        
        if (!empty($statisticsChanges)) {
            $this->info("\n📊 IMPACTO EN ESTADÍSTICAS:");
            $wrongToRight = 0;
            $rightToWrong = 0;
            
            foreach ($statisticsChanges as $change) {
                if (!$change['was_correct'] && $change['now_correct']) {
                    $wrongToRight++;
                } elseif ($change['was_correct'] && !$change['now_correct']) {
                    $rightToWrong++;
                }
            }
            
            $this->info("✅ Predicciones mejoradas (INCORRECTO → CORRECTO): {$wrongToRight}");
            $this->info("❌ Predicciones degradadas (CORRECTO → INCORRECTO): {$rightToWrong}");
            $this->info("📊 Cambio neto en precisión: " . ($wrongToRight - $rightToWrong));
            
            // Show some examples of changes
            if (count($statisticsChanges) > 0) {
                $this->info("\n📝 Ejemplos de cambios:");
                $shown = 0;
                foreach ($statisticsChanges as $change) {
                    if ($shown >= 5) break;
                    $arrow = $change['was_correct'] ? '✅→❌' : '❌→✅';
                    $this->line("  {$change['match_date']}: {$change['old_1t']} → {$change['new_1t']} {$arrow}");
                    $shown++;
                }
            }
            
            if ($corrected > 0) {
                $this->info("\n🔄 Actualizando estadísticas...");
                $statisticsService = app(StatisticsService::class);
                $statisticsService->updateFirstHalfStatistics();
                $this->info("✅ Estadísticas actualizadas con datos reales");
            }
        }
        
        $this->info("\n🎯 DATOS HISTÓRICOS SINCRONIZADOS CON API-SPORTS");
        
        return Command::SUCCESS;
    }
}