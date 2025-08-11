<?php

namespace App\Console\Commands;

use App\Models\BudgetConfiguration;
use Illuminate\Console\Command;

class CheckTargetProfitAchieved extends Command
{
    protected $signature = 'budget:check-target-profit {--fix : Automatically deactivate budgets that reached target}';
    protected $description = 'Check if any budget configurations have reached their target profit goal';

    public function handle()
    {
        $this->info('🎯 Verificando objetivos de ganancia alcanzados...');

        $budgets = BudgetConfiguration::where('is_active', true)
            ->whereNotNull('target_profit')
            ->where('target_profit', '>', 0)
            ->get();

        if ($budgets->isEmpty()) {
            $this->info('✅ No hay configuraciones activas con objetivos de ganancia definidos.');
            return 0;
        }

        $targetAchievedCount = 0;

        foreach ($budgets as $budget) {
            $currentProfit = $budget->getNetProfitAttribute();
            $targetProfit = $budget->target_profit;
            
            $this->line(sprintf(
                '📊 %s: Ganancia actual: €%.2f | Objetivo: €%.2f',
                $budget->name,
                $currentProfit,
                $targetProfit
            ));

            if ($budget->hasReachedTargetProfit()) {
                $targetAchievedCount++;
                
                $this->warn(sprintf(
                    '🎉 ¡OBJETIVO ALCANZADO! %s ha superado su meta de ganancia',
                    $budget->name
                ));

                if ($this->option('fix')) {
                    $budget->update(['is_active' => false]);
                    $this->info("✅ Budget '{$budget->name}' desactivado automáticamente");
                    
                    \Log::info('Target profit achieved - budget deactivated', [
                        'budget_id' => $budget->id,
                        'budget_name' => $budget->name,
                        'current_profit' => $currentProfit,
                        'target_profit' => $targetProfit,
                        'achievement_percentage' => round(($currentProfit / $targetProfit) * 100, 2)
                    ]);
                }
            }
        }

        $this->line('');
        
        if ($targetAchievedCount > 0) {
            $this->info("📈 Resumen: {$targetAchievedCount} configuraciones han alcanzado su objetivo");
            
            if (!$this->option('fix')) {
                $this->comment('💡 Usa --fix para desactivar automáticamente los budgets que alcanzaron su objetivo');
            }
        } else {
            $this->info('📊 Ninguna configuración ha alcanzado aún su objetivo de ganancia');
        }

        return 0;
    }
}