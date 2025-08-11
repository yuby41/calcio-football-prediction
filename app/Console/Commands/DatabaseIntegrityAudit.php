<?php

namespace App\Console\Commands;

use App\Models\FootballMatch;
use App\Models\MatchPrediction;
use App\Models\Bet;
use App\Models\BudgetHistory;
use App\Models\BudgetConfiguration;
use App\Models\Team;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DatabaseIntegrityAudit extends Command
{
    protected $signature = 'db:integrity-audit
                           {--fix : Automatically fix issues found}
                           {--deep : Perform deep integrity checks}
                           {--report : Generate detailed report}';
    
    protected $description = 'Comprehensive database integrity audit for inconsistencies and errors';

    private $issues = [];
    private $warnings = [];
    private $stats = [];

    public function handle()
    {
        $fix = $this->option('fix');
        $deep = $this->option('deep');
        $report = $this->option('report');
        
        $this->info('🔍 INICIANDO AUDITORÍA SISTEMÁTICA DE BASE DE DATOS');
        $this->info('================================================');
        
        // Core data integrity checks
        $this->checkMatchConsistency();
        $this->checkPredictionConsistency();
        $this->checkBetConsistency();
        $this->checkBudgetConsistency();
        $this->checkTeamConsistency();
        
        if ($deep) {
            $this->info("\n🔬 ANÁLISIS PROFUNDO...");
            $this->checkBusinessLogicConsistency();
            $this->checkDataQuality();
            $this->checkPerformanceMetrics();
        }
        
        // Summary and fixes
        $this->showSummary();
        
        if ($fix && !empty($this->issues)) {
            $this->info("\n🔧 APLICANDO CORRECCIONES...");
            $this->applyFixes();
        }
        
        if ($report) {
            $this->generateReport();
        }
        
        return empty($this->issues) ? 0 : 1;
    }
    
    private function checkMatchConsistency()
    {
        $this->info("\n📊 1. VERIFICANDO CONSISTENCIA DE PARTIDOS...");
        
        // Check for matches with invalid scores
        $invalidScores = FootballMatch::where('status', 'finished')
            ->where(function($q) {
                $q->whereNull('home_goals')
                  ->orWhereNull('away_goals')
                  ->orWhere('home_goals', '<', 0)
                  ->orWhere('away_goals', '<', 0);
            })
            ->count();
            
        if ($invalidScores > 0) {
            $this->issues[] = "❌ {$invalidScores} partidos terminados sin goles válidos";
        }
        
        // Check for matches with future dates but finished status
        $futureFinished = FootballMatch::where('status', 'finished')
            ->where('match_date', '>', now())
            ->count();
            
        if ($futureFinished > 0) {
            $this->issues[] = "❌ {$futureFinished} partidos 'terminados' con fechas futuras";
        }
        
        // Check for first-half data consistency
        $inconsistentFirstHalf = FootballMatch::where('status', 'finished')
            ->whereNotNull('home_goals_first_half')
            ->whereNotNull('away_goals_first_half')
            ->whereRaw('home_goals_first_half > home_goals OR away_goals_first_half > away_goals')
            ->count();
            
        if ($inconsistentFirstHalf > 0) {
            $this->issues[] = "❌ {$inconsistentFirstHalf} partidos con goles 1T > goles totales";
        }
        
        $this->stats['total_matches'] = FootballMatch::count();
        $this->stats['finished_matches'] = FootballMatch::where('status', 'finished')->count();
        
        $this->line("✅ Total partidos: {$this->stats['total_matches']}");
        $this->line("✅ Partidos terminados: {$this->stats['finished_matches']}");
    }
    
    private function checkPredictionConsistency()
    {
        $this->info("\n🤖 2. VERIFICANDO CONSISTENCIA DE PREDICCIONES...");
        
        // Check for predictions with invalid probabilities
        $invalidProbs = MatchPrediction::where(function($q) {
                $q->where('home_win_probability', '<', 0)
                  ->orWhere('home_win_probability', '>', 1)
                  ->orWhere('away_win_probability', '<', 0)
                  ->orWhere('away_win_probability', '>', 1)
                  ->orWhere('draw_probability', '<', 0)
                  ->orWhere('draw_probability', '>', 1);
            })
            ->count();
            
        if ($invalidProbs > 0) {
            $this->issues[] = "❌ {$invalidProbs} predicciones con probabilidades inválidas (0-1)";
        }
        
        // Check for predictions where probabilities don't sum to ~1
        $probabilitySumIssues = MatchPrediction::whereNotNull('home_win_probability')
            ->whereNotNull('away_win_probability')
            ->whereNotNull('draw_probability')
            ->whereRaw('ABS((home_win_probability + away_win_probability + draw_probability) - 1.0) > 0.1')
            ->count();
            
        if ($probabilitySumIssues > 0) {
            $this->warnings[] = "⚠️  {$probabilitySumIssues} predicciones con probabilidades que no suman ~1";
        }
        
        $this->stats['total_predictions'] = MatchPrediction::count();
        $this->stats['correct_predictions'] = MatchPrediction::where('is_correct', true)->count();
        
        $this->line("✅ Total predicciones: {$this->stats['total_predictions']}");
        $this->line("✅ Predicciones correctas: {$this->stats['correct_predictions']}");
    }
    
    private function checkBetConsistency()
    {
        $this->info("\n💰 3. VERIFICANDO CONSISTENCIA DE APUESTAS...");
        
        // Check for pending bets on finished matches
        $pendingFinished = Bet::join('matches', 'bets.match_id', '=', 'matches.id')
            ->where('bets.status', 'pending')
            ->where('matches.status', 'finished')
            ->whereNotNull('matches.home_goals')
            ->whereNotNull('matches.away_goals')
            ->count();
            
        if ($pendingFinished > 0) {
            $this->issues[] = "❌ {$pendingFinished} apuestas pendientes en partidos terminados";
        }
        
        // Check for bets without resolved_at but with won/lost status
        $unresolvedWinLoss = Bet::whereIn('status', ['won', 'lost'])
            ->whereNull('resolved_at')
            ->count();
            
        if ($unresolvedWinLoss > 0) {
            $this->issues[] = "❌ {$unresolvedWinLoss} apuestas won/lost sin resolved_at";
        }
        
        $this->stats['total_bets'] = Bet::count();
        $this->stats['won_bets'] = Bet::where('status', 'won')->count();
        $this->stats['lost_bets'] = Bet::where('status', 'lost')->count();
        $this->stats['pending_bets'] = Bet::where('status', 'pending')->count();
        
        $this->line("✅ Total apuestas: {$this->stats['total_bets']}");
        $this->line("✅ Ganadas: {$this->stats['won_bets']} | Perdidas: {$this->stats['lost_bets']} | Pendientes: {$this->stats['pending_bets']}");
    }
    
    private function checkBudgetConsistency()
    {
        $this->info("\n💳 4. VERIFICANDO CONSISTENCIA DE PRESUPUESTOS...");
        
        // Check budget calculation accuracy
        foreach (BudgetConfiguration::all() as $budget) {
            $calculatedBudget = $budget->initial_budget;
            
            $history = BudgetHistory::where('budget_configuration_id', $budget->id)
                ->orderBy('created_at', 'asc')
                ->get();
                
            foreach ($history as $entry) {
                $calculatedBudget += $entry->amount;
            }
            
            $difference = abs($calculatedBudget - $budget->current_budget);
            
            if ($difference > 0.01) {
                $this->issues[] = "❌ Budget {$budget->name}: Calculated €{$calculatedBudget} vs Stored €{$budget->current_budget}";
            }
        }
        
        $this->stats['total_budgets'] = BudgetConfiguration::count();
        $this->stats['total_history_entries'] = BudgetHistory::count();
        
        $this->line("✅ Total presupuestos: {$this->stats['total_budgets']}");
        $this->line("✅ Entradas historial: {$this->stats['total_history_entries']}");
    }
    
    private function checkTeamConsistency()
    {
        $this->info("\n⚽ 5. VERIFICANDO CONSISTENCIA DE EQUIPOS...");
        
        // Check for duplicate team names
        $duplicateNames = Team::select('name')
            ->groupBy('name')
            ->havingRaw('COUNT(*) > 1')
            ->count();
            
        if ($duplicateNames > 0) {
            $this->warnings[] = "⚠️  {$duplicateNames} nombres de equipos duplicados";
        }
        
        $this->stats['total_teams'] = Team::count();
        
        $this->line("✅ Total equipos: {$this->stats['total_teams']}");
    }
    
    private function checkBusinessLogicConsistency()
    {
        $this->info("📈 Verificando lógica de negocio...");
        
        // Check for unrealistic goal predictions
        $unrealisticGoals = MatchPrediction::where('home_goals_prediction', '>', 8)
            ->orWhere('away_goals_prediction', '>', 8)
            ->orWhere('home_goals_prediction', '<', 0)
            ->orWhere('away_goals_prediction', '<', 0)
            ->count();
            
        if ($unrealisticGoals > 0) {
            $this->warnings[] = "⚠️  {$unrealisticGoals} predicciones de goles irreales";
        }
        
        // Check for model version consistency
        $oldModelPredictions = MatchPrediction::where('model_version', 'like', '2.%')
            ->where('created_at', '>', now()->subDays(7))
            ->count();
            
        if ($oldModelPredictions > 0) {
            $this->warnings[] = "⚠️  {$oldModelPredictions} predicciones recientes usando modelo 2.x";
        }
    }
    
    private function checkDataQuality()
    {
        $this->info("🔍 Verificando calidad de datos...");
        
        // Check for missing odds
        $matchesWithoutOdds = FootballMatch::whereNull('odds')
            ->where('match_date', '>', now()->subDays(7))
            ->count();
            
        if ($matchesWithoutOdds > 0) {
            $this->warnings[] = "⚠️  {$matchesWithoutOdds} partidos recientes sin odds";
        }
    }
    
    private function checkPerformanceMetrics()
    {
        $this->info("⚡ Verificando métricas de rendimiento...");
        
        // Check for tables that might need cleanup
        $oldMatches = FootballMatch::where('match_date', '<', now()->subMonths(6))
            ->where('status', 'finished')
            ->count();
            
        if ($oldMatches > 10000) {
            $this->warnings[] = "⚠️  {$oldMatches} partidos antiguos (>6 meses) - considerar archivo";
        }
        
        $this->stats['old_matches'] = $oldMatches;
    }
    
    private function showSummary()
    {
        $this->info("\n📋 RESUMEN DE AUDITORÍA");
        $this->info("=====================");
        
        if (empty($this->issues) && empty($this->warnings)) {
            $this->info("✅ No se encontraron problemas críticos");
        } else {
            if (!empty($this->issues)) {
                $this->error("\n🚨 PROBLEMAS CRÍTICOS ENCONTRADOS:");
                foreach ($this->issues as $issue) {
                    $this->line("  " . $issue);
                }
            }
            
            if (!empty($this->warnings)) {
                $this->warn("\n⚠️  ADVERTENCIAS:");
                foreach ($this->warnings as $warning) {
                    $this->line("  " . $warning);
                }
            }
        }
        
        $this->info("\n📊 ESTADÍSTICAS:");
        foreach ($this->stats as $key => $value) {
            $this->line("  " . ucfirst(str_replace('_', ' ', $key)) . ": {$value}");
        }
    }
    
    private function applyFixes()
    {
        $fixed = 0;
        
        // Fix resolved_at for won/lost bets
        $unresolvedBets = Bet::whereIn('status', ['won', 'lost'])
            ->whereNull('resolved_at')
            ->get();
            
        foreach ($unresolvedBets as $bet) {
            $bet->resolved_at = $bet->updated_at;
            $bet->save();
            $fixed++;
        }
        
        if ($fixed > 0) {
            $this->info("✅ {$fixed} problemas corregidos automáticamente");
        }
    }
    
    private function generateReport()
    {
        $reportData = [
            'audit_date' => now()->toDateTimeString(),
            'issues' => $this->issues,
            'warnings' => $this->warnings,
            'stats' => $this->stats
        ];
        
        $reportPath = storage_path('logs/db_audit_' . now()->format('Y-m-d_H-i-s') . '.json');
        file_put_contents($reportPath, json_encode($reportData, JSON_PRETTY_PRINT));
        
        $this->info("📄 Reporte guardado en: {$reportPath}");
    }
}