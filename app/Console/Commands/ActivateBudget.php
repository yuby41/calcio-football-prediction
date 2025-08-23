<?php

namespace App\Console\Commands;

use App\Models\BudgetConfiguration;
use Illuminate\Console\Command;

class ActivateBudget extends Command
{
    protected $signature = 'budget:activate {id : Budget ID to activate}';
    protected $description = 'Activate a specific budget configuration';

    public function handle()
    {
        $budgetId = $this->argument('id');
        $budget = BudgetConfiguration::find($budgetId);

        if (!$budget) {
            $this->error("Budget with ID {$budgetId} not found.");
            return 1;
        }

        $this->info("Budget: {$budget->name} (ID: {$budgetId})");
        $this->info("Current status: " . ($budget->is_active ? 'ACTIVE' : 'INACTIVE'));
        $this->info("Current balance: €{$budget->current_budget}");
        $this->info("Strategy: {$budget->strategy}");

        if (!$budget->is_active) {
            $budget->is_active = true;
            $budget->save();
            $this->info("✅ Budget ACTIVATED successfully!");
        } else {
            $this->info("✅ Budget is already ACTIVE.");
        }

        return 0;
    }
}