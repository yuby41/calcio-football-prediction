<?php

namespace App\Console\Commands;

use App\Models\BudgetConfiguration;
use App\Models\BudgetHistory;
use Illuminate\Console\Command;

class RegenerateBudgetHistory extends Command
{
    protected $signature = 'budgets:regenerate-history {--budget=* : Specific budget names or IDs} {--dry-run : Show what would be regenerated}';
    protected $description = 'Regenerate budget history based on actual bet evolution';

    public function handle()
    {
        $this->info('🔄 REGENERATING BUDGET HISTORY');
        $this->info('==============================');

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

        $totalRegenerated = 0;
        
        foreach ($budgets as $budget) {
            $this->line('');
            $this->info("💰 Budget: {$budget->name} (ID: {$budget->id})");
            $this->line('----------------------------------------');
            
            $result = $this->regenerateBudgetHistory($budget, $dryRun);
            
            if ($result['regenerated']) {
                $totalRegenerated++;
                $this->info("✅ Regenerated - {$result['records']} history records created");
            } else {
                $this->comment("ℹ️  No history to regenerate (no bets or already correct)");
            }
        }

        $this->line('');
        $this->info('📊 SUMMARY');
        $this->info('===========');
        $this->line("Budgets processed: {$budgets->count()}");
        $this->line("Budgets regenerated: {$totalRegenerated}");

        if ($dryRun) {
            $this->warn('No actual changes were made (dry run mode)');
        } else {
            $this->info('Budget history has been regenerated!');
        }

        return 0;
    }

    private function regenerateBudgetHistory(BudgetConfiguration $budget, bool $dryRun): array
    {
        $bets = $budget->bets()->orderBy('id')->get();
        
        $this->line("Total bets: {$bets->count()}");
        
        if ($bets->isEmpty()) {
            return ['regenerated' => false, 'records' => 0];
        }
        
        // Delete existing history for this budget
        $oldRecords = $budget->budgetHistory()->count();
        $this->line("Removing {$oldRecords} old history records...");
        
        if (!$dryRun) {
            $budget->budgetHistory()->delete();
        }
        
        // Regenerate history based on actual bet evolution
        $newRecords = 0;
        $currentBalance = $budget->initial_budget;
        
        // Create initial record
        if (!$dryRun) {
            BudgetHistory::create([
                'budget_configuration_id' => $budget->id,
                'action' => 'initial',
                'amount' => 0,
                'balance_before' => $currentBalance,
                'balance_after' => $currentBalance,
                'description' => 'Configuración inicial del budget',
                'created_at' => $budget->created_at,
                'updated_at' => $budget->created_at
            ]);
        }
        $newRecords++;
        
        $this->line("  ✓ Initial record: €{$currentBalance}");
        
        // Process each bet
        foreach ($bets as $bet) {
            if ($bet->status === 'pending') {
                // Don't create history records for pending bets
                continue;
            }
            
            $balanceBefore = $currentBalance;
            $action = '';
            $amount = 0;
            $description = '';
            
            if ($bet->status === 'won') {
                $amount = $bet->actual_profit;
                $currentBalance = $bet->budget_after;
                $action = 'bet_won';
                $description = "Apuesta ganada: {$bet->match->homeTeam->name} vs {$bet->match->awayTeam->name} ({$bet->bet_type})";
            } elseif ($bet->status === 'lost') {
                $amount = $bet->actual_profit; // This will be negative
                $currentBalance = $bet->budget_after;
                $action = 'bet_lost';
                $description = "Apuesta perdida: {$bet->match->homeTeam->name} vs {$bet->match->awayTeam->name} ({$bet->bet_type})";
            } elseif ($bet->status === 'cancelled') {
                $amount = 0;
                $currentBalance = $bet->budget_after;
                $action = 'bet_cancelled';
                $description = "Apuesta cancelada: {$bet->match->homeTeam->name} vs {$bet->match->awayTeam->name} ({$bet->bet_type})";
            }
            
            if ($action && !$dryRun) {
                BudgetHistory::create([
                    'budget_configuration_id' => $budget->id,
                    'action' => $action,
                    'amount' => $amount,
                    'balance_before' => $balanceBefore,
                    'balance_after' => $currentBalance,
                    'description' => $description,
                    'related_bet_id' => $bet->id,
                    'created_at' => $bet->resolved_at ?: $bet->placed_at,
                    'updated_at' => $bet->resolved_at ?: $bet->placed_at
                ]);
            }
            
            if ($action) {
                $newRecords++;
                $this->line("  ✓ {$action}: €{$amount} → €{$currentBalance}");
            }
        }
        
        $this->line("Generated {$newRecords} new history records");
        
        // Verify final balance matches
        if (abs($currentBalance - $budget->current_budget) > 0.01) {
            $this->error("  ❌ Final balance mismatch: History €{$currentBalance} vs Budget €{$budget->current_budget}");
        } else {
            $this->info("  ✅ Final balance verified: €{$currentBalance}");
        }
        
        return [
            'regenerated' => true,
            'records' => $newRecords
        ];
    }
}