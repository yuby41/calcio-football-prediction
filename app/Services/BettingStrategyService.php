<?php

namespace App\Services;

use App\Models\BudgetConfiguration;
use App\Models\Bet;
use App\Models\BudgetHistory;
use App\Models\FootballMatch;

class BettingStrategyService
{
    public function calculateBetAmount(BudgetConfiguration $config, FootballMatch $match, string $betType, float $confidence): float
    {
        $currentBudget = $config->current_budget;
        
        // CRITICAL FIX: Stop betting if target profit reached
        if ($config->hasReachedTargetProfit()) {
            \Log::info("Target profit reached - stopping betting", [
                'budget_id' => $config->id,
                'current_profit' => $config->getNetProfitAttribute(),
                'target_profit' => $config->target_profit
            ]);
            return 0; // No betting when target is achieved
        }
        
        // CRITICAL FIX: Stop betting if budget is below minimum threshold
        $minimumBudget = max(2.0, $config->initial_budget * 0.01); // At least €2 or 1% of initial
        if ($currentBudget < $minimumBudget) {
            \Log::info("Budget exhausted - stopping betting", [
                'budget_id' => $config->id,
                'current_budget' => $currentBudget,
                'minimum_threshold' => $minimumBudget
            ]);
            return 0; // No betting when budget is too low
        }
        
        $maxBetAmount = ($currentBudget * $config->max_bet_percentage) / 100;

        return match($config->strategy) {
            'mansaniello' => $this->calculateMansaniello($config, $confidence, $maxBetAmount),
            'fibonacci' => $this->calculateFibonacci($config, $maxBetAmount),
            'martingale' => $this->calculateMartingale($config, $maxBetAmount),
            'fixed' => $this->calculateFixed($config, $maxBetAmount),
            'percentage' => $this->calculatePercentage($config, $confidence, $maxBetAmount),
            default => $this->calculateFixed($config, $maxBetAmount),
        };
    }

    private function calculateMansaniello(BudgetConfiguration $config, float $confidence, float $maxBetAmount): float
    {
        $parameters = $config->strategy_parameters ?? [];
        $baseAmount = $parameters['base_amount'] ?? ($config->initial_budget * 0.02); // 2% por defecto
        $maxSequence = $parameters['max_sequence'] ?? 10;
        
        // Secuencia Mansaniello: 1, 1, 2, 2, 3, 4, 5, 7, 9, 12...
        $sequence = [1, 1, 2, 2, 3, 4, 5, 7, 9, 12, 16, 21, 28, 37, 49];
        
        // Obtener el último paso de la secuencia
        $lastBet = $config->bets()
            ->whereIn('status', ['won', 'lost'])
            ->latest('resolved_at')
            ->first();

        $step = 0;
        if ($lastBet) {
            if ($lastBet->status === 'lost') {
                $step = min($lastBet->sequence_step + 1, count($sequence) - 1);
            } else {
                $step = 0; // Reset after win
            }
        }

        // Ajustar por confianza
        $confidenceMultiplier = $this->getConfidenceMultiplier($confidence);
        $amount = $baseAmount * $sequence[$step] * $confidenceMultiplier;

        return max(2, floor(min($amount, $maxBetAmount)));
    }

    private function calculateFibonacci(BudgetConfiguration $config, float $maxBetAmount): float
    {
        $parameters = $config->strategy_parameters ?? [];
        $baseAmount = $parameters['base_amount'] ?? ($config->initial_budget * 0.01);
        
        // Secuencia Fibonacci: 1, 1, 2, 3, 5, 8, 13, 21, 34...
        $sequence = [1, 1, 2, 3, 5, 8, 13, 21, 34, 55, 89, 144];
        
        $lastBet = $config->bets()
            ->whereIn('status', ['won', 'lost'])
            ->latest('resolved_at')
            ->first();

        $step = 0;
        if ($lastBet && $lastBet->status === 'lost') {
            $step = min($lastBet->sequence_step + 1, count($sequence) - 1);
        }

        $amount = $baseAmount * $sequence[$step];
        return max(2, floor(min($amount, $maxBetAmount)));
    }

    private function calculateMartingale(BudgetConfiguration $config, float $maxBetAmount): float
    {
        $parameters = $config->strategy_parameters ?? [];
        $baseAmount = $parameters['base_amount'] ?? ($config->initial_budget * 0.02);
        $multiplier = $parameters['multiplier'] ?? 2.0;
        
        $lastBet = $config->bets()
            ->whereIn('status', ['won', 'lost'])
            ->latest('resolved_at')
            ->first();

        if ($lastBet && $lastBet->status === 'lost') {
            $amount = $lastBet->amount * $multiplier;
        } else {
            $amount = $baseAmount;
        }

        return max(2, floor(min($amount, $maxBetAmount)));
    }

    private function calculateFixed(BudgetConfiguration $config, float $maxBetAmount): float
    {
        $parameters = $config->strategy_parameters ?? [];
        $fixedAmount = $parameters['fixed_amount'] ?? ($config->initial_budget * 0.02);
        
        return min($fixedAmount, $maxBetAmount);
    }

    private function calculatePercentage(BudgetConfiguration $config, float $confidence, float $maxBetAmount): float
    {
        $parameters = $config->strategy_parameters ?? [];
        $basePercentage = $parameters['base_percentage'] ?? 2.0; // 2% base
        
        // Kelly Criterion adaptado
        $confidenceMultiplier = $this->getConfidenceMultiplier($confidence);
        $percentage = $basePercentage * $confidenceMultiplier;
        
        $amount = ($config->current_budget * $percentage) / 100;
        return max(2, floor(min($amount, $maxBetAmount)));
    }

    private function getConfidenceMultiplier(float $confidence): float
    {
        // Convertir confianza (60-95%) a multiplicador (0.5-2.0)
        if ($confidence < 60) return 0.5;
        if ($confidence >= 90) return 2.0;
        
        // Interpolación lineal
        return 0.5 + (($confidence - 60) / 30) * 1.5;
    }

    /**
     * DEPRECATED: Este método genera odds irreales
     * @deprecated Use FootballApiOddsService::getRealOddsForMatch() instead
     * @param string $betType
     * @param FootballMatch $match
     * @return float
     */
    public function getRecommendedOdds(string $betType, FootballMatch $match): float
    {
        \Log::warning('DEPRECATED: BettingStrategyService::getRecommendedOdds() called', [
            'bet_type' => $betType,
            'match_id' => $match->id,
            'caller' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function'] ?? 'unknown'
        ]);
        
        // CRITICAL FIX: Return reasonable fallback odds without database predictions
        // This prevents system failures when predictions are missing
        
        $fallbackOdds = [
            'home_win' => 2.50,
            'away_win' => 3.20,
            'draw' => 3.00,
            'over_2_5' => 1.85,
            'under_2_5' => 1.95,
            'both_teams_score' => 1.70,
            'over_0_5_first_half' => 1.30,
        ];
        
        return $fallbackOdds[$betType] ?? 2.0;
    }

    private function probabilityToOdds(float $probability): float
    {
        if ($probability <= 0) return 10.0;
        if ($probability >= 100) return 1.01;
        
        return round(100 / $probability, 2);
    }

    public function createBet(BudgetConfiguration $config, FootballMatch $match, string $betType, float $amount, float $odds, float $confidence): Bet
    {
        $potentialProfit = ($amount * $odds) - $amount;
        $sequence_step = $this->getNextSequenceStep($config);

        // Crear la apuesta
        $bet = Bet::create([
            'budget_configuration_id' => $config->id,
            'match_id' => $match->id,
            'amount' => $amount,
            'odds' => $odds,
            'bet_type' => $betType,
            'potential_profit' => $potentialProfit,
            'confidence' => $confidence,
            'budget_before' => $config->current_budget,
            'sequence_step' => $sequence_step,
            'placed_at' => now(),
        ]);

        // Actualizar budget
        $newBudget = $config->current_budget - $amount;
        $config->update(['current_budget' => $newBudget]);

        // Registrar en historial
        BudgetHistory::create([
            'budget_configuration_id' => $config->id,
            'bet_id' => $bet->id,
            'amount' => -$amount,
            'balance_before' => $bet->budget_before,
            'balance_after' => $newBudget,
            'type' => 'bet_placed',
            'description' => "Apuesta en {$match->homeTeam->name} vs {$match->awayTeam->name}",
        ]);

        return $bet;
    }

    private function getNextSequenceStep(BudgetConfiguration $config): int
    {
        $lastBet = $config->bets()->latest('placed_at')->first();
        
        if (!$lastBet) return 0;
        
        if ($config->strategy === 'mansaniello' || $config->strategy === 'fibonacci') {
            if ($lastBet->status === 'lost') {
                return $lastBet->sequence_step + 1;
            } else {
                return 0; // Reset after win
            }
        }
        
        return 0;
    }

    public function resolveBet(Bet $bet): void
    {
        // CRITICAL FIX: Add distributed locking to prevent race conditions
        $lockKey = "bet_resolution_{$bet->id}";
        $lockTimeout = 30; // 30 seconds
        
        if (!\Cache::lock($lockKey, $lockTimeout)->get()) {
            \Log::warning("Could not acquire lock for bet resolution", ['bet_id' => $bet->id]);
            throw new \Exception("Bet resolution already in progress");
        }
        
        try {
            // PROTECCIÓN 1: Verificar si ya fue resuelta
            if ($bet->status !== 'pending') {
                \Log::warning("Intento de resolver apuesta ya resuelta", ['bet_id' => $bet->id, 'status' => $bet->status]);
                return;
            }
            
            // PROTECCIÓN 2: Verificar si ya existe entrada en BudgetHistory para resolución
            $existingHistory = BudgetHistory::where('bet_id', $bet->id)
                ->whereIn('type', ['bet_won', 'bet_lost'])
                ->exists();
                
            if ($existingHistory) {
                \Log::warning("Intento de resolver apuesta que ya tiene historial", ['bet_id' => $bet->id]);
                return;
            }

            // PROTECCIÓN 3: Usar transacción para atomicidad con rollback logic
            \DB::transaction(function() use ($bet) {
            // Verificar nuevamente dentro de la transacción
            $bet->refresh();
            if ($bet->status !== 'pending') {
                \Log::warning("Apuesta resuelta por otro proceso durante transacción", ['bet_id' => $bet->id]);
                return;
            }
            
            $result = $bet->calculateResult();
            
            $bet->update([
                'status' => $result['status'],
                'actual_profit' => $result['profit'],
                'resolved_at' => now(),
            ]);

            $config = $bet->budgetConfiguration;
            $oldBudget = $config->current_budget;
            
            // CORRECTED: Handle bet resolution correctly
            // When bet is placed: amount is deducted from budget
            // When bet wins: return original amount + profit to budget  
            // When bet loses: no additional change (amount stays deducted)
            if ($result['status'] === 'won') {
                // Return original bet amount + profit to budget
                $newBudget = $oldBudget + $bet->amount + $result['profit'];
            } else {
                // For lost bets, no change needed - amount was already deducted when placed
                $newBudget = $oldBudget;
            }
            
            $config->update(['current_budget' => $newBudget]);
            $bet->update(['budget_after' => $newBudget]);

            // Registrar en historial
            BudgetHistory::create([
                'budget_configuration_id' => $config->id,
                'bet_id' => $bet->id,
                'amount' => $result['profit'],
                'balance_before' => $oldBudget,
                'balance_after' => $newBudget,
                'type' => $result['status'] === 'won' ? 'bet_won' : 'bet_lost',
                'description' => $result['status'] === 'won' ? 
                    "Apuesta ganada: +" . number_format($result['profit'], 2) : 
                    "Apuesta perdida: " . number_format($result['profit'], 2),
            ]);
            
            \Log::info("Apuesta resuelta correctamente", [
                'bet_id' => $bet->id,
                'result' => $result['status'],
                'profit' => $result['profit'],
                'old_budget' => $oldBudget,
                'new_budget' => $newBudget
            ]);
            });
            
        } catch (\Exception $e) {
            \Log::error("Error resolving bet", [
                'bet_id' => $bet->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        } finally {
            // CRITICAL: Always release the lock
            \Cache::lock($lockKey)->release();
        }
    }

    public function getStrategyPerformance(BudgetConfiguration $config, int $days = 30): array
    {
        $bets = $config->bets()
            ->whereIn('status', ['won', 'lost'])
            ->where('resolved_at', '>=', now()->subDays($days))
            ->get();

        $totalBets = $bets->count();
        $wonBets = $bets->where('status', 'won')->count();
        $totalProfit = $bets->sum('actual_profit');
        $totalStaked = $bets->sum('amount');

        return [
            'total_bets' => $totalBets,
            'won_bets' => $wonBets,
            'lost_bets' => $totalBets - $wonBets,
            'win_rate' => $totalBets > 0 ? round(($wonBets / $totalBets) * 100, 2) : 0,
            'total_profit' => $totalProfit,
            'total_staked' => $totalStaked,
            'roi' => $totalStaked > 0 ? round(($totalProfit / $totalStaked) * 100, 2) : 0,
            'avg_bet_size' => $totalBets > 0 ? round($totalStaked / $totalBets, 2) : 0,
            'avg_odds' => $totalBets > 0 ? round($bets->avg('odds'), 2) : 0,
        ];
    }
}