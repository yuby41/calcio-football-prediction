<?php

namespace App\Console\Commands;

use App\Models\BudgetConfiguration;
use Illuminate\Console\Command;

class SyncBudgetAvailableBalances extends Command
{
    protected $signature = 'budgets:sync-available-balances {--budget=* : Specific budget IDs to sync} {--dry-run : Show what would be synced without making changes}';
    protected $description = 'Sync all budget current_budget values to reflect correct available balance (resolved bets minus pending commitments)';

    public function handle()
    {
        $this->info('🔄 SYNCING BUDGET AVAILABLE BALANCES');
        $this->info('====================================');

        $budgetIds = $this->option('budget');
        $dryRun = $this->option('dry-run');
        
        if ($dryRun) {
            $this->warn('🔍 DRY RUN MODE - No changes will be made');
            $this->line('');
        }

        $query = BudgetConfiguration::query();
        
        if (!empty($budgetIds)) {
            $query->whereIn('id', $budgetIds);
        }

        $budgets = $query->get();
        
        if ($budgets->isEmpty()) {
            $this->error('No budgets found');
            return 1;
        }

        $totalSynced = 0;
        $totalCorrected = 0;
        
        foreach ($budgets as $budget) {
            $this->line('');
            $this->info("💰 Budget: {$budget->name} (ID: {$budget->id})");
            $this->line('----------------------------------------');
            
            $this->line("Current budget: €{$budget->current_budget}");
            
            $resolvedBalance = $budget->getBalanceAfterResolvedBets();
            $pendingAmount = $budget->getPendingBetsAmount();
            $availableBalance = $budget->getAvailableBalance();
            
            $this->line("Balance after resolved bets: €" . number_format($resolvedBalance, 2));
            $this->line("Pending bets amount: €" . number_format($pendingAmount, 2));
            $this->line("Should be (available): €" . number_format($availableBalance, 2));
            
            $difference = abs($budget->current_budget - $availableBalance);
            
            if ($difference > 0.01) {
                $this->warn("❌ NEEDS SYNC: Difference of €" . number_format($difference, 2));
                
                if (!$dryRun) {
                    $corrected = $budget->syncAvailableBalance();
                    if ($corrected) {
                        $this->info("✅ SYNCED to €" . number_format($budget->current_budget, 2));
                        $totalCorrected++;
                    }
                } else {
                    $this->info("🔍 WOULD SYNC to €" . number_format($availableBalance, 2));
                    $totalCorrected++;
                }
            } else {
                $this->info("✅ ALREADY CORRECT");
            }
            
            $totalSynced++;
        }

        $this->line('');
        $this->info('📊 SUMMARY');
        $this->info('===========');
        $this->line("Budgets processed: {$totalSynced}");
        $this->line("Budgets " . ($dryRun ? 'needing sync' : 'synced') . ": {$totalCorrected}");

        if ($dryRun) {
            $this->warn('No actual changes were made (dry run mode)');
        } else {
            $this->info('Budget available balances have been synced!');
        }

        return 0;
    }
}