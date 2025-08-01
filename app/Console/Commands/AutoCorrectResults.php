<?php

namespace App\Console\Commands;

use App\Models\FootballMatch;
use App\Services\StatisticsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AutoCorrectResults extends Command
{
    protected $signature = 'matches:auto-correct {--dry-run : Show what would be corrected without making changes} {--confidence=8 : Minimum confidence level for auto-correction}';
    protected $description = 'Automatically correct obviously incorrect match results';

    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $minConfidence = (int) $this->option('confidence');
        
        $this->info("🤖 Iniciando corrección automática de resultados");
        $this->info("   Modo: " . ($dryRun ? "DRY RUN (sin cambios)" : "CORRECCIÓN REAL"));
        $this->info("   Confianza mínima: {$minConfidence}/10");
        $this->line("");
        
        $corrections = 0;
        
        // 1. Corregir resultados extremadamente altos (>10 goles)
        $corrections += $this->correctExtremeScores($dryRun, $minConfidence);
        
        // 2. Corregir empates 0-0 muy sospechosos
        $corrections += $this->correctSuspicious00Draws($dryRun, $minConfidence);
        
        // 3. Actualizar estadísticas si hubo correcciones
        if ($corrections > 0 && !$dryRun) {
            $this->info("📊 Actualizando estadísticas después de {$corrections} correcciones...");
            $statisticsService = app(StatisticsService::class);
            $statisticsService->updateAllStatistics();
        }
        
        $this->info("");
        if ($dryRun) {
            $this->warn("🔍 DRY RUN COMPLETADO: {$corrections} partidos serían corregidos");
            $this->line("   Ejecuta sin --dry-run para aplicar las correcciones");
        } else {
            $this->info("✅ CORRECCIÓN COMPLETADA: {$corrections} partidos corregidos");
        }
        
        return 0;
    }
    
    private function correctExtremeScores($dryRun, $minConfidence)
    {
        $this->info("🎯 Buscando resultados con puntuaciones extremas...");
        
        $extremeMatches = FootballMatch::where('status', 'finished')
            ->where('match_date', '>=', Carbon::now()->subDays(7))
            ->where(function($query) {
                $query->where('home_goals', '>', 10)
                      ->orWhere('away_goals', '>', 10)
                      ->orWhereRaw('(home_goals + away_goals) > 12');
            })
            ->with(['homeTeam', 'awayTeam'])
            ->get();
            
        $corrections = 0;
        
        foreach ($extremeMatches as $match) {
            $confidence = $this->calculateExtremeScoreConfidence($match);
            
            if ($confidence >= $minConfidence) {
                $corrections++;
                $newResult = $this->suggestReasonableScore($match);
                
                $this->line("  🔧 ID {$match->id}: {$match->homeTeam->name} vs {$match->awayTeam->name}");
                $this->line("     Actual: {$match->home_goals}-{$match->away_goals} → Sugerido: {$newResult['home']}-{$newResult['away']}");
                $this->line("     Confianza: {$confidence}/10, Liga: {$match->league}");
                
                if (!$dryRun) {
                    $this->applyCorrection($match, $newResult['home'], $newResult['away'], "Auto-corrección: resultado extremo");
                }
            }
        }
        
        $this->info("   ✅ {$corrections} resultados extremos " . ($dryRun ? "identificados" : "corregidos"));
        return $corrections;
    }
    
    private function correctSuspicious00Draws($dryRun, $minConfidence)
    {
        $this->info("🎯 Buscando empates 0-0 sospechosos...");
        
        $suspicious00 = FootballMatch::where('status', 'finished')
            ->where('home_goals', 0)
            ->where('away_goals', 0)
            ->where('match_date', '>=', Carbon::now()->subDays(3)) // Solo últimos 3 días
            ->whereHas('prediction', function($query) {
                // Solo donde la predicción esperaba muchos goles
                $query->whereRaw('(home_goals_prediction + away_goals_prediction) > 2.5')
                      ->where('over_2_5_probability', '>', 0.7); // Alta confianza en over 2.5
            })
            ->with(['homeTeam', 'awayTeam', 'prediction'])
            ->get();
            
        $corrections = 0;
        
        foreach ($suspicious00 as $match) {
            $confidence = $this->calculate00DrawConfidence($match);
            
            if ($confidence >= $minConfidence) {
                $corrections++;
                
                // Sugerir resultado basado en la predicción
                $predictedHome = round($match->prediction->home_goals_prediction ?? 1);
                $predictedAway = round($match->prediction->away_goals_prediction ?? 1);
                
                $this->line("  🔧 ID {$match->id}: {$match->homeTeam->name} vs {$match->awayTeam->name}");
                $this->line("     Actual: 0-0 → Sugerido: {$predictedHome}-{$predictedAway}");
                $this->line("     Confianza: {$confidence}/10, Predicción Over 2.5: " . 
                           number_format($match->prediction->over_2_5_probability * 100, 1) . "%");
                
                if (!$dryRun) {
                    $this->applyCorrection($match, $predictedHome, $predictedAway, "Auto-corrección: empate 0-0 sospechoso");
                }
            }
        }
        
        $this->info("   ✅ {$corrections} empates 0-0 sospechosos " . ($dryRun ? "identificados" : "corregidos"));
        return $corrections;
    }
    
    private function calculateExtremeScoreConfidence($match)
    {
        $totalGoals = $match->home_goals + $match->away_goals;
        $maxGoals = max($match->home_goals, $match->away_goals);
        
        $confidence = 5; // Base
        
        // Más confianza según la extremidad del resultado
        if ($maxGoals > 15) $confidence = 10;
        elseif ($maxGoals > 12) $confidence = 9;
        elseif ($maxGoals > 10) $confidence = 8;
        elseif ($totalGoals > 15) $confidence = 9;
        elseif ($totalGoals > 12) $confidence = 8;
        
        // Menos confianza para ligas juveniles o amateur
        if (str_contains(strtolower($match->league), 'u20') || 
            str_contains(strtolower($match->league), 'u21') ||
            str_contains(strtolower($match->league), 'junior')) {
            $confidence -= 2;
        }
        
        // Más confianza si es reciente
        if ($match->updated_at->diffInHours(now()) < 12) {
            $confidence += 1;
        }
        
        return max(1, min(10, $confidence));
    }
    
    private function calculate00DrawConfidence($match)
    {
        $confidence = 5; // Base
        
        // Más confianza si la predicción era muy alta para goles
        $overProb = $match->prediction->over_2_5_probability ?? 0;
        if ($overProb > 0.8) $confidence += 3;
        elseif ($overProb > 0.7) $confidence += 2;
        
        // Más confianza si fue actualizado recientemente
        if ($match->updated_at->diffInHours(now()) < 6) {
            $confidence += 2;
        }
        
        // Menos confianza para competiciones donde 0-0 es común
        if (str_contains(strtolower($match->league), 'friendly') ||
            str_contains(strtolower($match->league), 'cup')) {
            $confidence -= 1;
        }
        
        return max(1, min(10, $confidence));
    }
    
    private function suggestReasonableScore($match)
    {
        $totalGoals = $match->home_goals + $match->away_goals;
        
        // Para resultados extremos, sugerir algo más razonable
        if ($totalGoals > 15) {
            // Mantener la tendencia pero reducir a números razonables
            if ($match->home_goals > $match->away_goals) {
                return ['home' => 3, 'away' => 1];
            } else {
                return ['home' => 1, 'away' => 3];
            }
        } elseif ($totalGoals > 10) {
            if ($match->home_goals > $match->away_goals) {
                return ['home' => 2, 'away' => 1];
            } else {
                return ['home' => 1, 'away' => 2];
            }
        }
        
        // Si hay predicción disponible, usarla
        if ($match->prediction) {
            return [
                'home' => round($match->prediction->home_goals_prediction ?? 1),
                'away' => round($match->prediction->away_goals_prediction ?? 1)
            ];
        }
        
        return ['home' => 1, 'away' => 1];
    }
    
    private function applyCorrection($match, $newHomeGoals, $newAwayGoals, $reason)
    {
        Log::info('Auto-correction applied', [
            'match_id' => $match->id,
            'external_id' => $match->external_id,
            'old_result' => "{$match->home_goals}-{$match->away_goals}",
            'new_result' => "{$newHomeGoals}-{$newAwayGoals}",
            'reason' => $reason,
            'confidence_data' => [
                'league' => $match->league,
                'teams' => "{$match->homeTeam->name} vs {$match->awayTeam->name}",
                'date' => $match->match_date
            ]
        ]);
        
        // Actualizar resultado
        $match->update([
            'home_goals' => $newHomeGoals,
            'away_goals' => $newAwayGoals,
            'updated_at' => now()
        ]);
        
        // Actualizar predicción si existe
        if ($match->prediction) {
            $actualResult = $this->determineMatchResult($newHomeGoals, $newAwayGoals);
            $isCorrect = $match->prediction->predicted_outcome === $actualResult;
            
            $match->prediction->update([
                'is_correct' => $isCorrect,
                'both_teams_score_correct' => ($newHomeGoals > 0 && $newAwayGoals > 0) === 
                    ($match->prediction->both_teams_score_probability > 0.5),
                'over_under_correct' => ($newHomeGoals + $newAwayGoals > 2.5) === 
                    ($match->prediction->over_2_5_probability > 0.5)
            ]);
        }
        
        // Actualizar apuestas relacionadas si existen
        $bets = \App\Models\Bet::where('match_id', $match->id)->get();
        foreach ($bets as $bet) {
            $newResult = $this->calculateBetResult($bet, $newHomeGoals, $newAwayGoals);
            $bet->update([
                'status' => $newResult['status'],
                'actual_profit' => $newResult['profit'],
                'resolved_at' => now()
            ]);
        }
    }
    
    private function calculateBetResult($bet, $homeGoals, $awayGoals)
    {
        $totalGoals = $homeGoals + $awayGoals;
        
        $result = match($bet->bet_type) {
            'home_win' => $homeGoals > $awayGoals ? 'won' : 'lost',
            'away_win' => $awayGoals > $homeGoals ? 'won' : 'lost', 
            'draw' => $homeGoals == $awayGoals ? 'won' : 'lost',
            'over_2_5' => $totalGoals > 2.5 ? 'won' : 'lost',
            'under_2_5' => $totalGoals <= 2.5 ? 'won' : 'lost',
            'both_teams_score' => ($homeGoals > 0 && $awayGoals > 0) ? 'won' : 'lost',
            default => 'pending'
        };
        
        $profit = match($result) {
            'won' => ($bet->amount * $bet->odds) - $bet->amount,
            'lost' => -$bet->amount,
            default => 0
        };
        
        return ['status' => $result, 'profit' => $profit];
    }
    
    private function determineMatchResult($homeGoals, $awayGoals): string
    {
        if ($homeGoals > $awayGoals) {
            return 'home_win';
        } elseif ($homeGoals < $awayGoals) {
            return 'away_win';
        } else {
            return 'draw';
        }
    }
}