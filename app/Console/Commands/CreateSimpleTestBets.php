<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CreateSimpleTestBets extends Command
{
    protected $signature = 'budget:simple-test-bets {budget_id} {--count=10}';
    
    protected $description = 'Create simple test bets using raw SQL';

    public function handle()
    {
        $budgetId = $this->argument('budget_id');
        $count = $this->option('count');
        
        try {
            // Check if budget exists
            $budget = DB::table('budget_configurations')->where('id', $budgetId)->first();
            if (!$budget) {
                $this->error("Budget configuration with ID {$budgetId} not found.");
                return Command::FAILURE;
            }

            $this->info("Creando {$count} apuestas de prueba para: {$budget->name}");
            
            // Create test teams if they don't exist
            $this->createTestTeams();
            
            // Create test matches
            $this->createTestMatches();
            
            // Get some matches to use
            $matches = DB::table('matches')->limit($count)->get();
            if ($matches->count() === 0) {
                $this->error("No hay partidos disponibles.");
                return Command::FAILURE;
            }

            $currentBalance = $budget->current_budget;
            $betTypes = ['match_result', 'both_teams_score', 'over_under_2_5'];
            $outcomes = ['home', 'draw', 'away', 'yes', 'no', 'over', 'under'];
            
            for ($i = 0; $i < $count; $i++) {
                $match = $matches[$i % $matches->count()];
                $betType = $betTypes[array_rand($betTypes)];
                
                // Simulate bet amounts
                $amount = 5.0 * (1 + ($i % 3));
                
                // Random results: 60% win rate
                $isWin = rand(1, 100) <= 60;
                $odds = rand(150, 300) / 100;
                
                $actualProfit = $isWin ? ($amount * $odds) - $amount : -$amount;
                $balanceBefore = $currentBalance;
                $currentBalance += $actualProfit;
                
                $placedAt = Carbon::now()->subDays(rand(1, 30))->format('Y-m-d H:i:s');
                $resolvedAt = Carbon::now()->subDays(rand(0, 29))->format('Y-m-d H:i:s');
                
                // Insert bet
                $betId = DB::table('bets')->insertGetId([
                    'budget_configuration_id' => $budgetId,
                    'match_id' => $match->id,
                    'bet_type' => $betType,
                    'amount' => $amount,
                    'odds' => $odds,
                    'confidence' => rand(60, 85),
                    'potential_profit' => ($amount * $odds) - $amount,
                    'actual_profit' => $actualProfit,
                    'status' => $isWin ? 'won' : 'lost',
                    'budget_before' => $balanceBefore,
                    'budget_after' => $currentBalance,
                    'sequence_step' => $isWin ? 0 : min($i % 5, 10),
                    'placed_at' => $placedAt,
                    'resolved_at' => $resolvedAt,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Insert budget history
                DB::table('budget_history')->insert([
                    'budget_configuration_id' => $budgetId,
                    'bet_id' => $betId,
                    'amount' => $actualProfit,
                    'balance_before' => $currentBalance - $actualProfit,
                    'balance_after' => $currentBalance,
                    'type' => $isWin ? 'bet_won' : 'bet_lost',
                    'description' => "Apuesta {$betType} - Partido {$match->id}",
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $this->line(sprintf(
                    "✓ Apuesta #%d: €%.2f %s (%s)",
                    $i + 1,
                    $amount,
                    $isWin ? 'GANADA' : 'PERDIDA',
                    $actualProfit >= 0 ? "+€" . number_format($actualProfit, 2) : "€" . number_format($actualProfit, 2)
                ));
            }

            // Update budget current balance
            DB::table('budget_configurations')
                ->where('id', $budgetId)
                ->update(['current_budget' => $currentBalance, 'updated_at' => now()]);
            
            $netProfit = $currentBalance - $budget->initial_budget;
            $wonBets = DB::table('bets')->where('budget_configuration_id', $budgetId)->where('status', 'won')->count();
            $totalBets = DB::table('bets')->where('budget_configuration_id', $budgetId)->count();
            $winRate = $totalBets > 0 ? ($wonBets / $totalBets) * 100 : 0;
            
            $this->info("\n📊 Resumen:");
            $this->line("   Balance inicial: €" . number_format($budget->initial_budget, 2));
            $this->line("   Balance actual: €" . number_format($currentBalance, 2));
            $this->line("   Beneficio neto: " . ($netProfit >= 0 ? "+€" : "€") . number_format($netProfit, 2));
            $this->line("   Win Rate: " . number_format($winRate, 1) . "%");
            $this->line("\nVisita: http://localhost:8000/budget/{$budgetId}");

            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function createTestTeams()
    {
        $teams = [
            ['name' => 'Real Madrid', 'external_id' => 'test_rm', 'short_name' => 'Real Madrid'],
            ['name' => 'FC Barcelona', 'external_id' => 'test_fcb', 'short_name' => 'Barcelona'],
            ['name' => 'Manchester United', 'external_id' => 'test_mu', 'short_name' => 'Man United'],
            ['name' => 'Liverpool FC', 'external_id' => 'test_lfc', 'short_name' => 'Liverpool'],
        ];

        foreach ($teams as $teamData) {
            DB::table('teams')->insertOrIgnore([
                'name' => $teamData['name'],
                'external_id' => $teamData['external_id'],
                'short_name' => $teamData['short_name'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function createTestMatches()
    {
        $teams = DB::table('teams')->limit(4)->get();
        if ($teams->count() < 4) return;

        for ($i = 0; $i < 5; $i++) {
            $homeTeam = $teams[rand(0, 3)];
            $awayTeam = $teams[rand(0, 3)];
            
            // Ensure different teams
            while ($awayTeam->id == $homeTeam->id) {
                $awayTeam = $teams[rand(0, 3)];
            }
            
            DB::table('matches')->insertOrIgnore([
                'external_id' => 'test_match_' . $i,
                'home_team_id' => $homeTeam->id,
                'away_team_id' => $awayTeam->id,
                'match_date' => Carbon::now()->subDays(rand(1, 30))->format('Y-m-d H:i:s'),
                'status' => 'finished',
                'home_goals' => rand(0, 4),
                'away_goals' => rand(0, 4),
                'league' => 'Test League',
                'season' => '2024',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}