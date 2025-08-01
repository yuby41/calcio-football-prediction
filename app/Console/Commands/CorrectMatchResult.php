<?php

namespace App\Console\Commands;

use App\Models\FootballMatch;
use App\Models\Bet;
use App\Services\StatisticsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CorrectMatchResult extends Command
{
    protected $signature = 'matches:correct-result {match_id} {home_goals} {away_goals} {--confirm : Confirm the correction}';
    protected $description = 'Correct a specific match result and update related statistics';

    public function handle()
    {
        $matchId = $this->argument('match_id');
        $newHomeGoals = (int) $this->argument('home_goals');
        $newAwayGoals = (int) $this->argument('away_goals');
        
        $match = FootballMatch::with(['homeTeam', 'awayTeam', 'prediction'])->find($matchId);
        
        if (!$match) {
            $this->error("❌ Partido con ID {$matchId} no encontrado");
            return 1;
        }
        
        $this->info("🔍 PARTIDO ENCONTRADO:");
        $this->line("  ID: {$match->id}");
        $this->line("  Partido: {$match->homeTeam->name} vs {$match->awayTeam->name}");
        $this->line("  Fecha: {$match->match_date}");
        $this->line("  Liga: {$match->league}");
        $this->line("  Status: {$match->status}");
        $this->line("  Resultado actual: {$match->home_goals}-{$match->away_goals}");
        $this->line("  External ID: {$match->external_id}");
        
        $this->warn("🔄 NUEVO RESULTADO PROPUESTO: {$newHomeGoals}-{$newAwayGoals}");
        
        // Verificar apuestas afectadas
        $affectedBets = Bet::where('match_id', $matchId)->get();
        if ($affectedBets->count() > 0) {
            $this->warn("⚠️  APUESTAS AFECTADAS: {$affectedBets->count()}");
            
            foreach ($affectedBets as $bet) {
                $oldResult = $this->calculateBetResult($bet, $match->home_goals, $match->away_goals);
                $newResult = $this->calculateBetResult($bet, $newHomeGoals, $newAwayGoals);
                
                $this->line("  • Bet ID {$bet->id} ({$bet->bet_type}): {$oldResult['status']} → {$newResult['status']}");
            }
        }
        
        if (!$this->option('confirm')) {
            $this->warn("❗ Usa --confirm para aplicar la corrección");
            return 0;
        }
        
        // Confirmar con el usuario
        if (!$this->confirm("¿Confirmas que quieres corregir el resultado?")) {
            $this->info("❌ Operación cancelada");
            return 0;
        }
        
        $this->applyCorrection($match, $newHomeGoals, $newAwayGoals, $affectedBets);
        
        return 0;
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
            'over_0_5_first_half' => 'pending', // No tenemos datos de primer tiempo
            default => 'pending'
        };
        
        $profit = match($result) {
            'won' => ($bet->amount * $bet->odds) - $bet->amount,
            'lost' => -$bet->amount,
            default => 0
        };
        
        return ['status' => $result, 'profit' => $profit];
    }
    
    private function applyCorrection($match, $newHomeGoals, $newAwayGoals, $affectedBets)
    {
        $this->info("🔧 APLICANDO CORRECCIÓN...");
        
        // Log de la corrección
        Log::info('Manual match result correction', [
            'match_id' => $match->id,
            'external_id' => $match->external_id,
            'old_result' => "{$match->home_goals}-{$match->away_goals}",
            'new_result' => "{$newHomeGoals}-{$newAwayGoals}",
            'match_details' => [
                'teams' => "{$match->homeTeam->name} vs {$match->awayTeam->name}",
                'date' => $match->match_date,
                'league' => $match->league
            ],
            'affected_bets' => $affectedBets->count()
        ]);
        
        // Actualizar resultado del partido
        $match->update([
            'home_goals' => $newHomeGoals,
            'away_goals' => $newAwayGoals,
            'updated_at' => now()
        ]);
        
        $this->info("✅ Resultado del partido actualizado");
        
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
            
            $this->info("✅ Predicción actualizada: " . ($isCorrect ? "CORRECTA" : "INCORRECTA"));
        }
        
        // Actualizar apuestas afectadas
        foreach ($affectedBets as $bet) {
            $newResult = $this->calculateBetResult($bet, $newHomeGoals, $newAwayGoals);
            
            $bet->update([
                'status' => $newResult['status'],
                'actual_profit' => $newResult['profit'],
                'resolved_at' => now()
            ]);
            
            $this->line("  ✅ Bet ID {$bet->id}: {$newResult['status']} (€{$newResult['profit']})");
        }
        
        // Actualizar estadísticas
        $this->info("📊 Recalculando estadísticas...");
        $statisticsService = app(StatisticsService::class);
        $statisticsService->updateAllStatistics();
        
        $this->info("🎉 CORRECCIÓN COMPLETADA EXITOSAMENTE");
        $this->line("  • Partido actualizado: {$newHomeGoals}-{$newAwayGoals}");
        $this->line("  • Apuestas recalculadas: {$affectedBets->count()}");
        $this->line("  • Estadísticas actualizadas");
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
