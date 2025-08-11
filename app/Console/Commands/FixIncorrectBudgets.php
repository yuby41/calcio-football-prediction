<?php

namespace App\Console\Commands;

use App\Models\BudgetConfiguration;
use App\Models\Bet;
use Illuminate\Console\Command;
use DB;

class FixIncorrectBudgets extends Command
{
    protected $signature = 'budgets:fix-calculations {--budget=* : Specific budget IDs or names to fix} {--dry-run : Show what would be fixed without making changes}';
    protected $description = 'Fix incorrect budget calculations by recalculating from bet history';

    public function handle()
    {
        $this->info('🔧 FIXING BUDGET CALCULATIONS');
        $this->info('=============================');

        $budgetIds = $this->option('budget');
        $dryRun = $this->option('dry-run');
        
        if ($dryRun) {
            $this->warn('🔍 DRY RUN MODE - No changes will be made');
            $this->line('');
        }

        $query = BudgetConfiguration::query();
        
        if (!empty($budgetIds)) {
            $query->where(function($q) use ($budgetIds) {
                foreach ($budgetIds as $id) {
                    if (is_numeric($id)) {
                        $q->orWhere('id', $id);
                    } else {
                        $q->orWhere('name', 'like', "%{$id}%");
                    }
                }
            });
        }

        $budgets = $query->get();
        
        if ($budgets->isEmpty()) {
            $this->error('No budgets found to fix');
            return 1;
        }

        $totalFixed = 0;
        $totalErrors = 0;

        foreach ($budgets as $budget) {
            $this->line('');
            $this->info("💰 Budget: {$budget->name} (ID: {$budget->id})");
            $this->line('----------------------------------------');
            
            $result = $this->fixBudgetCalculations($budget, $dryRun);
            
            if ($result['fixed']) {
                $totalFixed++;
                $this->info("✅ Fixed - Errors corrected: {$result['errors']}");
                if ($result['balance_corrected']) {
                    $this->info("  💰 Budget corrected: €{$result['old_balance']} → €{$result['new_balance']}");
                }
            } else {
                $this->comment("ℹ️  No corrections needed");
            }
            
            $totalErrors += $result['errors'];
        }

        $this->line('');
        $this->info('📊 SUMMARY');
        $this->info('===========');
        $this->line("Budgets processed: {$budgets->count()}");
        $this->line("Budgets fixed: {$totalFixed}");
        $this->line("Total errors corrected: {$totalErrors}");

        if ($dryRun) {
            $this->warn('No actual changes were made (dry run mode)');
        } else {
            $this->info('All budget calculations have been corrected!');
        }

        return 0;
    }

    private function fixBudgetCalculations(BudgetConfiguration $budget, bool $dryRun): array
    {
        $bets = $budget->bets()->orderBy('id')->get();
        $errors = 0;
        
        $oldBudgetBalance = $budget->current_budget;

        $this->line("Initial budget: €{$budget->initial_budget}");
        $this->line("Current budget: €{$budget->current_budget}");
        $this->line("Total bets: {$bets->count()}");
        
        if ($bets->isEmpty()) {
            // No bets, budget should equal initial budget
            if (abs($budget->current_budget - $budget->initial_budget) > 0.01) {
                $errors++;
                
                if (!$dryRun) {
                    $budget->current_budget = $budget->initial_budget;
                    $budget->save();
                }
                $this->line("  🔧 Reset budget to initial: €{$budget->current_budget} → €{$budget->initial_budget}");
            }
        } else {
            // Calculate what the current budget should be based on resolved bets only
            $correctBalance = $budget->initial_budget;
            
            foreach ($bets as $bet) {
                // Fix individual bet calculations
                $betErrors = $this->validateAndFixBet($bet, $dryRun);
                $errors += $betErrors;
                
                // Only count resolved bets towards current budget
                if ($bet->status === 'won') {
                    $correctBalance += ($bet->amount * $bet->odds) - $bet->amount;
                } elseif ($bet->status === 'lost') {
                    $correctBalance -= $bet->amount;
                }
                // pending and cancelled bets don't affect current budget
            }

            // Fix main budget if incorrect
            if (abs($budget->current_budget - $correctBalance) > 0.01) {
                $errors++;
                
                if (!$dryRun) {
                    $budget->current_budget = $correctBalance;
                    $budget->save();
                }
                $this->line("  🔧 Budget corrected: €{$oldBudgetBalance} → €{$correctBalance}");
            }
        }

        return [
            'fixed' => $errors > 0,
            'errors' => $errors,
            'balance_corrected' => abs($oldBudgetBalance - ($budget->current_budget ?? $correctBalance ?? $oldBudgetBalance)) > 0.01,
            'old_balance' => $oldBudgetBalance,
            'new_balance' => $budget->current_budget ?? $correctBalance ?? $oldBudgetBalance
        ];
    }

    private function validateAndFixBet(Bet $bet, bool $dryRun): int
    {
        $errors = 0;
        $needsSave = false;

        // Calculate correct potential_profit
        $correctPotentialProfit = ($bet->amount * $bet->odds) - $bet->amount;
        if (abs($bet->potential_profit - $correctPotentialProfit) > 0.01) {
            $errors++;
            if (!$dryRun) {
                $bet->potential_profit = $correctPotentialProfit;
                $needsSave = true;
            }
            $this->line("    🔧 Potential profit: €{$bet->potential_profit} → €{$correctPotentialProfit}");
        }

        // For resolved bets, check actual_profit and budget_after
        if ($bet->status === 'won') {
            $correctActualProfit = $correctPotentialProfit;
            
            if ($bet->actual_profit !== $correctActualProfit) {
                $errors++;
                if (!$dryRun) {
                    $bet->actual_profit = $correctActualProfit;
                    $needsSave = true;
                }
                $this->line("    🔧 Actual profit: €{$bet->actual_profit} → €{$correctActualProfit}");
            }
            
        } elseif ($bet->status === 'lost') {
            $correctActualProfit = -$bet->amount;
            
            if ($bet->actual_profit !== $correctActualProfit) {
                $errors++;
                if (!$dryRun) {
                    $bet->actual_profit = $correctActualProfit;
                    $needsSave = true;
                }
                $this->line("    🔧 Actual profit: €{$bet->actual_profit} → €{$correctActualProfit}");
            }
            
        } elseif ($bet->status === 'pending') {
            // Pending bets should have null actual_profit and budget_after
            if ($bet->actual_profit !== null) {
                $errors++;
                if (!$dryRun) {
                    $bet->actual_profit = null;
                    $needsSave = true;
                }
                $this->line("    🔧 Cleared actual profit for pending bet");
            }
            
            if ($bet->budget_after !== null) {
                $errors++;
                if (!$dryRun) {
                    $bet->budget_after = null;
                    $needsSave = true;
                }
                $this->line("    🔧 Cleared budget_after for pending bet");
            }
        }

        if (!$dryRun && $needsSave) {
            $bet->save();
        }

        return $errors;
    }
}