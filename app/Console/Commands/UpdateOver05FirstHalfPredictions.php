<?php

namespace App\Console\Commands;

use App\Models\MatchPrediction;
use Illuminate\Console\Command;

class UpdateOver05FirstHalfPredictions extends Command
{
    protected $signature = 'predictions:update-over05-first-half {--limit=50 : Number of predictions to update}';
    protected $description = 'Update existing predictions with Over 0.5 First Half calculations';

    public function handle()
    {
        $limit = $this->option('limit');
        
        $this->info("🔄 Actualizando predicciones con campos Over 0.5 First Half...");
        
        // Obtener predicciones que necesitan actualización
        $predictions = MatchPrediction::whereNull('first_half_over_0_5_probability')
            ->whereNotNull('home_goals_prediction')
            ->whereNotNull('away_goals_prediction')
            ->limit($limit)
            ->get();
            
        if ($predictions->count() === 0) {
            $this->info('✅ No hay predicciones que necesiten actualización');
            return 0;
        }
        
        $this->info("Actualizando {$predictions->count()} predicciones...");
        
        $updated = 0;
        $bar = $this->output->createProgressBar($predictions->count());
        $bar->start();
        
        foreach ($predictions as $prediction) {
            try {
                // Calcular goles esperados en primera mitad (40-45% del total)
                $homeGoalsFirstHalf = ($prediction->home_goals_prediction ?? 0) * 0.425; // 42.5%
                $awayGoalsFirstHalf = ($prediction->away_goals_prediction ?? 0) * 0.425; // 42.5%
                $totalFirstHalfGoals = $homeGoalsFirstHalf + $awayGoalsFirstHalf;
                
                // Calcular probabilidad Over 0.5 1T usando distribución de Poisson
                // P(X > 0.5) = 1 - P(X <= 0) = 1 - e^(-λ)
                $over05FirstHalfProb = 1 - exp(-$totalFirstHalfGoals * 1.2); // Factor 1.2 para ajuste
                
                // Asegurar que la probabilidad esté entre 0 y 1
                $over05FirstHalfProb = max(0, min(1, $over05FirstHalfProb));
                
                // Actualizar la predicción
                $prediction->update([
                    'home_goals_first_half_prediction' => round($homeGoalsFirstHalf, 2),
                    'away_goals_first_half_prediction' => round($awayGoalsFirstHalf, 2),
                    'first_half_over_0_5_probability' => round($over05FirstHalfProb, 4)
                ]);
                
                $updated++;
                
            } catch (\Exception $e) {
                $this->error("Error actualizando predicción ID {$prediction->id}: {$e->getMessage()}");
            }
            
            $bar->advance();
        }
        
        $bar->finish();
        $this->newLine();
        
        $this->info("✅ Actualizadas {$updated} predicciones con campos Over 0.5 First Half");
        
        // Mostrar estadísticas
        $totalWithOver05 = MatchPrediction::whereNotNull('first_half_over_0_5_probability')->count();
        $totalPredictions = MatchPrediction::count();
        $percentage = round(($totalWithOver05 / $totalPredictions) * 100, 1);
        
        $this->info("📊 Estadísticas:");
        $this->line("   • Total predicciones con Over 0.5 1T: {$totalWithOver05}");
        $this->line("   • Total predicciones: {$totalPredictions}");
        $this->line("   • Porcentaje completado: {$percentage}%");
        
        if ($totalWithOver05 < $totalPredictions) {
            $remaining = $totalPredictions - $totalWithOver05;
            $this->warn("⚠️  Quedan {$remaining} predicciones por actualizar");
            $this->line("   Ejecuta: php artisan predictions:update-over05-first-half --limit={$remaining}");
        }
        
        return 0;
    }
}