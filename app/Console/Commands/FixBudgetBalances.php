<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\BudgetConfiguration;
use App\Models\Bet;

class FixBudgetBalances extends Command
{
    protected $signature = 'budget:fix-balances {--dry-run : Show what would be fixed without making changes}';
    protected $description = 'Fix incorrect budget balances caused by the resolveBet bug';

    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        
        if ($isDryRun) {
            $this->info('🔍 DRY RUN MODE - No changes will be made');
        } else {
            $this->info('🔧 FIXING BUDGET BALANCES');
        }
        
        $budgets = BudgetConfiguration::all();
        $fixedCount = 0;
        $totalErrorAmount = 0;
        
        foreach ($budgets as $budget) {
            $this->line("Checking Budget #{$budget->id}: {$budget->name}");
            
            // Calculate correct balance
            $correctBalance = $this->calculateCorrectBalance($budget);
            $currentBalance = $budget->current_budget;
            $difference = $currentBalance - $correctBalance;
            
            $this->line("  Current: €" . number_format($currentBalance, 2));
            $this->line("  Correct: €" . number_format($correctBalance, 2));
            
            if (abs($difference) > 0.01) {
                $this->warn("  ❌ ERROR: Difference of €" . number_format($difference, 2));
                $totalErrorAmount += abs($difference);
                
                if (!$isDryRun) {
                    $budget->current_budget = $correctBalance;
                    $budget->save();
                    $this->info("  ✅ FIXED");
                }
                $fixedCount++;
            } else {
                $this->info("  ✅ OK");
            }
            
            $this->line('');
        }
        
        $this->line('=== SUMMARY ===');
        $this->line("Total budgets checked: {$budgets->count()}");
        $this->line("Budgets with errors: {$fixedCount}");
        $this->line("Total error amount: €" . number_format($totalErrorAmount, 2));
        
        if ($isDryRun && $fixedCount > 0) {
            $this->warn('Run without --dry-run to apply fixes');
        } elseif (!$isDryRun && $fixedCount > 0) {
            $this->info('✅ All budget balances have been corrected!');
        } else {
            $this->info('✅ All budget balances are correct');
        }
    }
    
    private function calculateCorrectBalance(BudgetConfiguration $budget): float
    {
        $initialBudget = $budget->initial_budget;
        
        // Get all resolved bets (won/lost) - these affect the balance
        $resolvedBets = $budget->bets()
            ->whereIn('status', ['won', 'lost'])
            ->orderBy('resolved_at')
            ->get();
            
        // Get all pending bets - these reduce available balance
        $pendingBetsAmount = $budget->bets()
            ->where('status', 'pending')
            ->sum('amount');
        
        $balance = $initialBudget;
        
        // Apply all resolved bet results
        foreach ($resolvedBets as $bet) {
            // For lost bets: actual_profit = -amount (we lose the bet amount)
            // For won bets: actual_profit = positive profit (we gain profit)
            $balance += $bet->actual_profit ?? 0;
        }
        
        // Subtract pending bets (money committed but not resolved)
        $balance -= $pendingBetsAmount;
        
        return round($balance, 2);
    }
}