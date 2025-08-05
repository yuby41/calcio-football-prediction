<?php

namespace App\Console\Commands;

use App\Models\FootballMatch;
use Illuminate\Console\Command;

class VerifyMatchesPredictions extends Command
{
    protected $signature = 'matches:verify-predictions {--limit=20 : Number of matches to check} {--fix : Fix inconsistencies found}';
    protected $description = 'Verify matches page predictions for inconsistencies';

    public function handle()
    {
        $limit = (int) $this->option('limit');
        $fix = $this->option('fix');
        
        $this->info('🔍 Verificando incoherencias en predicciones de partidos...');
        
        $matches = FootballMatch::with(['prediction', 'homeTeam', 'awayTeam'])
            ->where('status', 'finished')
            ->whereHas('prediction')
            ->whereNotNull('home_goals')
            ->whereNotNull('away_goals')
            ->orderBy('match_date', 'desc')
            ->limit($limit)
            ->get();
            
        $inconsistencies = 0;
        $fixed = 0;
        
        foreach ($matches as $match) {
            $prediction = $match->prediction;
            if (!$prediction) continue;
            
            $issues = $this->checkMatchInconsistencies($match, $prediction);
            
            if (!empty($issues)) {
                $inconsistencies++;
                
                $this->line("❌ {$match->homeTeam->name} vs {$match->awayTeam->name} ({$match->home_goals}-{$match->away_goals})");
                
                foreach ($issues as $issue) {
                    $this->line("   {$issue}");
                }
                
                if ($fix) {
                    $this->fixMatchPrediction($match, $prediction);
                    $fixed++;
                    $this->info("   ✅ Corregido");
                }
                
                $this->line('---');
            }
        }
        
        $this->line('');
        
        if ($inconsistencies === 0) {
            $this->info('✅ No se encontraron incoherencias!');
        } else {
            $this->warn("❌ Se encontraron {$inconsistencies} partidos con incoherencias de {$limit} verificados.");
            
            if ($fix) {
                $this->info("✅ Se corrigieron {$fixed} partidos.");
                $this->info('🔄 Actualizando estadísticas...');
                $this->call('statistics:update');
            } else {
                $this->info('Ejecuta con --fix para corregir las incoherencias.');
            }
        }
        
        return 0;
    }
    
    private function checkMatchInconsistencies($match, $prediction): array 
    {
        $issues = [];
        
        // 1. Verificar resultado principal
        $actualOutcome = $this->getActualOutcome($match);
        if ($prediction->predicted_outcome !== $actualOutcome) {
            $issues[] = "Outcome: Predicción '{$prediction->predicted_outcome}' vs Real '{$actualOutcome}' - ❌";
        }
        
        // 2. Verificar Both Teams Score
        $bothScored = $match->home_goals > 0 && $match->away_goals > 0;
        $predictedBothScore = $prediction->both_teams_score_probability > 0.5;
        if ($bothScored !== $predictedBothScore) {
            $bothScoredText = $bothScored ? 'SÍ' : 'NO';
            $predictedText = $predictedBothScore ? 'SÍ' : 'NO';
            $issues[] = "Both Score: Predicción '{$predictedText}' vs Real '{$bothScoredText}' - ❌";
        }
        
        // 3. Verificar Over/Under 2.5
        $totalGoals = $match->home_goals + $match->away_goals;
        $actualOver25 = $totalGoals > 2.5;
        $predictedOver25 = $prediction->over_2_5_probability > 0.5;
        if ($actualOver25 !== $predictedOver25) {
            $actualText = $actualOver25 ? 'Over' : 'Under';
            $predictedText = $predictedOver25 ? 'Over' : 'Under';
            $issues[] = "Over/Under 2.5: Predicción '{$predictedText}' vs Real '{$actualText}' ({$totalGoals} goles) - ❌";
        }
        
        // 4. Verificar campos de corrección en la predicción
        if ($prediction->is_correct !== ($prediction->predicted_outcome === $actualOutcome)) {
            $issues[] = "Campo is_correct inconsistente";
        }
        
        if (!is_null($prediction->both_teams_score_correct)) {
            if ($prediction->both_teams_score_correct !== ($bothScored === $predictedBothScore)) {
                $issues[] = "Campo both_teams_score_correct inconsistente";
            }
        }
        
        if (!is_null($prediction->over_under_correct)) {
            if ($prediction->over_under_correct !== ($actualOver25 === $predictedOver25)) {
                $issues[] = "Campo over_under_correct inconsistente";
            }
        }
        
        return $issues;
    }
    
    private function getActualOutcome($match): string
    {
        if ($match->home_goals > $match->away_goals) {
            return 'home_win';
        } elseif ($match->home_goals < $match->away_goals) {
            return 'away_win';
        } else {
            return 'draw';
        }
    }
    
    private function fixMatchPrediction($match, $prediction): void
    {
        $actualOutcome = $this->getActualOutcome($match);
        
        // Corregir resultado principal
        $prediction->is_correct = ($prediction->predicted_outcome === $actualOutcome);
        
        // Corregir Both Teams Score
        $bothScored = $match->home_goals > 0 && $match->away_goals > 0;
        $predictedBothScore = $prediction->both_teams_score_probability > 0.5;
        $prediction->both_teams_score_correct = ($bothScored === $predictedBothScore);
        
        // Corregir Over/Under 2.5
        $totalGoals = $match->home_goals + $match->away_goals;
        $actualOver25 = $totalGoals > 2.5;
        $predictedOver25 = $prediction->over_2_5_probability > 0.5;
        $prediction->over_under_correct = ($actualOver25 === $predictedOver25);
        
        // Corregir First Half Over 0.5 si tenemos datos
        if (!is_null($match->home_goals_first_half) && !is_null($match->away_goals_first_half)) {
            $firstHalfGoals = $match->home_goals_first_half + $match->away_goals_first_half;
            $actualOver05FH = $firstHalfGoals > 0.5;
            $predictedOver05FH = $prediction->first_half_over_0_5_probability > 0.5;
            $prediction->first_half_over_0_5_correct = ($actualOver05FH === $predictedOver05FH);
        }
        
        $prediction->save();
    }
}