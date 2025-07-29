<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\BudgetConfiguration;
use App\Services\BudgetRecalculationService;

class RecalculateBudgetHistory extends Command
{
    protected $signature = 'budget:recalculate 
                           {--budget-id= : ID específico del budget a recalcular}
                           {--all : Recalcular todos los budgets}
                           {--check-only : Solo verificar inconsistencias sin recalcular}
                           {--force : Forzar recálculo incluso si no hay inconsistencias}';

    protected $description = 'Recalcula el historial de presupuesto para corregir inconsistencias';

    protected BudgetRecalculationService $recalculationService;

    public function __construct(BudgetRecalculationService $recalculationService)
    {
        parent::__construct();
        $this->recalculationService = $recalculationService;
    }

    public function handle()
    {
        $budgetId = $this->option('budget-id');
        $all = $this->option('all');
        $checkOnly = $this->option('check-only');
        $force = $this->option('force');

        if (!$budgetId && !$all) {
            $this->error('Debes especificar --budget-id o --all');
            return Command::FAILURE;
        }

        if ($budgetId && $all) {
            $this->error('No puedes usar --budget-id y --all al mismo tiempo');
            return Command::FAILURE;
        }

        // Obtener budgets a procesar
        if ($all) {
            $budgets = BudgetConfiguration::all();
            $this->info("Procesando todos los budgets ({$budgets->count()})...");
        } else {
            $budget = BudgetConfiguration::find($budgetId);
            if (!$budget) {
                $this->error("Budget con ID {$budgetId} no encontrado");
                return Command::FAILURE;
            }
            $budgets = collect([$budget]);
            $this->info("Procesando budget: {$budget->name}");
        }

        if ($budgets->isEmpty()) {
            $this->info('No hay budgets para procesar.');
            return Command::SUCCESS;
        }

        $processedCount = 0;
        $inconsistentCount = 0;
        $recalculatedCount = 0;

        $progressBar = $this->output->createProgressBar($budgets->count());

        foreach ($budgets as $budget) {
            $this->processBuffer($budget, $checkOnly, $force, $inconsistentCount, $recalculatedCount);
            $processedCount++;
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        // Mostrar resumen
        $this->info("✅ Proceso completado!");
        $this->info("Budgets procesados: {$processedCount}");
        $this->info("Budgets con inconsistencias: {$inconsistentCount}");
        
        if (!$checkOnly) {
            $this->info("Budgets recalculados: {$recalculatedCount}");
        } else {
            $this->info("Modo verificación: No se realizaron cambios");
        }

        return Command::SUCCESS;
    }

    private function processBuffer(BudgetConfiguration $budget, bool $checkOnly, bool $force, int &$inconsistentCount, int &$recalculatedCount): void
    {
        try {
            // Detectar inconsistencias
            $inconsistencies = $this->recalculationService->detectBudgetInconsistencies($budget);
            
            if ($inconsistencies['has_inconsistencies']) {
                $inconsistentCount++;
                
                $this->newLine();
                $this->warn("❌ Budget '{$budget->name}' (ID: {$budget->id}) tiene inconsistencias:");
                
                foreach ($inconsistencies['inconsistencies'] as $issue) {
                    $this->line("  - {$issue['type']}: {$issue['description']}");
                }

                if (!$checkOnly) {
                    if ($force || $this->confirm("¿Recalcular historial para '{$budget->name}'?", true)) {
                        $this->recalculateBudget($budget, $recalculatedCount);
                    }
                }
            } else {
                if ($force && !$checkOnly) {
                    $this->info("🔄 Recalculando '{$budget->name}' (forzado)...");
                    $this->recalculateBudget($budget, $recalculatedCount);
                } else {
                    $this->line("✅ Budget '{$budget->name}' está consistente");
                }
            }

        } catch (\Exception $e) {
            $this->newLine();
            $this->error("❌ Error procesando budget '{$budget->name}': {$e->getMessage()}");
        }
    }

    private function recalculateBudget(BudgetConfiguration $budget, int &$recalculatedCount): void
    {
        try {
            $result = $this->recalculationService->recalculateCompleteBudgetHistory($budget);
            
            if ($result['success']) {
                $recalculatedCount++;
                $this->info("✅ Budget '{$budget->name}' recalculado exitosamente");
                $this->line("  Balance final: €{$result['final_balance']}");
                $this->line("  Apuestas procesadas: {$result['total_bets_processed']}");
                $this->line("  Entradas de historial creadas: {$result['history_entries_created']}");
                
                if ($result['verification']['valid']) {
                    $this->info("  ✅ Verificación de integridad: PASSED");
                } else {
                    $this->error("  ❌ Verificación de integridad: FAILED");
                    $this->line("    Diferencia: €{$result['verification']['difference']}");
                }
            } else {
                $this->error("❌ Error recalculando budget '{$budget->name}'");
            }

        } catch (\Exception $e) {
            $this->error("❌ Exception recalculando '{$budget->name}': {$e->getMessage()}");
        }
    }
}