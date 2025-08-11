<?php

namespace App\Console\Commands;

use App\Models\Bet;
use App\Models\BudgetHistory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AutoResolveBets extends Command
{
    protected $signature = 'bets:auto-resolve
                           {--force : Force resolution even with missing data}
                           {--estimate-missing : Estimate missing first-half data}
                           {--dry-run : Show what would be resolved}';
    
    protected $description = 'Automatically resolve all pending bets with smart data estimation';

    public function handle()
    {
        $force = $this->option('force');
        $estimateMissing = $this->option('estimate-missing');
        $dryRun = $this->option('dry-run');
        
        $this->info($dryRun ? '🔍 DRY RUN: Auto-resolving pending bets...' : '🤖 Auto-resolving pending bets...');
        
        // Get all pending bets for finished matches
        $pendingBets = Bet::whereHas('match', function($query) {
            $query->where('status', 'finished');
        })
        ->where('status', 'pending')
        ->with(['match.homeTeam', 'match.awayTeam', 'budgetConfiguration'])
        ->get();
        
        if ($pendingBets->isEmpty()) {
            $this->info('✅ No pending bets to resolve');
            return 0;
        }
        
        $this->info("Found {$pendingBets->count()} pending bets");
        
        $resolved = 0;
        $estimated = 0;
        
        foreach ($pendingBets as $bet) {
            $match = $bet->match;
            
            // Check if first-half data is missing
            if (is_null($match->home_goals_first_half) || is_null($match->away_goals_first_half)) {
                if ($estimateMissing) {
                    $this->estimateFirstHalfData($match);
                    $estimated++;
                    $this->line("📊 Estimated first-half data for {$match->homeTeam->name} vs {$match->awayTeam->name}");
                } elseif (!$force) {
                    $this->line("⏭️ Skipping bet #{$bet->id} - missing first-half data (use --estimate-missing)");
                    continue;
                }
            }
            
            // Calculate result
            try {
                $result = $bet->calculateResult();
                
                if ($result['status'] === 'pending') {
                    $this->line("⚠️ Bet #{$bet->id} still pending after calculation");
                    continue;
                }
                
                if (!$dryRun) {
                    DB::transaction(function() use ($bet, $result) {
                        // Update bet status
                        $bet->status = $result['status'];
                        $bet->save();
                        
                        // Create budget history
                        $budget = $bet->budgetConfiguration;
                        $balanceBefore = $budget->current_budget;
                        $balanceAfter = $balanceBefore + $result['profit'];
                        
                        BudgetHistory::create([
                            'budget_configuration_id' => $bet->budget_configuration_id,
                            'type' => $result['status'] === 'won' ? 'bet_won' : 'bet_lost',
                            'amount' => $result['profit'],
                            'balance_before' => $balanceBefore,
                            'balance_after' => $balanceAfter,
                            'bet_id' => $bet->id,
                        ]);
                        
                        // Update budget
                        $budget->current_budget = $balanceAfter;
                        $budget->save();
                    });
                }
                
                $this->line(sprintf(
                    "%s Bet #%d: %s vs %s | %s | %s€%.2f",
                    $dryRun ? '🔍' : '✅',
                    $bet->id,
                    $match->homeTeam->name,
                    $match->awayTeam->name,
                    $result['status'],
                    $result['profit'] >= 0 ? '+' : '',
                    $result['profit']
                ));
                
                $resolved++;
                
            } catch (\Exception $e) {
                $this->error("❌ Error resolving bet #{$bet->id}: " . $e->getMessage());
                Log::error("Auto-resolve bet error", [
                    'bet_id' => $bet->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }
        
        $this->info(sprintf(
            "%s %d bets resolved%s%s",
            $dryRun ? '🔍 Would resolve' : '✅ Resolved',
            $resolved,
            $estimated > 0 ? " | {$estimated} first-half data estimated" : '',
            $dryRun ? ' (use without --dry-run to apply)' : ''
        ));
        
        return 0;
    }
    
    private function estimateFirstHalfData($match)
    {
        // Smart estimation based on full-time score patterns
        $homeGoals = $match->home_goals;
        $awayGoals = $match->away_goals;
        $totalGoals = $homeGoals + $awayGoals;
        
        // Statistical estimation (about 45% of goals scored in first half)
        $firstHalfProbability = 0.45;
        $estimatedFirstHalfTotal = max(0, round($totalGoals * $firstHalfProbability));
        
        // Distribute goals proportionally
        if ($totalGoals > 0) {
            $homeRatio = $homeGoals / $totalGoals;
            $homeFirstHalf = round($estimatedFirstHalfTotal * $homeRatio);
            $awayFirstHalf = $estimatedFirstHalfTotal - $homeFirstHalf;
        } else {
            $homeFirstHalf = 0;
            $awayFirstHalf = 0;
        }
        
        // Ensure non-negative
        $homeFirstHalf = max(0, $homeFirstHalf);
        $awayFirstHalf = max(0, $awayFirstHalf);
        
        // Update match
        $match->home_goals_first_half = $homeFirstHalf;
        $match->away_goals_first_half = $awayFirstHalf;
        $match->save();
    }
}