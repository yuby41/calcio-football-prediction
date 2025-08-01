<?php

namespace App\Console\Commands;

use App\Models\FootballMatch;
use Illuminate\Console\Command;
use Carbon\Carbon;

class DetectSuspiciousResults extends Command
{
    protected $signature = 'matches:detect-suspicious {--days=7 : Number of days to check} {--auto-report : Generate automatic report}';
    protected $description = 'Detect potentially incorrect match results based on patterns';

    public function handle()
    {
        $days = (int) $this->option('days');
        $autoReport = $this->option('auto-report');
        
        $this->info("🔍 Analizando partidos de los últimos {$days} días...");
        
        $suspicious = [];
        
        // 1. Partidos finalizados hace poco pero que terminaron 0-0
        $suspicious['recent_00'] = $this->detectRecent00Draws($days);
        
        // 2. Partidos con muchos goles que pueden ser incorrectos
        $suspicious['high_scoring'] = $this->detectHighScoringMatches($days);
        
        // 3. Partidos sin goles en ligas con alta media de goles
        $suspicious['no_goals_in_high_scoring_leagues'] = $this->detectNoGoalsInHighScoringLeagues($days);
        
        // 4. Partidos donde la predicción era muy confiable pero falló
        $suspicious['confident_prediction_failures'] = $this->detectConfidentPredictionFailures($days);
        
        $this->displayResults($suspicious, $autoReport);
        
        return 0;
    }
    
    private function detectRecent00Draws($days)
    {
        $this->info("📊 Buscando empates 0-0 recientes...");
        
        return FootballMatch::with(['homeTeam', 'awayTeam', 'prediction'])
            ->where('status', 'finished')
            ->where('home_goals', 0)
            ->where('away_goals', 0)
            ->where('match_date', '>=', Carbon::now()->subDays($days))
            ->orderBy('match_date', 'desc')
            ->get()
            ->map(function($match) {
                return [
                    'match' => $match,
                    'suspicion_level' => $this->calculateSuspicionLevel($match, 'recent_00'),
                    'reason' => 'Empate 0-0 reciente - posible resultado no actualizado'
                ];
            })
            ->filter(function($item) {
                return $item['suspicion_level'] > 3; // Solo mostrar los más sospechosos
            });
    }
    
    private function detectHighScoringMatches($days)
    {
        $this->info("📊 Buscando partidos con puntuaciones altas...");
        
        return FootballMatch::with(['homeTeam', 'awayTeam'])
            ->where('status', 'finished')
            ->where('match_date', '>=', Carbon::now()->subDays($days))
            ->where(function($query) {
                $query->where('home_goals', '>', 8)
                      ->orWhere('away_goals', '>', 8)
                      ->orWhereRaw('(home_goals + away_goals) > 12');
            })
            ->get()
            ->map(function($match) {
                return [
                    'match' => $match,
                    'suspicion_level' => $this->calculateSuspicionLevel($match, 'high_scoring'),
                    'reason' => 'Resultado con puntuación muy alta - posible error de datos'
                ];
            });
    }
    
    private function detectNoGoalsInHighScoringLeagues($days)
    {
        $this->info("📊 Buscando partidos sin goles en ligas con alta media...");
        
        // Calcular media de goles por liga
        $leagueAverages = FootballMatch::where('status', 'finished')
            ->where('match_date', '>=', Carbon::now()->subDays(30)) // Últimos 30 días para media
            ->whereNotNull('home_goals')
            ->whereNotNull('away_goals')
            ->selectRaw('league, AVG(home_goals + away_goals) as avg_goals, COUNT(*) as matches_count')
            ->groupBy('league')
            ->having('matches_count', '>=', 10) // Al menos 10 partidos
            ->having('avg_goals', '>', 2.5) // Media alta de goles
            ->pluck('avg_goals', 'league');
            
        return FootballMatch::with(['homeTeam', 'awayTeam'])
            ->where('status', 'finished')
            ->where('home_goals', 0)
            ->where('away_goals', 0)
            ->where('match_date', '>=', Carbon::now()->subDays($days))
            ->whereIn('league', $leagueAverages->keys())
            ->get()
            ->map(function($match) use ($leagueAverages) {
                return [
                    'match' => $match,
                    'suspicion_level' => min(8, round($leagueAverages[$match->league])), // Max 8
                    'reason' => "Sin goles en liga con media de " . number_format($leagueAverages[$match->league], 1) . " goles/partido",
                    'league_average' => $leagueAverages[$match->league]
                ];
            })
            ->filter(function($item) {
                return $item['suspicion_level'] > 2.8;
            });
    }
    
    private function detectConfidentPredictionFailures($days)
    {
        $this->info("📊 Buscando fallos en predicciones muy confiables...");
        
        return FootballMatch::with(['homeTeam', 'awayTeam', 'prediction'])
            ->where('status', 'finished')
            ->where('match_date', '>=', Carbon::now()->subDays($days))
            ->whereHas('prediction', function($query) {
                $query->where(function($q) {
                    $q->where('home_win_probability', '>', 0.80) // Muy confiable para local
                      ->orWhere('away_win_probability', '>', 0.80) // Muy confiable para visitante
                      ->orWhere('over_2_5_probability', '>', 0.85); // Muy confiable para over 2.5
                })
                ->where('is_correct', false); // Pero falló
            })
            ->get()
            ->map(function($match) {
                $maxProb = max(
                    $match->prediction->home_win_probability ?? 0,
                    $match->prediction->away_win_probability ?? 0,
                    $match->prediction->over_2_5_probability ?? 0
                );
                
                return [
                    'match' => $match,
                    'suspicion_level' => round($maxProb * 10), // Convertir a escala 1-10
                    'reason' => "Predicción muy confiable (" . number_format($maxProb * 100, 1) . "%) pero falló - posible resultado incorrecto",
                    'max_confidence' => $maxProb
                ];
            })
            ->filter(function($item) {
                return $item['suspicion_level'] > 8; // Solo las más confiables que fallaron
            });
    }
    
    private function calculateSuspicionLevel($match, $type)
    {
        $level = 1;
        
        switch($type) {
            case 'recent_00':
                // Más sospechoso si fue actualizado recientemente
                $hoursAgo = $match->updated_at->diffInHours(now());
                if ($hoursAgo < 6) $level += 3;
                elseif ($hoursAgo < 24) $level += 2;
                else $level += 1;
                
                // Más sospechoso si tiene predicción de muchos goles
                if ($match->prediction && 
                    ($match->prediction->home_goals_prediction + $match->prediction->away_goals_prediction) > 2) {
                    $level += 2;
                }
                break;
                
            case 'high_scoring':
                $totalGoals = $match->home_goals + $match->away_goals;
                if ($totalGoals > 15) $level = 10; // Máximo nivel
                elseif ($totalGoals > 12) $level = 8;
                elseif ($totalGoals > 10) $level = 6;
                else $level = 4;
                break;
        }
        
        return $level;
    }
    
    private function displayResults($suspicious, $autoReport)
    {
        $totalSuspicious = collect($suspicious)->flatten(1)->count();
        
        if ($totalSuspicious === 0) {
            $this->info("✅ No se encontraron resultados sospechosos");
            return;
        }
        
        $this->warn("⚠️  Encontrados {$totalSuspicious} resultados potencialmente incorrectos:");
        $this->line("");
        
        foreach ($suspicious as $category => $matches) {
            if ($matches->count() > 0) {
                $this->info("📂 " . $this->getCategoryName($category) . " ({$matches->count()}):");
                
                foreach ($matches as $item) {
                    $match = $item['match'];
                    $level = $item['suspicion_level'];
                    $reason = $item['reason'];
                    
                    $this->line(sprintf(
                        "  🔍 ID %d [Nivel %d/10]: %s vs %s (%s)",
                        $match->id,
                        $level,
                        $match->homeTeam->name,
                        $match->awayTeam->name,
                        $match->home_goals . '-' . $match->away_goals
                    ));
                    
                    $this->line("     📅 {$match->match_date->format('d/m/Y H:i')} | 🏆 {$match->league}");
                    $this->line("     💡 {$reason}");
                    
                    if ($level >= 7) {
                        $this->error("     ❗ ALTA PRIORIDAD - Revisar manualmente");
                        $this->line("     💻 Corrección: php artisan matches:correct-result {$match->id} <home_goals> <away_goals> --confirm");
                    }
                    
                    $this->line("");
                }
            }
        }
        
        $this->info("💡 TIP: Usa 'php artisan matches:correct-result <match_id> <home> <away> --confirm' para corregir");
    }
    
    private function getCategoryName($category)
    {
        return match($category) {
            'recent_00' => 'Empates 0-0 Recientes',
            'high_scoring' => 'Puntuaciones Muy Altas',
            'no_goals_in_high_scoring_leagues' => 'Sin Goles en Ligas de Alta Puntuación',
            'confident_prediction_failures' => 'Fallos en Predicciones Muy Confiables',
            default => ucfirst($category)
        };
    }
}
