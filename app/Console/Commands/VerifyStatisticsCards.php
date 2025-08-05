<?php

namespace App\Console\Commands;

use App\Models\PredictionStatistic;
use App\Models\MatchPrediction;
use App\Models\FootballMatch;
use App\Services\SimpleAccuracyService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class VerifyStatisticsCards extends Command
{
    protected $signature = 'statistics:verify-cards';
    protected $description = 'Verify that statistics cards show correct data compared to actual database';

    public function handle()
    {
        $this->info('🔍 Verificando correspondencia entre cards de estadísticas y datos reales...');
        $this->line('');

        // 1. Verificar precisión general
        $overallAccuracy = SimpleAccuracyService::getCurrentAccuracy();
        $this->info("📊 Precisión General (SimpleAccuracyService): {$overallAccuracy}%");

        // 2. Obtener estadísticas de las cards
        $cardStats = PredictionStatistic::all()->keyBy('prediction_type');
        
        // 3. Calcular estadísticas reales
        $realStats = $this->calculateRealStatistics();
        
        $this->line('');
        $this->info('📋 COMPARACIÓN DE ESTADÍSTICAS:');
        $this->line(str_repeat('=', 80));
        
        $discrepancies = 0;
        $types = ['match_outcome', 'both_teams_score_yes', 'both_teams_score_no', 'over_2_5', 'under_2_5', 'first_half_over_0_5'];
        
        foreach ($types as $type) {
            $cardStat = $cardStats[$type] ?? null;
            $realStat = $realStats[$type] ?? null;
            
            if ($cardStat && $realStat) {
                $cardAccuracy = $cardStat->accuracy_percentage;
                $realAccuracy = $realStat['accuracy'];
                $difference = abs($cardAccuracy - $realAccuracy);
                
                $status = $difference <= 0.5 ? '✅' : '❌';
                if ($difference > 0.5) $discrepancies++;
                
                $this->line(sprintf(
                    '%s %-25s | Card: %6.1f%% (%4d/%4d) | Real: %6.1f%% (%4d/%4d) | Diff: %4.1f%%',
                    $status,
                    $type,
                    $cardAccuracy,
                    $cardStat->correct_predictions,
                    $cardStat->total_predictions,
                    $realAccuracy,
                    $realStat['correct'],
                    $realStat['total'],
                    $difference
                ));
            } elseif ($cardStat) {
                $this->line(sprintf('⚠️  %-25s | Card: %6.1f%% | Real: N/A', $type, $cardStat->accuracy_percentage));
                $discrepancies++;
            } elseif ($realStat) {
                $this->line(sprintf('⚠️  %-25s | Card: N/A | Real: %6.1f%%', $type, $realStat['accuracy']));
                $discrepancies++;
            }
        }
        
        $this->line('');
        
        if ($discrepancies === 0) {
            $this->info('✅ Todas las estadísticas están correctas y coinciden!');
        } else {
            $this->warn("❌ Se encontraron {$discrepancies} discrepancias.");
            $this->line('');
            
            if ($this->confirm('¿Deseas actualizar las estadísticas para corregir las discrepancias?')) {
                $this->call('statistics:update');
                $this->info('✅ Estadísticas actualizadas. Vuelve a ejecutar este comando para verificar.');
            }
        }
        
        return 0;
    }
    
    private function calculateRealStatistics(): array
    {
        $stats = [];
        
        // Match Outcome
        $matchOutcome = DB::selectOne('
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN is_correct = 1 THEN 1 ELSE 0 END) as correct
            FROM match_predictions mp
            JOIN matches m ON mp.match_id = m.id
            WHERE m.status = "finished"
              AND mp.is_correct IS NOT NULL
        ');
        
        if ($matchOutcome) {
            $stats['match_outcome'] = [
                'total' => $matchOutcome->total,
                'correct' => $matchOutcome->correct,
                'accuracy' => $matchOutcome->total > 0 ? round(($matchOutcome->correct / $matchOutcome->total) * 100, 2) : 0
            ];
        }
        
        // Both Teams Score YES
        $btsYes = DB::selectOne('
            SELECT 
                SUM(CASE WHEN both_teams_score_probability > 0.5 THEN 1 ELSE 0 END) as total,
                SUM(CASE WHEN both_teams_score_probability > 0.5 AND both_teams_score_correct = 1 THEN 1 ELSE 0 END) as correct
            FROM match_predictions mp
            JOIN matches m ON mp.match_id = m.id
            WHERE m.status = "finished"
              AND mp.both_teams_score_probability IS NOT NULL
              AND mp.both_teams_score_correct IS NOT NULL
        ');
        
        if ($btsYes) {
            $stats['both_teams_score_yes'] = [
                'total' => $btsYes->total,
                'correct' => $btsYes->correct,
                'accuracy' => $btsYes->total > 0 ? round(($btsYes->correct / $btsYes->total) * 100, 2) : 0
            ];
        }
        
        // Both Teams Score NO
        $btsNo = DB::selectOne('
            SELECT 
                SUM(CASE WHEN both_teams_score_probability <= 0.5 THEN 1 ELSE 0 END) as total,
                SUM(CASE WHEN both_teams_score_probability <= 0.5 AND both_teams_score_correct = 1 THEN 1 ELSE 0 END) as correct
            FROM match_predictions mp
            JOIN matches m ON mp.match_id = m.id
            WHERE m.status = "finished"
              AND mp.both_teams_score_probability IS NOT NULL
              AND mp.both_teams_score_correct IS NOT NULL
        ');
        
        if ($btsNo) {
            $stats['both_teams_score_no'] = [
                'total' => $btsNo->total,
                'correct' => $btsNo->correct,
                'accuracy' => $btsNo->total > 0 ? round(($btsNo->correct / $btsNo->total) * 100, 2) : 0
            ];
        }
        
        // Over 2.5
        $over25 = DB::selectOne('
            SELECT 
                SUM(CASE WHEN over_2_5_probability > 0.5 THEN 1 ELSE 0 END) as total,
                SUM(CASE WHEN over_2_5_probability > 0.5 AND over_under_correct = 1 THEN 1 ELSE 0 END) as correct
            FROM match_predictions mp
            JOIN matches m ON mp.match_id = m.id
            WHERE m.status = "finished"
              AND mp.over_2_5_probability IS NOT NULL
              AND mp.over_under_correct IS NOT NULL
        ');
        
        if ($over25) {
            $stats['over_2_5'] = [
                'total' => $over25->total,
                'correct' => $over25->correct,
                'accuracy' => $over25->total > 0 ? round(($over25->correct / $over25->total) * 100, 2) : 0
            ];
        }
        
        // Under 2.5
        $under25 = DB::selectOne('
            SELECT 
                SUM(CASE WHEN over_2_5_probability <= 0.5 THEN 1 ELSE 0 END) as total,
                SUM(CASE WHEN over_2_5_probability <= 0.5 AND over_under_correct = 1 THEN 1 ELSE 0 END) as correct
            FROM match_predictions mp
            JOIN matches m ON mp.match_id = m.id
            WHERE m.status = "finished"
              AND mp.over_2_5_probability IS NOT NULL
              AND mp.over_under_correct IS NOT NULL
        ');
        
        if ($under25) {
            $stats['under_2_5'] = [
                'total' => $under25->total,
                'correct' => $under25->correct,
                'accuracy' => $under25->total > 0 ? round(($under25->correct / $under25->total) * 100, 2) : 0
            ];
        }
        
        // First Half Over 0.5
        $firstHalf = DB::selectOne('
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN first_half_over_0_5_correct = 1 THEN 1 ELSE 0 END) as correct
            FROM match_predictions mp
            JOIN matches m ON mp.match_id = m.id
            WHERE m.status = "finished"
              AND mp.first_half_over_0_5_probability IS NOT NULL
              AND mp.first_half_over_0_5_correct IS NOT NULL
        ');
        
        if ($firstHalf) {
            $stats['first_half_over_0_5'] = [
                'total' => $firstHalf->total,
                'correct' => $firstHalf->correct,
                'accuracy' => $firstHalf->total > 0 ? round(($firstHalf->correct / $firstHalf->total) * 100, 2) : 0
            ];
        }
        
        return $stats;
    }
}