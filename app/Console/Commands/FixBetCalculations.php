<?php

namespace App\Console\Commands;

use App\Models\Bet;
use App\Models\BudgetHistory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixBetCalculations extends Command
{
    protected $signature = 'bets:fix-calculations
                           {--dry-run : Show what would be fixed without making changes}
                           {--budget-id= : Fix only specific budget ID}';
    
    protected $description = 'Fix bet calculations where only profit was added instead of full payout';

    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $budgetId = $this->option('budget-id');
        
        $this->info('🔍 Finding bets with incorrect payout calculations...');
        
        // Find winning bets that may have incorrect budget calculations
        $query = Bet::where('status', 'won')
                   ->whereDate('created_at', '>=', '2025-08-07') // Recent bets only
                   ->with(['match', 'budgetConfiguration']);
        
        if ($budgetId) {
            $query->where('budget_configuration_id', $budgetId);
        }
        
        $winningBets = $query->get();
        
        $this->info("Found {$winningBets->count()} winning bets to check");
        
        $fixedCount = 0;
        $totalAdjustment = 0;
        
        foreach ($winningBets as $bet) {
            // Calculate what the payout should be
            $expectedPayout = $bet->amount * $bet->odds;
            
            // Find the budget history entry for this bet win
            $winHistory = BudgetHistory::where('bet_id', $bet->id)
                                     ->where('type', 'bet_won')
                                     ->first();
            
            if (!$winHistory) {
                continue; // Skip if no win history found
            }
            
            // Check if the amount is incorrect (profit only instead of full payout)
            $recordedAmount = $winHistory->amount;
            $profitOnly = $expectedPayout - $bet->amount; // Just the profit
            
            // If recorded amount matches profit only, it's incorrect
            if (abs($recordedAmount - $profitOnly) < 0.01 && abs($recordedAmount - $expectedPayout) > 0.01) {
                $adjustment = $expectedPayout - $recordedAmount;
                
                $this->line(sprintf(
                    "❌ Bet #%d: %s vs %s | Stake: €%.2f | Odds: %.2f",
                    $bet->id,
                    $bet->match->homeTeam->name ?? 'Home',
                    $bet->match->awayTeam->name ?? 'Away',
                    $bet->amount,
                    $bet->odds
                ));
                $this->line(sprintf(
                    "   Expected payout: €%.2f | Recorded: €%.2f | Missing: €%.2f",
                    $expectedPayout,
                    $recordedAmount,
                    $adjustment
                ));
                
                if (!$dryRun) {
                    DB::transaction(function() use ($bet, $winHistory, $adjustment, $expectedPayout) {
                        // Update the budget history amount
                        $winHistory->amount = $expectedPayout;
                        $winHistory->save();
                        
                        // Update the budget configuration current_budget
                        $budgetConfig = $bet->budgetConfiguration;
                        $budgetConfig->current_budget += $adjustment;
                        $budgetConfig->save();
                        
                        $this->line("   ✅ Fixed: Added missing €{$adjustment} to budget");
                    });
                }
                
                $fixedCount++;
                $totalAdjustment += $adjustment;
            }
        }
        
        if ($dryRun) {
            $this->info("🔍 DRY RUN: Would fix {$fixedCount} bets with total adjustment of €{$totalAdjustment}");
            $this->info("Run without --dry-run to apply fixes");
        } else {
            $this->info("✅ Fixed {$fixedCount} bet calculations with total adjustment of €{$totalAdjustment}");
        }
    }
}