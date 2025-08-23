<?php

namespace App\Console\Commands;

use App\Models\BudgetConfiguration;
use App\Models\Bet;
use Illuminate\Console\Command;

class VerifyBudgetEvolution extends Command
{
    protected $signature = 'budgets:verify-evolution {--budget=* : Specific budget names or IDs} {--detailed : Show detailed bet-by-bet analysis}';
    protected $description = 'Verify the complete evolution of budget calculations step by step';

    public function handle()
    {
        $this->info('📊 VERIFYING BUDGET EVOLUTION');
        $this->info('=============================');

        $budgetIds = $this->option('budget');
        $detailed = $this->option('detailed');
        
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
        } else {
            // Show active budgets by default
            $query->where('is_active', true);
        }

        $budgets = $query->get();
        
        if ($budgets->isEmpty()) {
            $this->error('No budgets found');
            return 1;
        }

        foreach ($budgets as $budget) {
            $this->analyzeBudgetEvolution($budget, $detailed);
            $this->line('');
        }

        return 0;
    }

    private function analyzeBudgetEvolution(BudgetConfiguration $budget, bool $detailed)
    {
        $this->line('');
        $this->info("💰 BUDGET: {$budget->name} (ID: {$budget->id})");
        $this->line('===============================================');
        
        $this->line("Initial Budget: €{$budget->initial_budget}");
        $this->line("Current Budget: €{$budget->current_budget}");
        $this->line("Strategy: {$budget->betting_strategy}");
        $this->line("Status: " . ($budget->is_active ? 'ACTIVE' : 'INACTIVE'));
        
        $bets = $budget->bets()->orderBy('id')->get();
        $this->line("Total Bets: {$bets->count()}");
        
        if ($bets->isEmpty()) {
            $this->comment("No bets found");
            return;
        }
        
        // Track evolution step by step
        $calculatedBalance = $budget->initial_budget;
        $issues = [];
        $totalWins = 0;
        $totalLosses = 0;
        $totalPending = 0;
        $totalProfit = 0;
        
        $this->line('');
        $this->info("📈 BUDGET EVOLUTION ANALYSIS");
        $this->line('-----------------------------');
        
        foreach ($bets as $index => $bet) {
            $betNumber = $index + 1;
            $expectedBudgetBefore = $calculatedBalance;
            
            // Verify budget_before
            if (abs($bet->budget_before - $calculatedBalance) > 0.01) {
                $issues[] = "Bet {$betNumber}: budget_before mismatch (Expected: €{$calculatedBalance}, Got: €{$bet->budget_before})";
            }
            
            // Calculate what should happen based on bet result
            $expectedActualProfit = null;
            $expectedBudgetAfter = null;
            
            if ($bet->status === 'won') {
                $expectedActualProfit = ($bet->amount * $bet->odds) - $bet->amount;
                $expectedBudgetAfter = $calculatedBalance + ($bet->amount * $bet->odds);
                $calculatedBalance = $expectedBudgetAfter;
                $totalWins++;
                $totalProfit += $expectedActualProfit;
            } elseif ($bet->status === 'lost') {
                $expectedActualProfit = -$bet->amount;
                $expectedBudgetAfter = $calculatedBalance - $bet->amount;
                $calculatedBalance = $expectedBudgetAfter;
                $totalLosses++;
                $totalProfit += $expectedActualProfit;
            } elseif ($bet->status === 'pending') {
                $expectedActualProfit = null;
                $expectedBudgetAfter = null;
                // Budget shouldn't change for pending bets, calculatedBalance stays the same
                $totalPending++;
            } else { // cancelled
                $expectedActualProfit = 0;
                $expectedBudgetAfter = $calculatedBalance;
                // calculatedBalance stays the same for cancelled bets
            }
            
            // Verify actual_profit
            if ($bet->status !== 'pending') {
                if (abs($bet->actual_profit - $expectedActualProfit) > 0.01) {
                    $issues[] = "Bet {$betNumber}: actual_profit mismatch (Expected: €{$expectedActualProfit}, Got: €{$bet->actual_profit})";
                }
            }
            
            // Verify budget_after
            if ($bet->status !== 'pending') {
                if (abs($bet->budget_after - $expectedBudgetAfter) > 0.01) {
                    $issues[] = "Bet {$betNumber}: budget_after mismatch (Expected: €{$expectedBudgetAfter}, Got: €{$bet->budget_after})";
                }
            }
            
            // Show detailed evolution if requested
            if ($detailed) {
                $match = $bet->match;
                $this->line("Bet {$betNumber} (ID: {$bet->id}) - " . date('d/m H:i', strtotime($bet->placed_at)));
                $this->line("  Match: {$match->homeTeam->name} vs {$match->awayTeam->name}");
                $this->line("  Type: {$bet->bet_type} | Amount: €{$bet->amount} | Odds: {$bet->odds}");
                $this->line("  Status: {$bet->status} | Confidence: {$bet->confidence}%");
                $this->line("  Budget Before: €{$bet->budget_before}");
                
                if ($bet->status === 'won') {
                    $this->info("  ✅ WON - Profit: €{$bet->actual_profit} | Budget After: €{$bet->budget_after}");
                } elseif ($bet->status === 'lost') {
                    $this->error("  ❌ LOST - Loss: €{$bet->actual_profit} | Budget After: €{$bet->budget_after}");
                } elseif ($bet->status === 'pending') {
                    $this->comment("  ⏳ PENDING - No budget change yet");
                }
                $this->line("");
            }
        }
        
        // Final verification - check available balance logic
        $this->line('');
        $this->info("📊 SUMMARY");
        $this->line('----------');
        $this->line("Wins: {$totalWins} | Losses: {$totalLosses} | Pending: {$totalPending}");
        $this->line("Total Net Profit: €" . number_format($totalProfit, 2));
        
        // Use the new budget model methods for accurate verification
        $resolvedBalance = $budget->getBalanceAfterResolvedBets();
        $pendingAmount = $budget->getPendingBetsAmount();
        $expectedAvailableBalance = $budget->getAvailableBalance();
        
        $this->line("Expected Balance After Resolved Bets: €" . number_format($resolvedBalance, 2));
        $this->line("Pending Bets Amount: €" . number_format($pendingAmount, 2));
        $this->line("Expected Available Balance: €" . number_format($expectedAvailableBalance, 2));
        $this->line("Actual Available Balance: €{$budget->current_budget}");
        
        $budgetDifference = abs($budget->current_budget - $expectedAvailableBalance);
        if ($budgetDifference > 0.01) {
            $this->error("❌ AVAILABLE BALANCE MISMATCH: Difference of €" . number_format($budgetDifference, 2));
            $issues[] = "Available balance doesn't match expected calculation";
        } else {
            $this->info("✅ AVAILABLE BALANCE CORRECT");
        }
        
        // Show issues if any
        if (!empty($issues)) {
            $this->line('');
            $this->error("❌ ISSUES FOUND:");
            foreach ($issues as $issue) {
                $this->line("  • {$issue}");
            }
        } else {
            $this->line('');
            $this->info("✅ NO ISSUES FOUND - Budget evolution is correct");
        }
        
        // Risk analysis
        $this->analyzeRisk($budget, $bets);
    }
    
    private function analyzeRisk(BudgetConfiguration $budget, $bets)
    {
        $this->line('');
        $this->info("⚠️  RISK ANALYSIS");
        $this->line('----------------');
        
        $currentBalance = $budget->current_budget;
        $initialBalance = $budget->initial_budget;
        $riskLevel = 'LOW';
        
        // Calculate percentages
        $changePercent = (($currentBalance - $initialBalance) / $initialBalance) * 100;
        $remainingPercent = ($currentBalance / $initialBalance) * 100;
        
        $this->line("Budget Change: " . number_format($changePercent, 1) . "%");
        $this->line("Remaining: " . number_format($remainingPercent, 1) . "% of initial");
        
        if ($currentBalance < $initialBalance * 0.2) {
            $riskLevel = 'CRITICAL';
            $this->error("🚨 CRITICAL: Only " . number_format($remainingPercent, 1) . "% remaining!");
        } elseif ($currentBalance < $initialBalance * 0.5) {
            $riskLevel = 'HIGH';
            $this->error("⚠️  HIGH RISK: " . number_format($remainingPercent, 1) . "% remaining");
        } elseif ($currentBalance < $initialBalance * 0.8) {
            $riskLevel = 'MEDIUM';
            $this->comment("⚠️  MEDIUM RISK: " . number_format($remainingPercent, 1) . "% remaining");
        } else {
            $this->info("✅ LOW RISK: Budget stable");
        }
        
        // Minimum bet check
        $pendingBets = $bets->where('status', 'pending');
        if ($pendingBets->count() > 0) {
            $totalPendingAmount = $pendingBets->sum('amount');
            $availableAfterPending = $currentBalance - $totalPendingAmount;
            
            $this->line("Pending bets: {$pendingBets->count()} (€" . number_format($totalPendingAmount, 2) . ")");
            $this->line("Available after pending: €" . number_format($availableAfterPending, 2));
            
            if ($availableAfterPending < 2.0) {
                $this->error("🚨 WARNING: Very low budget after pending bets resolve!");
            }
        }
        
        // Check if budget should be deactivated
        $minimumBudget = max(2.0, $budget->initial_budget * 0.01);
        if ($currentBalance < $minimumBudget) {
            $this->error("🚨 BUDGET SHOULD BE DEACTIVATED: Below minimum threshold (€{$minimumBudget})");
        }
    }
}