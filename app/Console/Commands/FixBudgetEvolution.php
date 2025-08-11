<?php

namespace App\Console\Commands;

use App\Models\BudgetConfiguration;
use App\Models\Bet;
use Illuminate\Console\Command;

class FixBudgetEvolution extends Command
{
    protected $signature = 'budgets:fix-evolution {--budget=* : Specific budget names or IDs} {--dry-run : Show what would be fixed}';
    protected $description = 'Fix budget evolution to properly handle pending bets without reserving funds';

    public function handle()
    {
        $this->info('🔧 FIXING BUDGET EVOLUTION');
        $this->info('==========================');

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
            $this->error('No budgets found');
            return 1;
        }

        $totalFixed = 0;
        
        foreach ($budgets as $budget) {
            $this->line('');
            $this->info("💰 Budget: {$budget->name} (ID: {$budget->id})");
            $this->line('----------------------------------------');
            
            $result = $this->fixBudgetEvolution($budget, $dryRun);
            
            if ($result['fixed']) {
                $totalFixed++;
                $this->info("✅ Fixed - Evolution corrected with {$result['corrections']} changes");
            } else {
                $this->comment("ℹ️  Evolution is already correct");
            }
        }

        $this->line('');
        $this->info('📊 SUMMARY');
        $this->info('===========');
        $this->line("Budgets processed: {$budgets->count()}");
        $this->line("Budgets fixed: {$totalFixed}");

        if ($dryRun) {
            $this->warn('No actual changes were made (dry run mode)');
        } else {
            $this->info('Budget evolution has been corrected!');
        }

        return 0;
    }

    private function fixBudgetEvolution(BudgetConfiguration $budget, bool $dryRun): array
    {
        $bets = $budget->bets()->orderBy('id')->get();
        $corrections = 0;
        
        $this->line("Total bets: {$bets->count()}");
        
        if ($bets->isEmpty()) {
            return ['fixed' => false, 'corrections' => 0];
        }
        
        // CORRECT APPROACH: Only resolved bets affect the budget evolution
        $runningBalance = $budget->initial_budget;
        
        $this->line("Correcting evolution from initial budget: €{$runningBalance}");
        
        foreach ($bets as $bet) {
            $correctBudgetBefore = $runningBalance;
            $needsSave = false;
            
            // Fix budget_before if incorrect
            if (abs($bet->budget_before - $correctBudgetBefore) > 0.01) {
                $corrections++;
                $this->line("  Bet {$bet->id}: budget_before €{$bet->budget_before} → €{$correctBudgetBefore}");
                if (!$dryRun) {
                    $bet->budget_before = $correctBudgetBefore;
                    $needsSave = true;
                }
            }
            
            // Handle based on status
            if ($bet->status === 'won') {
                $correctActualProfit = ($bet->amount * $bet->odds) - $bet->amount;
                $correctBudgetAfter = $correctBudgetBefore + ($bet->amount * $bet->odds);
                
                // Update running balance
                $runningBalance = $correctBudgetAfter;
                
                // Fix actual_profit
                if ($bet->actual_profit !== $correctActualProfit) {
                    $corrections++;
                    $this->line("    actual_profit €{$bet->actual_profit} → €{$correctActualProfit}");
                    if (!$dryRun) {
                        $bet->actual_profit = $correctActualProfit;
                        $needsSave = true;
                    }
                }
                
                // Fix budget_after
                if ($bet->budget_after !== $correctBudgetAfter) {
                    $corrections++;
                    $this->line("    budget_after €{$bet->budget_after} → €{$correctBudgetAfter}");
                    if (!$dryRun) {
                        $bet->budget_after = $correctBudgetAfter;
                        $needsSave = true;
                    }
                }
                
            } elseif ($bet->status === 'lost') {
                $correctActualProfit = -$bet->amount;
                $correctBudgetAfter = $correctBudgetBefore - $bet->amount;
                
                // Update running balance
                $runningBalance = $correctBudgetAfter;
                
                // Fix actual_profit
                if ($bet->actual_profit !== $correctActualProfit) {
                    $corrections++;
                    $this->line("    actual_profit €{$bet->actual_profit} → €{$correctActualProfit}");
                    if (!$dryRun) {
                        $bet->actual_profit = $correctActualProfit;
                        $needsSave = true;
                    }
                }
                
                // Fix budget_after
                if ($bet->budget_after !== $correctBudgetAfter) {
                    $corrections++;
                    $this->line("    budget_after €{$bet->budget_after} → €{$correctBudgetAfter}");
                    if (!$dryRun) {
                        $bet->budget_after = $correctBudgetAfter;
                        $needsSave = true;
                    }
                }
                
            } elseif ($bet->status === 'pending') {
                // Pending bets should NOT affect the running balance
                // They should have null actual_profit and budget_after
                
                if ($bet->actual_profit !== null) {
                    $corrections++;
                    $this->line("    Clearing actual_profit for pending bet");
                    if (!$dryRun) {
                        $bet->actual_profit = null;
                        $needsSave = true;
                    }
                }
                
                if ($bet->budget_after !== null) {
                    $corrections++;
                    $this->line("    Clearing budget_after for pending bet");
                    if (!$dryRun) {
                        $bet->budget_after = null;
                        $needsSave = true;
                    }
                }
                
                // Running balance stays the same for pending bets
                
            } else { // cancelled
                $correctActualProfit = 0;
                $correctBudgetAfter = $correctBudgetBefore; // No change
                
                // Running balance stays the same for cancelled bets
                $runningBalance = $correctBudgetAfter;
                
                if ($bet->actual_profit !== $correctActualProfit) {
                    $corrections++;
                    if (!$dryRun) {
                        $bet->actual_profit = $correctActualProfit;
                        $needsSave = true;
                    }
                }
                
                if ($bet->budget_after !== $correctBudgetAfter) {
                    $corrections++;
                    if (!$dryRun) {
                        $bet->budget_after = $correctBudgetAfter;
                        $needsSave = true;
                    }
                }
            }
            
            if (!$dryRun && $needsSave) {
                $bet->save();
            }
        }
        
        // Fix the main budget current_budget
        if (abs($budget->current_budget - $runningBalance) > 0.01) {
            $corrections++;
            $this->line("Main budget: €{$budget->current_budget} → €{$runningBalance}");
            if (!$dryRun) {
                $budget->current_budget = $runningBalance;
                $budget->save();
            }
        }
        
        return [
            'fixed' => $corrections > 0,
            'corrections' => $corrections
        ];
    }
}