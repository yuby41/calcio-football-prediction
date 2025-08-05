<?php

namespace App\Console\Commands;

use App\Models\BudgetConfiguration;
use App\Models\BudgetHistory;
use App\Models\Bet;
use Illuminate\Console\Command;

class FixBudgetChart extends Command
{
    protected $signature = 'budget:fix-chart {--budget-id= : Specific budget ID to fix} {--all : Fix all budgets}';
    protected $description = 'Fix budget evolution chart by recreating accurate budget history';

    public function handle()
    {
        $budgetId = $this->option('budget-id');
        $all = $this->option('all');

        if (!$budgetId && !$all) {
            $this->error('Please specify either --budget-id=X or --all');
            return 1;
        }

        if ($all) {
            $budgets = BudgetConfiguration::all();
            $this->info("🔧 Fixing chart data for all " . $budgets->count() . " budgets...");
        } else {
            $budgets = BudgetConfiguration::where('id', $budgetId)->get();
            if ($budgets->isEmpty()) {
                $this->error("Budget with ID {$budgetId} not found");
                return 1;
            }
            $this->info("🔧 Fixing chart data for budget ID {$budgetId}...");
        }

        foreach ($budgets as $budget) {
            $this->fixBudgetHistory($budget);
        }

        $this->info("✅ Budget chart data fixed successfully!");
        return 0;
    }

    private function fixBudgetHistory(BudgetConfiguration $budget)
    {
        $this->line("\n📊 Processing budget: {$budget->name} (ID: {$budget->id})");

        // Clear existing incorrect history
        $deletedCount = BudgetHistory::where('budget_configuration_id', $budget->id)->delete();
        $this->line("   🗑️  Deleted {$deletedCount} existing history entries");

        // Create initial deposit entry
        BudgetHistory::create([
            'budget_configuration_id' => $budget->id,
            'bet_id' => null,
            'amount' => $budget->initial_budget,
            'balance_before' => 0,
            'balance_after' => $budget->initial_budget,
            'type' => 'deposit',
            'description' => 'Depósito inicial',
            'created_at' => $budget->created_at,
            'updated_at' => $budget->created_at,
        ]);

        // Get all bets for this budget in chronological order
        $bets = Bet::where('budget_configuration_id', $budget->id)
            ->orderBy('placed_at')
            ->get();

        $this->line("   📈 Processing " . $bets->count() . " bets...");

        $currentBalance = $budget->initial_budget;
        $progressBar = $this->output->createProgressBar($bets->count());

        foreach ($bets as $bet) {
            // 1. Bet placed - subtract amount
            $balanceBefore = $currentBalance;
            $currentBalance -= $bet->amount;

            BudgetHistory::create([
                'budget_configuration_id' => $budget->id,
                'bet_id' => $bet->id,
                'amount' => -$bet->amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $currentBalance,
                'type' => 'bet_placed',
                'description' => "Apuesta en " . ($bet->match->homeTeam->name ?? 'Unknown') . " vs " . ($bet->match->awayTeam->name ?? 'Unknown'),
                'created_at' => $bet->placed_at,
                'updated_at' => $bet->placed_at,
            ]);

            // 2. If bet is resolved, add result
            if ($bet->status === 'won') {
                $balanceBefore = $currentBalance;
                $winAmount = $bet->amount * $bet->odds; // Total payout
                $currentBalance += $winAmount;

                BudgetHistory::create([
                    'budget_configuration_id' => $budget->id,
                    'bet_id' => $bet->id,
                    'amount' => $winAmount,
                    'balance_before' => $balanceBefore,
                    'balance_after' => $currentBalance,
                    'type' => 'bet_won',
                    'description' => "Apuesta ganada: +€" . number_format($bet->actual_profit ?? ($winAmount - $bet->amount), 2),
                    'created_at' => $bet->resolved_at ?? $bet->placed_at->addHours(2),
                    'updated_at' => $bet->resolved_at ?? $bet->placed_at->addHours(2),
                ]);
            } elseif ($bet->status === 'lost') {
                // For lost bets, no additional balance change (amount was already subtracted)
                BudgetHistory::create([
                    'budget_configuration_id' => $budget->id,
                    'bet_id' => $bet->id,
                    'amount' => 0,
                    'balance_before' => $currentBalance,
                    'balance_after' => $currentBalance,
                    'type' => 'bet_lost',
                    'description' => "Apuesta perdida: -€" . number_format($bet->amount, 2),
                    'created_at' => $bet->resolved_at ?? $bet->placed_at->addHours(2),
                    'updated_at' => $bet->resolved_at ?? $bet->placed_at->addHours(2),
                ]);
            }

            $progressBar->advance();
        }

        $progressBar->finish();

        // Update the budget's current balance to match the calculated balance
        $budget->update(['current_budget' => $currentBalance]);

        $this->line("\n   ✅ Budget {$budget->name}:");
        $this->line("      💰 Final balance: €" . number_format($currentBalance, 2));
        $this->line("      📊 History entries created: " . ($bets->count() * 2 + 1)); // Initial + bets placed + bets resolved
        $this->line("      🎯 Budget updated successfully");
    }
}