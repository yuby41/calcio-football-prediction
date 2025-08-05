<?php

namespace App\Console\Commands;

use App\Models\MatchPrediction;
use Illuminate\Console\Command;

class VerifyPredictionAccuracy extends Command
{
    protected $signature = 'predictions:verify {--fix : Fix incorrect accuracy calculations} {--limit=200 : Limit the number of predictions to check}';
    protected $description = 'Verify and optionally fix prediction accuracy calculations for inconsistencies';

    public function handle()
    {
        $fix = $this->option('fix');
        $limit = $this->option('limit');
        
        $this->info($fix ? "🔧 Verificando y corrigiendo precisión de predicciones..." : "🔍 Verificando precisión de predicciones...");
        
        // Get predictions for finished matches
        $predictions = MatchPrediction::whereHas('match', function($query) {
            $query->where('status', 'finished')
                  ->whereNotNull('home_goals')
                  ->whereNotNull('away_goals');
        })
        ->with('match')
        ->orderBy('id', 'desc')
        ->limit($limit)
        ->get();

        if ($predictions->isEmpty()) {
            $this->info('✅ No hay predicciones que verificar.');
            return 0;
        }

        $this->info("🎯 Verificando {$predictions->count()} predicciones:");

        $inconsistencies = 0;
        $fixed = 0;
        $progressBar = $this->output->createProgressBar($predictions->count());
        $progressBar->start();

        foreach ($predictions as $prediction) {
            try {
                $hasInconsistency = $this->checkPredictionConsistency($prediction, $fix);
                if ($hasInconsistency) {
                    $inconsistencies++;
                    if ($fix) {
                        $fixed++;
                    }
                }
            } catch (\Exception $e) {
                $this->error("\n❌ Error verificando predicción ID {$prediction->id}: " . $e->getMessage());
            }
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();

        if ($fix) {
            $this->info("🔧 CORRECCIÓN COMPLETADA:");
            $this->info("   - ❌ Inconsistencias encontradas: {$inconsistencies}");
            $this->info("   - ✅ Predicciones corregidas: {$fixed}");
        } else {
            $this->info("🔍 VERIFICACIÓN COMPLETADA:");
            $this->info("   - ❌ Inconsistencias encontradas: {$inconsistencies}");
            if ($inconsistencies > 0) {
                $this->warn("   💡 Ejecuta con --fix para corregir automáticamente");
            }
        }

        // Show final statistics after fixing
        if ($fix && $fixed > 0) {
            $this->newLine();
            $this->info("📊 Ejecutando recálculo de estadísticas generales...");
            $this->call('predictions:update-accuracy');
        }

        return 0;
    }

    private function checkPredictionConsistency(MatchPrediction $prediction, bool $fix = false): bool
    {
        $match = $prediction->match;
        $hasInconsistency = false;

        // Check main outcome prediction consistency
        $actualResult = $match->result;
        $expectedCorrect = $prediction->predicted_outcome === $actualResult;
        
        if ($prediction->is_correct != $expectedCorrect) {
            $hasInconsistency = true;
            $this->error("\n❌ INCONSISTENCIA en predicción ID {$prediction->id}:");
            $this->line("   🏟️  Partido: {$match->homeTeam->name} {$match->home_goals}-{$match->away_goals} {$match->awayTeam->name}");
            $this->line("   🎯 Predicción: {$prediction->predicted_outcome}");
            $this->line("   📊 Resultado real: {$actualResult}");
            $this->line("   ❗ Marcado como: " . ($prediction->is_correct ? 'CORRECTO' : 'INCORRECTO'));
            $this->line("   ✅ Debería ser: " . ($expectedCorrect ? 'CORRECTO' : 'INCORRECTO'));
            
            if ($fix) {
                $prediction->is_correct = $expectedCorrect;
                $prediction->save();
                $this->info("   🔧 CORREGIDO");
            }
        }

        // Check both teams score consistency
        if (!is_null($prediction->both_teams_score_correct)) {
            $actualBothScore = ($match->home_goals > 0 && $match->away_goals > 0);
            $predictedBothScore = $prediction->both_teams_score_probability > 0.5;
            $expectedBothTeamsCorrect = $actualBothScore === $predictedBothScore;
            
            if ($prediction->both_teams_score_correct != $expectedBothTeamsCorrect) {
                $hasInconsistency = true;
                $this->error("\n❌ INCONSISTENCIA BTS en predicción ID {$prediction->id}:");
                $this->line("   🎯 Predicción BTS: " . ($predictedBothScore ? 'Sí' : 'No'));
                $this->line("   📊 Resultado real BTS: " . ($actualBothScore ? 'Sí' : 'No'));
                $this->line("   ❗ Marcado como: " . ($prediction->both_teams_score_correct ? 'CORRECTO' : 'INCORRECTO'));
                $this->line("   ✅ Debería ser: " . ($expectedBothTeamsCorrect ? 'CORRECTO' : 'INCORRECTO'));
                
                if ($fix) {
                    $prediction->both_teams_score_correct = $expectedBothTeamsCorrect;
                    $prediction->save();
                    $this->info("   🔧 BTS CORREGIDO");
                }
            }
        }

        // Check over/under 2.5 consistency
        if (!is_null($prediction->over_under_correct)) {
            $actualTotalGoals = $match->home_goals + $match->away_goals;
            $predictedOver25 = $prediction->over_2_5_probability > $prediction->under_2_5_probability;
            $actualOver25 = $actualTotalGoals > 2.5;
            $expectedOverUnderCorrect = $actualOver25 === $predictedOver25;
            
            if ($prediction->over_under_correct != $expectedOverUnderCorrect) {
                $hasInconsistency = true;
                $this->error("\n❌ INCONSISTENCIA O/U en predicción ID {$prediction->id}:");
                $this->line("   🎯 Predicción O/U 2.5: " . ($predictedOver25 ? 'Over' : 'Under'));
                $this->line("   📊 Goles totales: {$actualTotalGoals} (" . ($actualOver25 ? 'Over' : 'Under') . " 2.5)");
                $this->line("   ❗ Marcado como: " . ($prediction->over_under_correct ? 'CORRECTO' : 'INCORRECTO'));
                $this->line("   ✅ Debería ser: " . ($expectedOverUnderCorrect ? 'CORRECTO' : 'INCORRECTO'));
                
                if ($fix) {
                    $prediction->over_under_correct = $expectedOverUnderCorrect;
                    $prediction->save();
                    $this->info("   🔧 O/U CORREGIDO");
                }
            }
        }

        // Check first half over 0.5 consistency
        if (!is_null($prediction->first_half_over_0_5_correct) && 
            !is_null($match->home_goals_first_half) && !is_null($match->away_goals_first_half)) {
            
            $actualFirstHalfGoals = $match->home_goals_first_half + $match->away_goals_first_half;
            $predictedFirstHalfOver05 = $prediction->first_half_over_0_5_probability > 0.5;
            $actualFirstHalfOver05 = $actualFirstHalfGoals > 0.5;
            $expectedFirstHalfCorrect = $actualFirstHalfOver05 === $predictedFirstHalfOver05;
            
            if ($prediction->first_half_over_0_5_correct != $expectedFirstHalfCorrect) {
                $hasInconsistency = true;
                $this->error("\n❌ INCONSISTENCIA PRIMER TIEMPO en predicción ID {$prediction->id}:");
                $this->line("   🎯 Predicción Over 0.5 1T: " . ($predictedFirstHalfOver05 ? 'Sí' : 'No'));
                $this->line("   📊 Goles primer tiempo: {$actualFirstHalfGoals} (" . ($actualFirstHalfOver05 ? 'Over' : 'Under') . " 0.5)");
                $this->line("   ❗ Marcado como: " . ($prediction->first_half_over_0_5_correct ? 'CORRECTO' : 'INCORRECTO'));
                $this->line("   ✅ Debería ser: " . ($expectedFirstHalfCorrect ? 'CORRECTO' : 'INCORRECTO'));
                
                if ($fix) {
                    $prediction->first_half_over_0_5_correct = $expectedFirstHalfCorrect;
                    $prediction->save();
                    $this->info("   🔧 PRIMER TIEMPO CORREGIDO");
                }
            }
        }

        return $hasInconsistency;
    }
}