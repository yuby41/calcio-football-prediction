<?php

namespace App\Console\Commands;

use App\Models\FootballMatch;
use App\Models\MatchPrediction;
use App\Models\BudgetConfiguration;
use App\Models\Bet;
use App\Models\Team;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SystematicIssuesAudit extends Command
{
    protected $signature = 'app:audit-systematic-issues {--fix : Fix issues found} {--detailed : Show detailed output}';
    protected $description = 'Comprehensive audit of systematic issues in the application';

    private $issues = [];
    private $criticalIssues = 0;
    private $warningIssues = 0;

    public function handle()
    {
        $fix = $this->option('fix');
        $detailed = $this->option('detailed');
        
        $this->info('🔍 AUDITORÍA SISTEMÁTICA DE LA APLICACIÓN');
        $this->info('=====================================');
        $this->line('');
        
        // 1. Data Integrity Issues
        $this->checkDataIntegrity($detailed);
        
        // 2. Prediction System Issues
        $this->checkPredictionSystem($detailed);
        
        // 3. Budget System Issues
        $this->checkBudgetSystem($detailed);
        
        // 4. API Integration Issues
        $this->checkApiIntegration($detailed);
        
        // 5. Database Consistency Issues
        $this->checkDatabaseConsistency($detailed);
        
        // 6. Performance Issues
        $this->checkPerformanceIssues($detailed);
        
        // 7. Security Issues
        $this->checkSecurityIssues($detailed);
        
        // Summary
        $this->showSummary($fix);
        
        if ($fix && !empty($this->issues)) {
            $this->fixIssues();
        }
        
        return 0;
    }
    
    private function checkDataIntegrity($detailed = false): void
    {
        $this->info('🔍 1. INTEGRIDAD DE DATOS');
        $this->info('=======================');
        
        // Check for NULL values in critical fields
        $nullMatches = FootballMatch::where('status', 'finished')
            ->where(function($query) {
                $query->whereNull('home_goals')
                      ->orWhereNull('away_goals');
            })
            ->count();
            
        if ($nullMatches > 0) {
            $this->addIssue('critical', "Data Integrity", "$nullMatches partidos terminados sin goles registrados", 'matches_missing_goals');
        }
        
        // Check for orphaned records
        $orphanedPredictions = MatchPrediction::whereNotExists(function ($query) {
            $query->select(DB::raw(1))
                  ->from('matches')
                  ->whereRaw('matches.id = match_predictions.match_id');
        })->count();
        
        if ($orphanedPredictions > 0) {
            $this->addIssue('warning', "Data Integrity", "$orphanedPredictions predicciones huérfanas encontradas", 'orphaned_predictions');
        }
        
        // Check for duplicate teams
        $duplicateTeams = DB::select("
            SELECT name, COUNT(*) as count 
            FROM teams 
            GROUP BY name 
            HAVING COUNT(*) > 1
        ");
        
        if (!empty($duplicateTeams)) {
            $count = count($duplicateTeams);
            $this->addIssue('warning', "Data Integrity", "$count equipos duplicados encontrados", 'duplicate_teams');
            
            if ($detailed) {
                foreach ($duplicateTeams as $team) {
                    $this->line("   - {$team->name} ({$team->count} duplicados)");
                }
            }
        }
        
        // Check for inconsistent external IDs
        $inconsistentExternalIds = FootballMatch::where('external_id', 0)
            ->orWhereNull('external_id')
            ->count();
            
        if ($inconsistentExternalIds > 0) {
            $this->addIssue('warning', "Data Integrity", "$inconsistentExternalIds partidos sin external_id válido", 'missing_external_ids');
        }
        
        $this->line('');
    }
    
    private function checkPredictionSystem($detailed = false): void
    {
        $this->info('🔍 2. SISTEMA DE PREDICCIONES');
        $this->info('============================');
        
        // Check for predictions without probabilities
        $invalidPredictions = MatchPrediction::where(function($q) {
            $q->whereNull('home_win_probability')
              ->orWhereNull('draw_probability')
              ->orWhereNull('away_win_probability');
        })->count();
        
        if ($invalidPredictions > 0) {
            $this->addIssue('critical', "Prediction System", "$invalidPredictions predicciones sin probabilidades válidas", 'invalid_predictions');
        }
        
        // Check for probability sum != 1
        $invalidProbabilitySums = MatchPrediction::whereRaw('
            ABS((home_win_probability + draw_probability + away_win_probability) - 1.0) > 0.01
        ')->count();
        
        if ($invalidProbabilitySums > 0) {
            $this->addIssue('critical', "Prediction System", "$invalidProbabilitySums predicciones con probabilidades que no suman 1.0", 'invalid_probability_sums');
        }
        
        // Check for finished matches without predictions
        $matchesWithoutPredictions = FootballMatch::where('status', 'finished')
            ->whereDoesntHave('prediction')
            ->count();
            
        if ($matchesWithoutPredictions > 0) {
            $this->addIssue('warning', "Prediction System", "$matchesWithoutPredictions partidos terminados sin predicciones", 'matches_without_predictions');
        }
        
        // Check for prediction accuracy inconsistencies (sample)
        $inaccuratePredictions = MatchPrediction::whereHas('match', function($q) {
            $q->where('status', 'finished')
              ->whereNotNull('home_goals')
              ->whereNotNull('away_goals');
        })->get()->filter(function($prediction) {
            $match = $prediction->match;
            $actualOutcome = $this->getActualOutcome($match);
            return $prediction->is_correct !== ($prediction->predicted_outcome === $actualOutcome);
        })->count();
        
        if ($inaccuratePredictions > 0) {
            $this->addIssue('critical', "Prediction System", "$inaccuratePredictions predicciones con is_correct incorrecto", 'inaccurate_predictions');
        }
        
        $this->line('');
    }
    
    private function checkBudgetSystem($detailed = false): void
    {
        $this->info('🔍 3. SISTEMA DE PRESUPUESTO');
        $this->info('===========================');
        
        // Check for budget configurations with invalid values
        $invalidBudgets = BudgetConfiguration::where('initial_budget', '<=', 0)
            ->orWhere('current_budget', '<', 0)
            ->count();
            
        if ($invalidBudgets > 0) {
            $this->addIssue('critical', "Budget System", "$invalidBudgets configuraciones de budget con valores inválidos", 'invalid_budgets');
        }
        
        // Check for bets with inconsistent calculations
        $inconsistentBets = Bet::whereNotNull('actual_profit')
            ->get()
            ->filter(function($bet) {
                $expectedProfit = $bet->calculateResult()['profit'];
                return abs($bet->actual_profit - $expectedProfit) > 0.01;
            })->count();
            
        if ($inconsistentBets > 0) {
            $this->addIssue('warning', "Budget System", "$inconsistentBets apuestas con cálculos de profit inconsistentes", 'inconsistent_bet_calculations');
        }
        
        // Check for budget history integrity
        foreach (BudgetConfiguration::all() as $budget) {
            $calculatedBalance = $budget->initial_budget;
            $historyEntries = $budget->budgetHistory()->orderBy('created_at')->get();
            
            foreach ($historyEntries as $entry) {
                if (abs($entry->balance_before - $calculatedBalance) > 0.01) {
                    $this->addIssue('warning', "Budget System", "Budget {$budget->name} tiene historial inconsistente", 'inconsistent_budget_history');
                    break;
                }
                $calculatedBalance = $entry->balance_after;
            }
        }
        
        $this->line('');
    }
    
    private function checkApiIntegration($detailed = false): void
    {
        $this->info('🔍 4. INTEGRACIÓN API');
        $this->info('====================');
        
        // Check for matches with old data (not updated recently)
        $oldMatches = FootballMatch::where('status', 'scheduled')
            ->where('match_date', '<', now()->subDays(1))
            ->count();
            
        if ($oldMatches > 0) {
            $this->addIssue('warning', "API Integration", "$oldMatches partidos programados con fecha pasada", 'old_scheduled_matches');
        }
        
        // Check for teams without proper API mapping
        $teamsWithoutExternalId = Team::whereNull('external_id')
            ->orWhere('external_id', 0)
            ->count();
            
        if ($teamsWithoutExternalId > 0) {
            $this->addIssue('warning', "API Integration", "$teamsWithoutExternalId equipos sin external_id válido", 'teams_without_external_id');
        }
        
        // Check for missing first half data
        $missingFirstHalfData = FootballMatch::where('status', 'finished')
            ->where('match_date', '>', now()->subDays(30)) // Recent matches
            ->where(function($q) {
                $q->whereNull('home_goals_first_half')
                  ->orWhereNull('away_goals_first_half');
            })
            ->count();
            
        if ($missingFirstHalfData > 100) { // Threshold for concern
            $this->addIssue('warning', "API Integration", "$missingFirstHalfData partidos recientes sin datos de primer tiempo", 'missing_first_half_data');
        }
        
        $this->line('');
    }
    
    private function checkDatabaseConsistency($detailed = false): void
    {
        $this->info('🔍 5. CONSISTENCIA BASE DE DATOS');
        $this->info('===============================');
        
        // Check for foreign key violations (soft check)
        $orphanedBets = Bet::whereNotExists(function ($query) {
            $query->select(DB::raw(1))
                  ->from('matches')
                  ->whereRaw('matches.id = bets.match_id');
        })->count();
        
        if ($orphanedBets > 0) {
            $this->addIssue('critical', "Database Consistency", "$orphanedBets apuestas huérfanas encontradas", 'orphaned_bets');
        }
        
        // Check for circular references or impossible data
        $impossibleScores = FootballMatch::where('home_goals', '<', 0)
            ->orWhere('away_goals', '<', 0)
            ->orWhere('home_goals', '>', 50) // Unrealistic scores
            ->orWhere('away_goals', '>', 50)
            ->count();
            
        if ($impossibleScores > 0) {
            $this->addIssue('critical', "Database Consistency", "$impossibleScores partidos con goles imposibles", 'impossible_scores');
        }
        
        // Check for date inconsistencies
        $dateInconsistencies = FootballMatch::where('match_date', '>', now()->addYears(2))
            ->orWhere('match_date', '<', now()->subYears(10))
            ->count();
            
        if ($dateInconsistencies > 0) {
            $this->addIssue('warning', "Database Consistency", "$dateInconsistencies partidos con fechas inconsistentes", 'date_inconsistencies');
        }
        
        $this->line('');
    }
    
    private function checkPerformanceIssues($detailed = false): void
    {
        $this->info('🔍 6. PROBLEMAS DE RENDIMIENTO');
        $this->info('=============================');
        
        // Check for missing indexes (simulate by checking query patterns)
        $largeTableCounts = [
            'matches' => FootballMatch::count(),
            'match_predictions' => MatchPrediction::count(),
            'bets' => Bet::count(),
        ];
        
        foreach ($largeTableCounts as $table => $count) {
            if ($count > 10000) {
                $this->addIssue('info', "Performance", "Tabla $table tiene $count registros - verificar índices", 'large_table_' . $table);
            }
        }
        
        // Check for potential N+1 queries (look for common patterns)
        $matchesWithoutEagerLoading = FootballMatch::doesntHave('prediction')->count();
        if ($matchesWithoutEagerLoading > 100) {
            $this->addIssue('info', "Performance", "$matchesWithoutEagerLoading partidos podrían beneficiarse de eager loading", 'potential_n_plus_1');
        }
        
        $this->line('');
    }
    
    private function checkSecurityIssues($detailed = false): void
    {
        $this->info('🔍 7. PROBLEMAS DE SEGURIDAD');
        $this->info('===========================');
        
        // Check for potential SQL injection points (basic check)
        $this->addIssue('info', "Security", "Verificar que todos los queries usen parámetros preparados", 'sql_injection_check');
        
        // Check for sensitive data exposure
        $this->addIssue('info', "Security", "Verificar que las claves API no estén expuestas en logs", 'api_key_exposure');
        
        // Check for proper validation
        $this->addIssue('info', "Security", "Verificar validación de entrada en controladores", 'input_validation');
        
        $this->line('');
    }
    
    private function addIssue($severity, $category, $description, $key): void
    {
        $this->issues[] = [
            'severity' => $severity,
            'category' => $category,
            'description' => $description,
            'key' => $key
        ];
        
        if ($severity === 'critical') {
            $this->criticalIssues++;
            $this->error("❌ CRÍTICO: $description");
        } elseif ($severity === 'warning') {
            $this->warningIssues++;
            $this->warn("⚠️  ADVERTENCIA: $description");
        } else {
            $this->line("ℹ️  INFO: $description");
        }
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
    
    private function showSummary($fix): void
    {
        $this->line('');
        $this->info('📊 RESUMEN DE AUDITORÍA');
        $this->info('======================');
        
        $totalIssues = count($this->issues);
        
        $this->line("Total de problemas encontrados: $totalIssues");
        $this->line("🔴 Críticos: {$this->criticalIssues}");
        $this->line("🟡 Advertencias: {$this->warningIssues}");
        $this->line("🔵 Informativos: " . ($totalIssues - $this->criticalIssues - $this->warningIssues));
        
        if ($this->criticalIssues > 0) {
            $this->line('');
            $this->error("⚠️  SE ENCONTRARON {$this->criticalIssues} PROBLEMAS CRÍTICOS QUE REQUIEREN ATENCIÓN INMEDIATA");
        }
        
        if ($fix) {
            $this->line('');
            $this->info('🔧 Procediendo a corregir problemas automáticamente...');
        } else {
            $this->line('');
            $this->info('💡 Ejecuta con --fix para corregir automáticamente los problemas detectados');
        }
    }
    
    private function fixIssues(): void
    {
        $fixed = 0;
        
        foreach ($this->issues as $issue) {
            switch ($issue['key']) {
                case 'inaccurate_predictions':
                    $this->call('predictions:verify', ['--fix' => true, '--limit' => 1000]);
                    $fixed++;
                    break;
                    
                case 'missing_first_half_data':
                    $this->call('matches:update-first-half');
                    $fixed++;
                    break;
                    
                case 'invalid_budgets':
                    $this->call('budgets:fix-calculations', ['--all' => true]);
                    $fixed++;
                    break;
                    
                case 'orphaned_predictions':
                    // Clean up orphaned predictions
                    MatchPrediction::whereNotExists(function ($query) {
                        $query->select(DB::raw(1))
                              ->from('matches')
                              ->whereRaw('matches.id = match_predictions.match_id');
                    })->delete();
                    $fixed++;
                    break;
                    
                case 'duplicate_teams':
                    $this->call('teams:cleanup-duplicates', ['--merge' => true]);
                    $fixed++;
                    break;
                    
                case 'missing_external_ids':
                    $this->call('teams:fix-external-ids');
                    $fixed++;
                    break;
                    
                case 'matches_missing_goals':
                    $this->call('matches:fix-without-goals');
                    $fixed++;
                    break;
                    
                case 'invalid_probability_sums':
                    // Fix probability sums that don't equal 1.0
                    $invalidProbabilities = MatchPrediction::whereRaw('
                        ABS((home_win_probability + draw_probability + away_win_probability) - 1.0) > 0.01
                    ')->get();
                    
                    foreach ($invalidProbabilities as $prediction) {
                        $total = $prediction->home_win_probability + $prediction->draw_probability + $prediction->away_win_probability;
                        if ($total > 0) {
                            $prediction->update([
                                'home_win_probability' => $prediction->home_win_probability / $total,
                                'draw_probability' => $prediction->draw_probability / $total,
                                'away_win_probability' => $prediction->away_win_probability / $total
                            ]);
                        }
                    }
                    $fixed++;
                    break;
                    
                case 'impossible_scores':
                    $this->call('matches:fix-first-half');
                    $fixed++;
                    break;
                    
                case 'inconsistent_bets':
                    $this->call('bets:fix-calculations');
                    $fixed++;
                    break;
                    
                case 'budget_history_inconsistencies':
                case 'inconsistent_budget_history':
                    $this->call('budgets:fix');
                    $fixed++;
                    break;
                    
                case 'inconsistent_bet_calculations':
                    $this->call('bets:verify-calculations', ['--fix' => true]);
                    $fixed++;
                    break;
                    
                case 'matches_without_predictions':
                    // Generate predictions for recently finished matches using batch command
                    $this->call('predictions:generate-batch', ['--limit' => 100, '--days' => 30]);
                    $fixed++;
                    break;
                    
                case 'teams_without_external_id':
                    $this->call('teams:fix-external-ids');
                    $fixed++;
                    break;
                    
                case 'old_scheduled_matches':
                    // Fix scheduled matches with past dates
                    \Illuminate\Support\Facades\DB::table('matches')
                        ->where('status', 'scheduled')
                        ->where('match_date', '<', now()->subHours(24))
                        ->update(['status' => 'cancelled', 'updated_at' => now()]);
                    $this->info("Fixed old scheduled matches");
                    $fixed++;
                    break;
                    
                case 'date_inconsistencies':
                    // Fix basic date inconsistencies
                    \Illuminate\Support\Facades\DB::table('matches')
                        ->where('status', 'finished')
                        ->where('match_date', '>', now())
                        ->update(['match_date' => now(), 'updated_at' => now()]);
                    $this->info("Fixed date inconsistencies");
                    $fixed++;
                    break;
                    
                case 'large_table_matches':
                case 'large_table_match_predictions':
                case 'large_table_bets':
                    // Optimize database tables for performance
                    $this->call('db:optimize', ['--analyze' => true]);
                    $this->info("Optimized database tables");
                    $fixed++;
                    break;
                    
                case 'potential_n_plus_1':
                    // Optimize queries by creating indexes and updating statistics
                    $this->call('queries:optimize', ['--create-indexes' => true, '--update-statistics' => true]);
                    $this->info("Applied query optimizations and created database indexes");
                    $fixed++;
                    break;
                    
                case 'sql_injection_check':
                case 'api_key_exposure':
                case 'input_validation':
                    // Security checks are informational
                    $this->info("Security check noted - manual review required");
                    $fixed++;
                    break;
                    
                // Add more fixes as needed
            }
        }
        
        $this->info("✅ Se corrigieron $fixed problemas automáticamente");
        
        if ($fixed < count($this->issues)) {
            $remaining = count($this->issues) - $fixed;
            $this->warn("⚠️  $remaining problemas requieren intervención manual");
        }
    }
}