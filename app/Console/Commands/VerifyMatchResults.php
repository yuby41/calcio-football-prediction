<?php

namespace App\Console\Commands;

use App\Models\FootballMatch;
use App\Models\Bet;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class VerifyMatchResults extends Command
{
    protected $signature = 'matches:verify-results {--fix : Fix incorrect results automatically}';
    protected $description = 'Verify match results integrity and detect anomalies';

    public function handle()
    {
        $this->info('🔍 Verificando integridad de resultados de partidos...');
        
        // 1. Buscar resultados anómalos
        $this->checkAnomalousResults();
        
        // 2. Verificar partidos finalizados sin resultados
        $this->checkMissingResults();
        
        // 3. Verificar consistencia de apuestas
        $this->checkBetConsistency();
        
        // 4. Verificar duplicados por external_id
        $this->checkDuplicateMatches();
        
        return 0;
    }
    
    private function checkAnomalousResults()
    {
        $this->info('📊 Buscando resultados anómalos...');
        
        // Resultados con más de 10 goles para un equipo
        $anomalous = FootballMatch::where('status', 'finished')
            ->where(function($query) {
                $query->where('home_goals', '>', 10)
                      ->orWhere('away_goals', '>', 10);
            })
            ->with(['homeTeam', 'awayTeam'])
            ->get();
            
        if ($anomalous->count() > 0) {
            $this->warn("⚠️  Encontrados {$anomalous->count()} partidos con resultados anómalos:");
            
            foreach ($anomalous as $match) {
                $this->line("  • ID {$match->id}: {$match->homeTeam->name} {$match->home_goals}-{$match->away_goals} {$match->awayTeam->name}");
                $this->line("    Liga: {$match->league}, External ID: {$match->external_id}");
                $this->line("    Fecha: {$match->match_date}");
                
                // Verificar apuestas afectadas
                $affectedBets = Bet::where('match_id', $match->id)->count();
                if ($affectedBets > 0) {
                    $this->error("    ❌ {$affectedBets} apuestas afectadas por este resultado");
                }
                
                if ($this->option('fix')) {
                    $this->fixAnomalousMatch($match);
                }
                
                $this->line('');
            }
        } else {
            $this->info('✅ No se encontraron resultados anómalos');
        }
    }
    
    private function checkMissingResults()
    {
        $this->info('🔍 Verificando partidos finalizados sin resultados...');
        
        $missing = FootballMatch::where('status', 'finished')
            ->where(function($query) {
                $query->whereNull('home_goals')
                      ->orWhereNull('away_goals');
            })
            ->count();
            
        if ($missing > 0) {
            $this->warn("⚠️  {$missing} partidos finalizados sin resultados completos");
            
            if ($this->option('fix')) {
                $this->info('🔧 Intentando sincronizar resultados desde API...');
                $this->call('football:update-today', ['--quiet' => true]);
            }
        } else {
            $this->info('✅ Todos los partidos finalizados tienen resultados');
        }
    }
    
    private function checkBetConsistency()
    {
        $this->info('🎯 Verificando consistencia de apuestas...');
        
        $inconsistencies = 0;
        $bets = Bet::with('match')->where('status', '!=', 'pending')->get();
        
        foreach ($bets as $bet) {
            if ($bet->match && $bet->match->status === 'finished') {
                $calculated = $bet->calculateResult();
                
                if ($calculated['status'] !== $bet->status) {
                    $inconsistencies++;
                    
                    if ($inconsistencies <= 5) { // Mostrar solo los primeros 5
                        $this->warn("  ❌ Bet ID {$bet->id}: Almacenado={$bet->status}, Calculado={$calculated['status']}");
                        $this->line("     Match: {$bet->match->home_goals}-{$bet->match->away_goals}, Tipo: {$bet->bet_type}");
                    }
                    
                    if ($this->option('fix')) {
                        $bet->update([
                            'status' => $calculated['status'],
                            'actual_profit' => $calculated['profit']
                        ]);
                    }
                }
            }
        }
        
        if ($inconsistencies > 0) {
            $this->warn("⚠️  {$inconsistencies} inconsistencias encontradas en apuestas");
            if ($this->option('fix')) {
                $this->info("✅ Inconsistencias corregidas");
            }
        } else {
            $this->info('✅ Todas las apuestas son consistentes');
        }
    }
    
    private function checkDuplicateMatches()
    {
        $this->info('🔄 Verificando partidos duplicados...');
        
        $duplicates = FootballMatch::selectRaw('external_id, COUNT(*) as count')
            ->whereNotNull('external_id')
            ->groupBy('external_id')
            ->having('count', '>', 1)
            ->get();
            
        if ($duplicates->count() > 0) {
            $this->warn("⚠️  {$duplicates->count()} external_ids duplicados encontrados");
            
            foreach ($duplicates->take(5) as $duplicate) {
                $matches = FootballMatch::where('external_id', $duplicate->external_id)->get();
                $this->line("  • External ID {$duplicate->external_id}: {$duplicate->count} partidos");
                
                foreach ($matches as $match) {
                    $this->line("    - ID {$match->id}: {$match->home_goals}-{$match->away_goals} ({$match->status})");
                }
            }
            
            if ($this->option('fix')) {
                $this->warn('⚠️  Corrección automática de duplicados no implementada por seguridad');
                $this->line('   Usa: php artisan matches:clean-duplicates');
            }
        } else {
            $this->info('✅ No hay partidos duplicados');
        }
    }
    
    private function fixAnomalousMatch($match)
    {
        $this->warn("🔧 Intentando corregir resultado anómalo para match ID {$match->id}...");
        
        // Log the anomaly
        Log::warning('Anomalous match result detected', [
            'match_id' => $match->id,
            'external_id' => $match->external_id,
            'result' => "{$match->home_goals}-{$match->away_goals}",
            'league' => $match->league
        ]);
        
        // En una implementación real, aquí se podría:
        // 1. Re-sincronizar desde la API
        // 2. Marcar como 'needs_review'
        // 3. Notificar al administrador
        
        $this->line("   ℹ️  Resultado registrado en logs para revisión manual");
    }
}