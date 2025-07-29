<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CreateHistoricalBets extends Command
{
    protected $signature = 'budget:historical-bets {budget_id} {--days=60}';
    
    protected $description = 'Create historical bets spread over multiple months';

    public function handle()
    {
        $budgetId = $this->argument('budget_id');
        $days = $this->option('days');
        
        try {
            $budget = DB::table('budget_configurations')->where('id', $budgetId)->first();
            if (!$budget) {
                $this->error("Budget configuration with ID {$budgetId} not found.");
                return Command::FAILURE;
            }

            $this->info("Creando apuestas históricas para: {$budget->name} (últimos {$days} días)");
            
            // Get existing matches
            $matches = DB::table('matches')->limit(10)->get();
            if ($matches->count() === 0) {
                $this->error("No hay partidos disponibles.");
                return Command::FAILURE;
            }

            $currentBalance = $budget->current_budget;
            $betTypes = ['match_result', 'both_teams_score', 'over_under_2_5'];
            
            // Create bets for the last X days
            for ($dayOffset = $days; $dayOffset >= 1; $dayOffset--) {
                $numBetsThisDay = rand(0, 3); // 0-3 bets per day
                
                for ($betIndex = 0; $betIndex < $numBetsThisDay; $betIndex++) {
                    $match = $matches[rand(0, $matches->count() - 1)];
                    $betType = $betTypes[array_rand($betTypes)];
                    
                    // Vary bet amounts based on day (simulate strategy evolution)
                    $baseAmount = 5.0 + ($days - $dayOffset) * 0.1; // Increase over time
                    $amount = $baseAmount * (1 + rand(0, 2));
                    
                    // Better win rate for recent bets (learning effect)
                    $winProbability = min(45 + (($days - $dayOffset) / $days) * 30, 75); // 45% to 75%
                    $isWin = rand(1, 100) <= $winProbability;
                    
                    $odds = rand(150, 350) / 100; // 1.5 to 3.5
                    $actualProfit = $isWin ? ($amount * $odds) - $amount : -$amount;
                    
                    $balanceBefore = $currentBalance;
                    $currentBalance += $actualProfit;
                    
                    // Make sure balance doesn't go negative
                    if ($currentBalance < 0) {
                        $currentBalance = $balanceBefore;
                        continue;
                    }
                    
                    $betDate = Carbon::now()->subDays($dayOffset)->addHours(rand(8, 20))->addMinutes(rand(0, 59));
                    $resolveDate = $betDate->copy()->addHours(rand(2, 24));
                    
                    // Insert bet
                    $betId = DB::table('bets')->insertGetId([
                        'budget_configuration_id' => $budgetId,
                        'match_id' => $match->id,
                        'bet_type' => $betType,
                        'amount' => round($amount, 2),
                        'odds' => $odds,
                        'confidence' => rand(60, 90),
                        'potential_profit' => round(($amount * $odds) - $amount, 2),
                        'actual_profit' => round($actualProfit, 2),
                        'status' => $isWin ? 'won' : 'lost',
                        'budget_before' => round($balanceBefore, 2),
                        'budget_after' => round($currentBalance, 2),
                        'sequence_step' => $isWin ? 0 : rand(1, 5),
                        'placed_at' => $betDate->format('Y-m-d H:i:s'),
                        'resolved_at' => $resolveDate->format('Y-m-d H:i:s'),
                        'created_at' => $betDate->format('Y-m-d H:i:s'),
                        'updated_at' => $resolveDate->format('Y-m-d H:i:s'),
                    ]);

                    // Insert budget history
                    DB::table('budget_history')->insert([
                        'budget_configuration_id' => $budgetId,
                        'bet_id' => $betId,
                        'amount' => round($actualProfit, 2),
                        'balance_before' => round($balanceBefore, 2),
                        'balance_after' => round($currentBalance, 2),
                        'type' => $isWin ? 'bet_won' : 'bet_lost',
                        'description' => "Apuesta histórica {$betType}",
                        'created_at' => $resolveDate->format('Y-m-d H:i:s'),
                        'updated_at' => $resolveDate->format('Y-m-d H:i:s'),
                    ]);

                    $this->line(sprintf(
                        "📅 %s: €%.2f %s (Balance: €%.2f)",
                        $betDate->format('d/m'),
                        $amount,
                        $isWin ? 'WIN' : 'LOSE',
                        $currentBalance
                    ));
                }
            }

            // Update budget current balance
            DB::table('budget_configurations')
                ->where('id', $budgetId)
                ->update(['current_budget' => $currentBalance, 'updated_at' => now()]);
            
            $totalBets = DB::table('bets')->where('budget_configuration_id', $budgetId)->count();
            $wonBets = DB::table('bets')->where('budget_configuration_id', $budgetId)->where('status', 'won')->count();
            $winRate = $totalBets > 0 ? ($wonBets / $totalBets) * 100 : 0;
            $netProfit = $currentBalance - $budget->initial_budget;
            
            $this->info("\n📊 Resumen histórico:");
            $this->line("   Período: {$days} días");
            $this->line("   Total de apuestas: {$totalBets}");
            $this->line("   Balance final: €" . number_format($currentBalance, 2));
            $this->line("   Beneficio neto: " . ($netProfit >= 0 ? "+€" : "€") . number_format($netProfit, 2));
            $this->line("   Win Rate: " . number_format($winRate, 1) . "%");
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}