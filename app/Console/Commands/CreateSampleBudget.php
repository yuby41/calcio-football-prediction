<?php

namespace App\Console\Commands;

use App\Models\BudgetConfiguration;
use App\Models\BudgetHistory;
use Illuminate\Console\Command;

class CreateSampleBudget extends Command
{
    protected $signature = 'budget:create-sample {--amount=100}';
    
    protected $description = 'Create a sample budget configuration for testing';

    public function handle()
    {
        $amount = $this->option('amount');
        
        $config = BudgetConfiguration::create([
            'name' => 'Mansaniello Demo - ' . now()->format('M Y'),
            'strategy' => 'mansaniello',
            'initial_budget' => $amount,
            'current_budget' => $amount,
            'target_profit' => $amount * 0.5, // 50% target
            'max_bet_percentage' => 5.0,
            'min_confidence' => 65.0,
            'strategy_parameters' => [
                'base_amount' => $amount * 0.02, // 2%
                'max_sequence' => 10,
            ],
        ]);

        // Registrar depósito inicial
        BudgetHistory::create([
            'budget_configuration_id' => $config->id,
            'amount' => $amount,
            'balance_before' => 0,
            'balance_after' => $amount,
            'type' => 'deposit',
            'description' => 'Depósito inicial - Demo',
        ]);

        $this->info("✓ Configuración de budget creada:");
        $this->line("  ID: {$config->id}");
        $this->line("  Nombre: {$config->name}");
        $this->line("  Budget: €{$amount}");
        $this->line("  Estrategia: Mansaniello");
        $this->line("");
        $this->info("Visita: http://localhost:8000/budget/{$config->id}");

        return Command::SUCCESS;
    }
}