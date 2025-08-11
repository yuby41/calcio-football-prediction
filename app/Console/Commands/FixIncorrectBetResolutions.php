<?php

namespace App\Console\Commands;

use App\Models\Bet;
use App\Models\BudgetHistory;
use Illuminate\Console\Command;

class FixIncorrectBetResolutions extends Command
{
    protected $signature = 'bets:fix-incorrect-resolutions {--dry-run : Show what would be fixed without actually fixing}';
    protected $description = 'Fix bets that were incorrectly resolved due to missing first half data';

    public function handle()
    {
        $dryRun = $this->option('dry-run');
        
        $this->info($dryRun ? '🔍 DRY RUN: Analyzing incorrect bet resolutions...' : '🔧 Fixing incorrect bet resolutions...');

        // Find bets that were marked as lost but should be won due to first half data now available
        $incorrectBets = Bet::where('status', 'pending')
            ->whereIn('bet_type', ['over_0_5_first_half', 'first_half_over_0_5'])
            ->whereHas('match', function($query) {
                $query->where('status', 'finished')
                      ->whereNotNull('home_goals_first_half')
                      ->whereNotNull('away_goals_first_half');
            })
            ->with(['match.homeTeam', 'match.awayTeam', 'budgetConfiguration'])
            ->get();

        // Also find bets that have incorrect budget history
        $incorrectHistoryBets = collect();
        
        // Find bet_lost entries where the bet should actually be won
        $incorrectHistories = BudgetHistory::where('type', 'bet_lost')
            ->whereNotNull('bet_id')
            ->whereDate('created_at', '>=', '2025-08-07') // Recent bets only
            ->with(['bet.match'])
            ->get();

        foreach ($incorrectHistories as $history) {
            $bet = $history->bet;
            if ($bet && 
                in_array($bet->bet_type, ['over_0_5_first_half', 'first_half_over_0_5']) &&
                $bet->match && 
                $bet->match->status === 'finished') {
                
                // Check if bet should actually be won
                $result = $bet->calculateResult();
                if ($result['status'] === 'won') {
                    $incorrectHistoryBets->push($bet);
                }
            }
        }

        $allIncorrectBets = $incorrectBets->concat($incorrectHistoryBets)->unique('id');

        if ($allIncorrectBets->isEmpty()) {
            $this->info('✅ No incorrect bet resolutions found');
            return Command::SUCCESS;
        }

        $this->info("Found {$allIncorrectBets->count()} bets with incorrect resolutions:");

        $fixed = 0;
        foreach ($allIncorrectBets as $bet) {
            $match = $bet->match;
            $result = $bet->calculateResult();
            
            $firstHalfTotal = ($match->home_goals_first_half ?? 0) + ($match->away_goals_first_half ?? 0);
            $shouldWin = $firstHalfTotal > 0.5;
            
            $this->line("\nBet ID {$bet->id}: {$match->homeTeam->name} vs {$match->awayTeam->name}");
            $this->line("  First Half: {$match->home_goals_first_half}-{$match->away_goals_first_half} ({$firstHalfTotal} goals)");
            $this->line("  Should be: " . ($shouldWin ? '✅ WON' : '❌ LOST'));
            $this->line("  Current Status: {$bet->status}");
            $this->line("  Amount: €{$bet->amount} @ {$bet->odds}");

            if (!$dryRun) {
                $this->fixBetResolution($bet, $result);
                $fixed++;
            }
        }

        if ($dryRun) {
            $this->info("\n🔍 DRY RUN COMPLETED:");
            $this->info("   - {$allIncorrectBets->count()} bets would be fixed");
        } else {
            $this->info("\n✅ Fixed {$fixed} incorrect bet resolutions!");
        }

        return Command::SUCCESS;
    }

    private function fixBetResolution(Bet $bet, array $result)
    {
        try {
            // Remove incorrect history entries
            BudgetHistory::where('bet_id', $bet->id)
                ->whereIn('type', ['bet_lost', 'bet_won'])
                ->delete();

            // Update bet status
            $bet->status = $result['status'];
            $bet->actual_profit = $result['profit'];
            $bet->resolved_at = now();
            $bet->save();

            // Create correct budget history
            $budgetConfig = $bet->budgetConfiguration;
            $balanceBefore = $budgetConfig->current_budget;
            $balanceAfter = $balanceBefore + $result['profit'];

            BudgetHistory::create([
                'budget_configuration_id' => $budgetConfig->id,
                'bet_id' => $bet->id,
                'type' => $result['status'] === 'won' ? 'bet_won' : 'bet_lost',
                'amount' => $result['profit'],
                'description' => "Apuesta {$result['status']}: {$bet->bet_type} - {$bet->match->homeTeam->name} vs {$bet->match->awayTeam->name}",
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter
            ]);

            // Update budget balance
            $budgetConfig->current_budget = $balanceAfter;
            $budgetConfig->save();

            $this->info("  ✅ Fixed - New balance: €{$balanceAfter}");

        } catch (\Exception $e) {
            $this->error("  ❌ Error fixing bet {$bet->id}: " . $e->getMessage());
        }
    }
}