<?php

namespace App\Console\Commands;

use App\Models\FootballMatch;
use App\Models\Bet;
use App\Services\EnhancedFootballApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FixMatchResults extends Command
{
    protected $signature = 'matches:fix-results {--verify-anomalous : Re-verify anomalous results with API}';
    protected $description = 'Fix incorrect match results and update affected bets';

    private $apiService;

    public function __construct(EnhancedFootballApiService $apiService)
    {
        parent::__construct();
        $this->apiService = $apiService;
    }

    public function handle()
    {
        $this->info('🔧 Corrigiendo resultados incorrectos de partidos...');
        
        if ($this->option('verify-anomalous')) {
            $this->verifyAnomalousResults();
        }
        
        $this->fixBetConsistencies();
        $this->recalculateBudgets();
        
        $this->info('✅ Proceso de corrección completado');
        return 0;
    }
    
    private function verifyAnomalousResults()
    {
        $this->info('🔍 Re-verificando resultados anómalos con la API...');
        
        $anomalous = FootballMatch::where('status', 'finished')
            ->where(function($query) {
                $query->where('home_goals', '>', 10)
                      ->orWhere('away_goals', '>', 10);
            })
            ->get();
            
        foreach ($anomalous as $match) {
            $this->line("Verificando match ID {$match->id} (External: {$match->external_id})...");
            
            try {
                // Intentar obtener información actualizada de la API
                $apiData = $this->apiService->fetchFixtureById($match->external_id);
                
                if ($apiData && isset($apiData['goals'])) {
                    $apiHome = $apiData['goals']['home'] ?? null;
                    $apiAway = $apiData['goals']['away'] ?? null;
                    
                    if ($apiHome !== null && $apiAway !== null) {
                        if ($apiHome != $match->home_goals || $apiAway != $match->away_goals) {
                            $this->warn("  ❌ Resultado incorrecto detectado!");
                            $this->line("     BD: {$match->home_goals}-{$match->away_goals}");
                            $this->line("     API: {$apiHome}-{$apiAway}");
                            
                            // Actualizar resultado
                            $match->update([
                                'home_goals' => $apiHome,
                                'away_goals' => $apiAway
                            ]);
                            
                            $this->info("  ✅ Resultado corregido");
                            
                            // Actualizar apuestas afectadas
                            $this->updateAffectedBets($match);
                            
                        } else {
                            $this->info("  ✅ Resultado confirmado como correcto");
                        }
                    }
                }
                
                // Rate limiting
                sleep(1);
                
            } catch (\Exception $e) {
                $this->error("  ❌ Error verificando con API: {$e->getMessage()}");
                
                // Si el resultado es extremadamente anómalo (>15 goles), marcarlo para revisión
                if ($match->home_goals > 15 || $match->away_goals > 15) {
                    $this->markForManualReview($match);
                }
            }
        }
    }
    
    private function fixBetConsistencies()
    {
        $this->info('🎯 Corrigiendo inconsistencias en apuestas...');
        
        $fixed = 0;
        $bets = Bet::with('match')->where('status', '!=', 'pending')->get();
        
        foreach ($bets as $bet) {
            if ($bet->match && $bet->match->status === 'finished') {
                $calculated = $bet->calculateResult();
                
                if ($calculated['status'] !== 'pending' && $calculated['status'] !== $bet->status) {
                    $oldStatus = $bet->status;
                    $oldProfit = $bet->actual_profit;
                    
                    $bet->update([
                        'status' => $calculated['status'],
                        'actual_profit' => $calculated['profit'],
                        'resolved_at' => $bet->match->match_date
                    ]);
                    
                    $fixed++;
                    
                    Log::info('Bet result corrected', [
                        'bet_id' => $bet->id,
                        'match_id' => $bet->match_id,
                        'old_status' => $oldStatus,
                        'new_status' => $calculated['status'],
                        'old_profit' => $oldProfit,
                        'new_profit' => $calculated['profit']
                    ]);
                }
            }
        }
        
        $this->info("✅ {$fixed} apuestas corregidas");
    }
    
    private function updateAffectedBets($match)
    {
        $affectedBets = Bet::where('match_id', $match->id)->get();
        
        foreach ($affectedBets as $bet) {
            $newResult = $bet->calculateResult();
            
            if ($newResult['status'] !== 'pending') {
                $bet->update([
                    'status' => $newResult['status'],
                    'actual_profit' => $newResult['profit']
                ]);
                
                $this->line("    → Apuesta ID {$bet->id} actualizada: {$newResult['status']}");
            }
        }
    }
    
    private function markForManualReview($match)
    {
        Log::warning('Match marked for manual review due to extreme result', [
            'match_id' => $match->id,
            'external_id' => $match->external_id,
            'result' => "{$match->home_goals}-{$match->away_goals}",
            'league' => $match->league,
            'teams' => [
                'home' => $match->homeTeam->name ?? 'Unknown',
                'away' => $match->awayTeam->name ?? 'Unknown'
            ]
        ]);
        
        // Aquí se podría implementar:
        // - Enviar notificación por email
        // - Crear ticket en sistema de soporte
        // - Marcar en base de datos para revisión
        
        $this->warn("  ⚠️  Marcado para revisión manual");
    }
    
    private function recalculateBudgets()
    {
        $this->info('💰 Recalculando budgets afectados...');
        
        // Usar el comando de recálculo correcto
        $this->call('budget:recalculate');
    }
}