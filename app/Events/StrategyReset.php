<?php

namespace App\Events;

use App\Models\Bet;
use App\Models\BudgetConfiguration;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StrategyReset implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $budget;
    public $lastBet;
    public $sequenceStats;

    public function __construct(BudgetConfiguration $budget, Bet $lastBet, array $sequenceStats)
    {
        $this->budget = $budget;
        $this->lastBet = $lastBet;
        $this->sequenceStats = $sequenceStats;
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
        return 'strategy.reset';
    }

    public function broadcastWith(): array
    {
        return [
            'budget_id' => $this->budget->id,
            'strategy' => $this->budget->strategy,
            'event_type' => 'reset_to_base',
            'message' => $this->generateMessage(),
            'stats' => $this->sequenceStats,
            'new_base_amount' => $this->calculateNewBaseAmount(),
            'timestamp' => now()->toISOString(),
        ];
    }

    private function generateMessage(): string
    {
        $strategy = ucfirst($this->budget->strategy);
        $steps = $this->sequenceStats['steps_completed'];
        $netResult = $this->sequenceStats['net_result'];
        
        if ($netResult > 0) {
            return "✅ {$strategy} completada exitosamente! Secuencia de {$steps} pasos finalizada con ganancia de €{$netResult}. Volviendo a cantidad base.";
        } else {
            return "🔄 {$strategy} reseteada. Secuencia de {$steps} pasos finalizada. Iniciando nuevo ciclo desde cantidad base.";
        }
    }

    private function calculateNewBaseAmount(): float
    {
        // Calcular nueva cantidad base según la estrategia
        switch ($this->budget->strategy) {
            case 'mansaniello':
            case 'fibonacci':
                return $this->budget->base_bet_amount;
            case 'martingale':
                return $this->budget->base_bet_amount;
            case 'percentage':
                return ($this->budget->current_budget * 0.02); // 2% base
            default:
                return $this->budget->base_bet_amount;
        }
    }
}