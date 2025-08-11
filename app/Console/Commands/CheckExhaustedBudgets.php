<?php

namespace App\Console\Commands;

use App\Models\BudgetConfiguration;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckExhaustedBudgets extends Command
{
    protected $signature = 'budgets:check-exhausted {--deactivate : Automatically deactivate exhausted budgets}';
    protected $description = 'Check for exhausted budgets and optionally deactivate them';

    public function handle()
    {
        $deactivate = $this->option('deactivate');
        
        $this->info('🔍 Checking for exhausted budgets...');

        $activeBudgets = BudgetConfiguration::where('is_active', true)->get();
        
        $exhaustedCount = 0;
        $deactivatedCount = 0;

        foreach ($activeBudgets as $budget) {
            $minimumThreshold = $budget->getMinimumBetAmount();
            
            if ($budget->isExhausted()) {
                $exhaustedCount++;
                
                $this->warn("❌ Budget '{$budget->name}' is exhausted:");
                $this->line("   Current: €{$budget->current_budget}");
                $this->line("   Minimum: €{$minimumThreshold}");
                $this->line("   Initial: €{$budget->initial_budget}");
                $this->line("   Loss: €" . number_format($budget->initial_budget - $budget->current_budget, 2));
                
                if ($deactivate) {
                    $budget->update(['is_active' => false]);
                    $deactivatedCount++;
                    $this->info("   ✅ Deactivated automatically");
                    
                    Log::info("Budget automatically deactivated due to exhaustion", [
                        'budget_id' => $budget->id,
                        'budget_name' => $budget->name,
                        'current_budget' => $budget->current_budget,
                        'minimum_threshold' => $minimumThreshold
                    ]);
                } else {
                    $this->line("   💡 Use --deactivate to automatically disable this budget");
                }
                
                $this->line('');
            } else {
                // Check if budget is close to exhaustion (within 5% of minimum)
                $warningThreshold = $minimumThreshold * 1.2; // 20% above minimum
                if ($budget->current_budget <= $warningThreshold) {
                    $this->warn("⚠️  Budget '{$budget->name}' is close to exhaustion:");
                    $this->line("   Current: €{$budget->current_budget}");
                    $this->line("   Warning threshold: €{$warningThreshold}");
                }
            }
        }

        if ($exhaustedCount === 0) {
            $this->info('✅ No exhausted budgets found');
        } else {
            $this->info("📊 Summary:");
            $this->info("   Exhausted budgets found: {$exhaustedCount}");
            if ($deactivate) {
                $this->info("   Budgets deactivated: {$deactivatedCount}");
            }
        }

        return 0;
    }
}