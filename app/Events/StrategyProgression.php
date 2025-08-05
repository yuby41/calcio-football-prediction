<?php

namespace App\Events;

use App\Models\Bet;
use App\Models\BudgetConfiguration;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StrategyProgression implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $budget;
    public $bet;
    public $progressionInfo;

    public function __construct(BudgetConfiguration $budget, Bet $bet, array $progressionInfo)
    {
        $this->budget = $budget;
        $this->bet = $bet;
        $this->progressionInfo = $progressionInfo;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('budget.' . $this->budget->id),
            new Channel('strategy-notifications'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'strategy.progression';
    }

    public function broadcastWith(): array
    {
        return [
            'budget_id' => $this->budget->id,
            'strategy' => $this->budget->strategy,
            'event_type' => 'progression',
            'message' => $this->generateMessage(),
            'progression' => $this->progressionInfo,
            'risk_level' => $this->calculateRiskLevel(),
            'timestamp' => now()->toISOString(),
        ];
    }

    private function generateMessage(): string
    {
        $strategy = ucfirst($this->budget->strategy);
        $step = $this->progressionInfo['current_step'];
        $amount = $this->bet->amount;
        $maxSteps = $this->progressionInfo['max_steps'];
        
        if ($step >= $maxSteps * 0.8) { // 80% del máximo
            return "⚠️ {$strategy} en paso {$step}/{$maxSteps}. Cantidad: €{$amount}. ¡Cerca del límite máximo!";
        } elseif ($step >= $maxSteps * 0.6) { // 60% del máximo
            return "📈 {$strategy} progresando - Paso {$step}/{$maxSteps}. Cantidad: €{$amount}. Riesgo moderado.";
        } else {
            return "🎲 {$strategy} paso {$step}/{$maxSteps}. Cantidad: €{$amount}. Progresión normal.";
        }
    }

    private function calculateRiskLevel(): string
    {
        $step = $this->progressionInfo['current_step'];
        $maxSteps = $this->progressionInfo['max_steps'];
        $percentage = ($step / $maxSteps) * 100;
        
        if ($percentage >= 80) return 'ALTO';
        if ($percentage >= 60) return 'MODERADO';
        if ($percentage >= 40) return 'MEDIO';
        return 'BAJO';
    }
}