<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\FootballMatch;
use App\Models\MatchPrediction; 
use Illuminate\Support\Facades\DB;

class AuditMLTrainingData extends Command
{
    protected $signature = 'ml:audit-training-data
                           {--fix : Fix synthetic data with real data where possible}
                           {--show-samples : Show sample data for verification}';
                           
    protected $description = 'Comprehensive audit of ML training data to ensure no synthetic data is used';

    public function handle()
    {
        $this->info('🔍 AUDITANDO DATOS DE ENTRENAMIENTO DE IA');
        $this->info('==========================================');
        
        $fix = $this->option('fix');
        $showSamples = $this->option('show-samples');
        
        // 1. Check finished matches data quality
        $this->checkFinishedMatchesData($showSamples, $fix);
        
        // 2. Check team statistics data quality
        $this->checkTeamStatisticsData($showSamples);
        
        // 3. Check first-half data quality 
        $this->checkFirstHalfDataQuality($showSamples, $fix);
        
        // 4. Check prediction accuracy data
        $this->checkPredictionAccuracyData($showSamples);
        
        // 5. Check for data inconsistencies
        $this->checkDataConsistencies($fix);
        
        $this->info("\n✅ AUDITORÍA COMPLETA FINALIZADA");
        
        return 0;
    }
    
    private function checkFinishedMatchesData($showSamples, $fix)
    {
        $this->info("\n📊 1. VERIFICANDO DATOS DE PARTIDOS TERMINADOS");
        $this->info("================================================");
        
        // Count finished matches
        $finishedMatches = FootballMatch::where('status', 'finished')
            ->whereNotNull('home_goals')
            ->whereNotNull('away_goals')
            ->whereDate('match_date', '>=', now()->subYears(2))
            ->count();
            
        $this->info("Total partidos terminados (últimos 2 años): {$finishedMatches}");
        
        // Check for null/invalid data
        $invalidScores = FootballMatch::where('status', 'finished')
            ->where(function($q) {
                $q->whereNull('home_goals')
                  ->orWhereNull('away_goals')
                  ->orWhere('home_goals', '<', 0)
                  ->orWhere('away_goals', '<', 0);
            })
            ->whereDate('match_date', '>=', now()->subYears(2))
            ->count();
            
        if ($invalidScores > 0) {
            $this->error("❌ {$invalidScores} partidos con resultados inválidos/nulos");
        } else {
            $this->info("✅ Todos los partidos terminados tienen resultados válidos");
        }
        
        // Check for impossible scores
        $impossibleScores = FootballMatch::where('status', 'finished')
            ->where(function($q) {
                $q->where('home_goals', '>', 15)
                  ->orWhere('away_goals', '>', 15);
            })
            ->whereDate('match_date', '>=', now()->subYears(2))
            ->count();
            
        if ($impossibleScores > 0) {
            $this->warn("⚠️  {$impossibleScores} partidos con resultados sospechosamente altos (>15 goles)");
        }
        
        if ($showSamples) {
            $this->info("\n📋 Muestra de partidos recientes:");
            $samples = FootballMatch::where('status', 'finished')
                ->with(['homeTeam', 'awayTeam'])
                ->whereDate('match_date', '>=', now()->subDays(7))
                ->limit(5)
                ->get();
                
            foreach ($samples as $match) {
                $this->line("  {$match->homeTeam->name} {$match->home_goals}-{$match->away_goals} {$match->awayTeam->name}");
            }
        }
    }
    
    private function checkTeamStatisticsData($showSamples)
    {
        $this->info("\n📈 2. VERIFICANDO ESTADÍSTICAS DE EQUIPOS");
        $this->info("========================================");
        
        // Count team statistics
        $teamStats = DB::table('team_statistics')->count();
        $this->info("Total estadísticas de equipos: {$teamStats}");
        
        // Check for inconsistent data
        $inconsistentStats = DB::table('team_statistics')
            ->where(function($q) {
                $q->where('matches_played', '<=', 0)
                  ->orWhere('goals_for', '<', 0)
                  ->orWhere('goals_against', '<', 0)
                  ->orWhere('points', '<', 0)
                  ->orWhereRaw('wins + draws + losses != matches_played')
                  ->orWhereRaw('goals_for - goals_against != goals_difference');
            })
            ->count();
            
        if ($inconsistentStats > 0) {
            $this->error("❌ {$inconsistentStats} estadísticas de equipo inconsistentes");
        } else {
            $this->info("✅ Todas las estadísticas de equipo son consistentes");
        }
        
        // Verify statistics are calculated from real matches
        $sampleTeam = DB::table('team_statistics')
            ->join('teams', 'team_statistics.team_id', '=', 'teams.id')
            ->first();
            
        if ($sampleTeam) {
            $realMatches = FootballMatch::where('status', 'finished')
                ->whereYear('match_date', $sampleTeam->season)
                ->where(function($q) use ($sampleTeam) {
                    $q->where('home_team_id', $sampleTeam->team_id)
                      ->orWhere('away_team_id', $sampleTeam->team_id);
                })
                ->count();
                
            if ($realMatches == $sampleTeam->matches_played) {
                $this->info("✅ Estadísticas calculadas desde partidos reales verificado");
            } else {
                $this->warn("⚠️  Discrepancia en conteo de partidos para {$sampleTeam->name}");
            }
        }
        
        if ($showSamples) {
            $this->info("\n📋 Muestra de estadísticas de equipos:");
            $samples = DB::table('team_statistics')
                ->join('teams', 'team_statistics.team_id', '=', 'teams.id')
                ->select('teams.name', 'team_statistics.matches_played', 'team_statistics.goals_for', 
                        'team_statistics.goals_against', 'team_statistics.points')
                ->limit(3)
                ->get();
                
            foreach ($samples as $stat) {
                $this->line("  {$stat->name}: {$stat->matches_played} partidos, {$stat->goals_for} GF, {$stat->goals_against} GA, {$stat->points} pts");
            }
        }
    }
    
    private function checkFirstHalfDataQuality($showSamples, $fix)
    {
        $this->info("\n⚽ 3. VERIFICANDO DATOS DE PRIMER TIEMPO");
        $this->info("=======================================");
        
        $recentFinished = FootballMatch::where('status', 'finished')
            ->whereDate('match_date', '>=', now()->subDays(7))
            ->count();
            
        $withFirstHalfData = FootballMatch::where('status', 'finished')
            ->whereDate('match_date', '>=', now()->subDays(7))
            ->whereNotNull('home_goals_first_half')
            ->whereNotNull('away_goals_first_half')
            ->count();
            
        $coverage = $recentFinished > 0 ? round(($withFirstHalfData / $recentFinished) * 100, 1) : 0;
        $this->info("Cobertura de datos de primer tiempo (últimos 7 días): {$coverage}% ({$withFirstHalfData}/{$recentFinished})");
        
        // Check for impossible first-half data
        $impossibleFirstHalf = FootballMatch::where('status', 'finished')
            ->whereNotNull('home_goals_first_half')
            ->whereNotNull('away_goals_first_half')
            ->whereRaw('(home_goals_first_half + away_goals_first_half) > (home_goals + away_goals)')
            ->whereDate('match_date', '>=', now()->subDays(30))
            ->count();
            
        if ($impossibleFirstHalf > 0) {
            $this->error("❌ {$impossibleFirstHalf} partidos con datos de primer tiempo impossibles (1T > FT)");
            
            if ($fix) {
                $this->warn("🔧 Ejecutando corrección de datos de primer tiempo...");
                $this->call('matches:fix-first-half');
            }
        } else {
            $this->info("✅ Todos los datos de primer tiempo son lógicamente consistentes");
        }
        
        // Check for synthetic data patterns
        $syntheticPattern = FootballMatch::where('status', 'finished')
            ->whereNotNull('home_goals_first_half')
            ->whereNotNull('away_goals_first_half')
            ->whereDate('match_date', '>=', now()->subDays(3))
            ->whereRaw('ABS((home_goals_first_half + away_goals_first_half) / (home_goals + away_goals + 0.1) - 0.45) < 0.05')
            ->count();
            
        if ($syntheticPattern > 10) {
            $this->warn("⚠️  {$syntheticPattern} partidos con patrones de datos sintéticos detectados");
            
            if ($fix) {
                $this->warn("🔧 Verificando y corrigiendo con datos reales de API...");
                $this->call('matches:verify-real-first-half', ['--fix' => true, '--limit' => 100]);
            }
        }
        
        if ($showSamples) {
            $this->info("\n📋 Muestra de datos de primer tiempo:");
            $samples = FootballMatch::where('status', 'finished')
                ->with(['homeTeam', 'awayTeam'])
                ->whereNotNull('home_goals_first_half')
                ->whereNotNull('away_goals_first_half')
                ->whereDate('match_date', '>=', now()->subDays(3))
                ->limit(5)
                ->get();
                
            foreach ($samples as $match) {
                $total1T = $match->home_goals_first_half + $match->away_goals_first_half;
                $totalFT = $match->home_goals + $match->away_goals;
                $ratio = $totalFT > 0 ? round(($total1T / $totalFT) * 100) : 0;
                $this->line("  {$match->homeTeam->name} vs {$match->awayTeam->name}: 1T {$match->home_goals_first_half}-{$match->away_goals_first_half}, FT {$match->home_goals}-{$match->away_goals} ({$ratio}%)");
            }
        }
    }
    
    private function checkPredictionAccuracyData($showSamples)
    {
        $this->info("\n🤖 4. VERIFICANDO PRECISIÓN DE PREDICCIONES");
        $this->info("===========================================");
        
        $totalPredictions = MatchPrediction::count();
        $this->info("Total predicciones: {$totalPredictions}");
        
        $predictionsWithResults = MatchPrediction::whereHas('match', function($q) {
            $q->where('status', 'finished')
              ->whereNotNull('home_goals')
              ->whereNotNull('away_goals');
        })->count();
        
        $this->info("Predicciones con resultados: {$predictionsWithResults}");
        
        // Check accuracy calculation consistency
        $accuracyMismatches = MatchPrediction::whereHas('match', function($q) {
            $q->where('status', 'finished');
        })
        ->whereNotNull('is_correct')
        ->where(function($q) {
            // Check for basic prediction accuracy consistency
            $q->where('predicted_outcome', 'home_win')
              ->whereRaw('is_correct = 0 AND (SELECT home_goals > away_goals FROM matches WHERE matches.id = match_predictions.match_id)')
              ->orWhere(function($q) {
                  $q->where('predicted_outcome', 'away_win')
                    ->whereRaw('is_correct = 0 AND (SELECT away_goals > home_goals FROM matches WHERE matches.id = match_predictions.match_id)');
              })
              ->orWhere(function($q) {
                  $q->where('predicted_outcome', 'draw')
                    ->whereRaw('is_correct = 0 AND (SELECT home_goals = away_goals FROM matches WHERE matches.id = match_predictions.match_id)');
              });
        })
        ->count();
        
        if ($accuracyMismatches > 0) {
            $this->warn("⚠️  {$accuracyMismatches} predicciones con cálculos de precisión inconsistentes");
        } else {
            $this->info("✅ Cálculos de precisión de predicciones consistentes");
        }
        
        if ($showSamples && $predictionsWithResults > 0) {
            $this->info("\n📋 Muestra de precisión de predicciones:");
            $samples = MatchPrediction::with(['match.homeTeam', 'match.awayTeam'])
                ->whereHas('match', function($q) {
                    $q->where('status', 'finished')
                      ->whereDate('match_date', '>=', now()->subDays(7));
                })
                ->whereNotNull('is_correct')
                ->limit(3)
                ->get();
                
            foreach ($samples as $pred) {
                $match = $pred->match;
                $predicted = $pred->home_win_probability > $pred->away_win_probability && $pred->home_win_probability > $pred->draw_probability ? 'H' :
                           ($pred->away_win_probability > $pred->draw_probability ? 'A' : 'D');
                $actual = $match->home_goals > $match->away_goals ? 'H' :
                         ($match->away_goals > $match->home_goals ? 'A' : 'D');
                $correct = $predicted === $actual ? '✅' : '❌';
                
                $this->line("  {$match->homeTeam->name} vs {$match->awayTeam->name}: Pred {$predicted}, Real {$actual} {$correct}");
            }
        }
    }
    
    private function checkDataConsistencies($fix)
    {
        $this->info("\n🔍 5. VERIFICANDO CONSISTENCIAS GENERALES");
        $this->info("========================================");
        
        // Check for matches without teams
        $orphanMatches = FootballMatch::whereNull('home_team_id')
            ->orWhereNull('away_team_id')
            ->count();
            
        if ($orphanMatches > 0) {
            $this->error("❌ {$orphanMatches} partidos sin equipos asignados");
        } else {
            $this->info("✅ Todos los partidos tienen equipos asignados");
        }
        
        // Check for duplicate matches
        $duplicateMatches = DB::select("
            SELECT COUNT(*) as count FROM (
                SELECT home_team_id, away_team_id, COUNT(*) as cnt
                FROM matches 
                WHERE status = 'finished'
                GROUP BY home_team_id, away_team_id, DATE(match_date)
                HAVING cnt > 1
            ) as duplicates
        ");
        
        $duplicateCount = $duplicateMatches[0]->count ?? 0;
        if ($duplicateCount > 0) {
            $this->warn("⚠️  {$duplicateCount} grupos de partidos duplicados detectados");
        } else {
            $this->info("✅ No hay partidos duplicados");
        }
        
        // Check for future matches marked as finished
        $futureFinished = FootballMatch::where('status', 'finished')
            ->where('match_date', '>', now())
            ->count();
            
        if ($futureFinished > 0) {
            $this->error("❌ {$futureFinished} partidos futuros marcados como terminados");
            
            if ($fix) {
                $this->warn("🔧 Corrigiendo partidos futuros marcados como terminados...");
                FootballMatch::where('status', 'finished')
                    ->where('match_date', '>', now())
                    ->update(['status' => 'scheduled']);
                $this->info("✅ Corregidos {$futureFinished} partidos");
            }
        } else {
            $this->info("✅ No hay partidos futuros marcados como terminados");
        }
        
        // Summary of data quality for ML training
        $this->info("\n📊 RESUMEN PARA ENTRENAMIENTO DE IA:");
        $this->info("====================================");
        
        $qualityMatches = FootballMatch::where('status', 'finished')
            ->whereNotNull('home_goals')
            ->whereNotNull('away_goals')
            ->where('home_goals', '>=', 0)
            ->where('away_goals', '>=', 0)
            ->where('home_goals', '<=', 10)
            ->where('away_goals', '<=', 10)
            ->whereDate('match_date', '>=', now()->subYears(2))
            ->count();
            
        $this->info("✅ Partidos de alta calidad para entrenamiento: {$qualityMatches}");
        
        $qualityPredictions = MatchPrediction::whereHas('match', function($q) {
            $q->where('status', 'finished')
              ->whereNotNull('home_goals')
              ->whereNotNull('away_goals')
              ->whereDate('match_date', '>=', now()->subYears(2));
        })
        ->whereNotNull('is_correct')
        ->count();
        
        $this->info("✅ Predicciones verificadas para análisis: {$qualityPredictions}");
    }
}