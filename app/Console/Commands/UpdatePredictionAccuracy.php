<?php

namespace App\Console\Commands;

use App\Models\MatchPrediction;
use Illuminate\Console\Command;

class UpdatePredictionAccuracy extends Command
{
    protected $signature = 'predictions:update-accuracy {--limit=100 : Limit the number of predictions to update}';
    protected $description = 'Update prediction accuracy for finished matches';

    public function handle()
    {
        $limit = $this->option('limit');
        
        $this->info("Actualizando precisión de predicciones para partidos finalizados...");
        
        // Get predictions for finished matches that need accuracy updates
        $predictions = MatchPrediction::whereHas('match', function($query) {
            $query->where('status', 'finished');
        })
        ->where(function($query) {
            $query->whereNull('both_teams_score_correct') // Only update those not yet calculated
                  ->orWhereNull('over_under_correct')
                  ->orWhereNull('first_half_over_0_5_correct'); // Include first half predictions
        })
        ->limit($limit)
        ->get();

        if ($predictions->isEmpty()) {
            $this->info('No hay predicciones que necesiten actualización.');
            return 0;
        }

        $updated = 0;
        $progressBar = $this->output->createProgressBar($predictions->count());
        $progressBar->start();

        foreach ($predictions as $prediction) {
            try {
                $prediction->checkAccuracy();
                $updated++;
            } catch (\Exception $e) {
                $this->error("\nError actualizando predicción ID {$prediction->id}: " . $e->getMessage());
            }
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();
        $this->info("✅ Actualizadas {$updated} predicciones exitosamente.");

        // Show some statistics
        $this->showAccuracyStats();

        return 0;
    }

    private function showAccuracyStats()
    {
        $this->newLine();
        $this->info('📊 Estadísticas de Precisión:');

        $totalFinished = MatchPrediction::whereHas('match', function($query) {
            $query->where('status', 'finished');
        })->whereNotNull('is_correct')->count();

        if ($totalFinished === 0) {
            $this->warn('No hay datos de precisión disponibles.');
            return;
        }

        // Result accuracy
        $resultCorrect = MatchPrediction::whereHas('match', function($query) {
            $query->where('status', 'finished');
        })->where('is_correct', true)->count();
        
        $resultAccuracy = round(($resultCorrect / $totalFinished) * 100, 1);
        $this->line("🎯 Precisión Resultado: {$resultAccuracy}% ({$resultCorrect}/{$totalFinished})");

        // Both teams score accuracy
        $bothTeamsCorrect = MatchPrediction::whereHas('match', function($query) {
            $query->where('status', 'finished');
        })->where('both_teams_score_correct', true)->count();
        
        $bothTeamsAccuracy = round(($bothTeamsCorrect / $totalFinished) * 100, 1);
        $this->line("⚽ Precisión Ambos Anotan: {$bothTeamsAccuracy}% ({$bothTeamsCorrect}/{$totalFinished})");

        // Over/Under accuracy
        $overUnderCorrect = MatchPrediction::whereHas('match', function($query) {
            $query->where('status', 'finished');
        })->where('over_under_correct', true)->count();
        
        $overUnderAccuracy = round(($overUnderCorrect / $totalFinished) * 100, 1);
        $this->line("🥅 Precisión Over/Under 2.5: {$overUnderAccuracy}% ({$overUnderCorrect}/{$totalFinished})");

        // Overall accuracy
        $predictions = MatchPrediction::whereHas('match', function($query) {
            $query->where('status', 'finished');
        })->whereNotNull('is_correct')->get();

        $totalAccuracySum = 0;
        $count = 0;
        foreach ($predictions as $prediction) {
            $accuracy = $prediction->overall_accuracy;
            if (!is_null($accuracy)) {
                $totalAccuracySum += $accuracy;
                $count++;
            }
        }

        if ($count > 0) {
            $avgAccuracy = round($totalAccuracySum / $count, 1);
            $this->line("📈 Precisión Promedio General: {$avgAccuracy}%");
        }
    }
}