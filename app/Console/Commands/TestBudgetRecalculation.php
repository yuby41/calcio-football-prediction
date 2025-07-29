<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\BudgetConfiguration;
use App\Models\Bet;
use App\Models\FootballMatch;
use App\Services\BudgetRecalculationService;
use App\Services\BettingStrategyService;

class TestBudgetRecalculation extends Command
{
    protected $signature = 'budget:test-recalculation 
                           {--budget-id= : ID del budget para hacer la prueba}
                           {--create-test-bets : Crear apuestas de prueba}
                           {--simulate-deletions : Simular eliminaciones múltiples}';

    protected $description = 'Prueba el sistema de recálculo de presupuesto con escenarios reales';

    protected BudgetRecalculationService $recalculationService;
    protected BettingStrategyService $bettingService;

    public function __construct(
        BudgetRecalculationService $recalculationService,
        BettingStrategyService $bettingService
    ) {
        parent::__construct();
        $this->recalculationService = $recalculationService;
        $this->bettingService = $bettingService;
    }

    public function handle()
    {
        $budgetId = $this->option('budget-id');
        $createTestBets = $this->option('create-test-bets');
        $simulateDeletions = $this->option('simulate-deletions');

        if (!$budgetId) {
            $this->error('Debes especificar --budget-id');
            return Command::FAILURE;
        }

        $budget = BudgetConfiguration::find($budgetId);
        if (!$budget) {
            $this->error("Budget con ID {$budgetId} no encontrado");
            return Command::FAILURE;
        }

        $this->info("=== PRUEBA DE RECÁLCULO DE PRESUPUESTO ===");
        $this->info("Budget: {$budget->name} (ID: {$budget->id})");
        $this->info("Balance inicial: €{$budget->current_budget}");
        $this->newLine();

        // Verificar estado inicial
        $this->showBudgetStatus($budget, "ESTADO INICIAL");

        if ($createTestBets) {
            $this->createTestBets($budget);
            $this->showBudgetStatus($budget->fresh(), "DESPUÉS DE CREAR APUESTAS");
        }

        if ($simulateDeletions) {
            $this->simulateMultipleDeletions($budget->fresh());
        }

        return Command::SUCCESS;
    }

    private function showBudgetStatus(BudgetConfiguration $budget, string $title): void
    {
        $this->info("=== {$title} ===");
        
        $verification = $this->recalculationService->verifyBudgetIntegrity($budget);
        $inconsistencies = $this->recalculationService->detectBudgetInconsistencies($budget);

        $this->info("Balance actual: €{$budget->current_budget}");
        $this->info("Balance calculado: €{$verification['calculated_balance']}");
        $this->info("Diferencia: €{$verification['difference']}");
        $this->info("Válido: " . ($verification['valid'] ? '✅' : '❌'));
        
        $betsCount = $budget->bets()->count();
        $pendingBets = $budget->bets()->where('status', 'pending')->count();
        $historyCount = $budget->budgetHistory()->count();
        
        $this->info("Apuestas totales: {$betsCount}");
        $this->info("Apuestas pendientes: {$pendingBets}");
        $this->info("Entradas de historial: {$historyCount}");

        if ($inconsistencies['has_inconsistencies']) {
            $this->warn("⚠️  Inconsistencias detectadas:");
            foreach ($inconsistencies['inconsistencies'] as $issue) {
                $this->line("  - {$issue['type']}: {$issue['description']}");
            }
        } else {
            $this->info("✅ Sin inconsistencias detectadas");
        }
        
        $this->newLine();
    }

    private function createTestBets(BudgetConfiguration $budget): void
    {
        $this->info("🔄 Creando apuestas de prueba...");

        // Obtener algunos partidos para las apuestas
        $matches = FootballMatch::where('status', 'scheduled')
            ->whereHas('prediction')
            ->limit(5)
            ->get();

        if ($matches->isEmpty()) {
            $this->warn("No hay partidos disponibles para crear apuestas de prueba");
            return;
        }

        $betTypes = ['home_win', 'away_win', 'over_2_5', 'both_teams_score'];
        $createdBets = [];

        foreach ($matches as $i => $match) {
            $betType = $betTypes[$i % count($betTypes)];
            $odds = 2.0 + ($i * 0.2); // Odds variadas
            $amount = 10 + ($i * 5); // Cantidades variadas
            $confidence = 70 + ($i * 5); // Confianza variada

            try {
                $bet = $this->bettingService->createBet(
                    $budget,
                    $match,
                    $betType,
                    $amount,
                    $odds,
                    $confidence
                );

                $createdBets[] = $bet;
                $this->line("✅ Apuesta creada: €{$amount} en {$bet->getBetTypeDisplayAttribute()} - {$match->homeTeam->name} vs {$match->awayTeam->name}");

            } catch (\Exception $e) {
                $this->error("❌ Error creando apuesta: {$e->getMessage()}");
            }
        }

        $this->info("✅ Creadas " . count($createdBets) . " apuestas de prueba");
        $this->newLine();
    }

    private function simulateMultipleDeletions(BudgetConfiguration $budget): void
    {
        $this->info("🗑️  Simulando eliminaciones múltiples...");

        $pendingBets = $budget->bets()->where('status', 'pending')->limit(3)->get();
        
        if ($pendingBets->isEmpty()) {
            $this->warn("No hay apuestas pendientes para eliminar");
            return;
        }

        $this->info("Apuestas a eliminar:");
        foreach ($pendingBets as $bet) {
            $this->line("  - ID {$bet->id}: €{$bet->amount} en {$bet->getBetTypeDisplayAttribute()}");
        }
        $this->newLine();

        // Eliminar una a una para mostrar el problema del método anterior
        $this->info("=== SIMULANDO ELIMINACIÓN UNA POR UNA (método anterior) ===");
        $balanceBeforeIndividual = $budget->current_budget;
        
        foreach ($pendingBets->take(2) as $bet) {
            $balanceBefore = $budget->fresh()->current_budget;
            $this->line("Eliminando apuesta ID {$bet->id} (Balance antes: €{$balanceBefore})");
            
            // Simular método anterior - solo restaurar balance individual
            $budget->update(['current_budget' => $bet->budget_before]);
            $bet->delete();
            
            $balanceAfter = $budget->fresh()->current_budget;
            $this->line("Balance después: €{$balanceAfter}");
        }

        $this->warn("❌ Problema detectado: El balance puede estar incorrecto con eliminaciones múltiples");
        $this->showBudgetStatus($budget->fresh(), "DESPUÉS DE ELIMINACIONES INDIVIDUALES");

        // Ahora probar con el nuevo método
        $remainingBet = $budget->bets()->where('status', 'pending')->first();
        if ($remainingBet) {
            $this->info("=== PROBANDO NUEVO MÉTODO DE RECÁLCULO ===");
            
            try {
                $result = $this->recalculationService->deleteBetWithRecalculation($budget->fresh(), $remainingBet);
                
                $this->info("✅ Eliminación con recálculo completada:");
                $this->line("  - Apuesta eliminada: €{$result['deleted_bet']['amount']}");
                $this->line("  - Nuevo balance: €{$result['new_balance']}");
                $this->line("  - Apuestas procesadas: {$result['recalculation_details']['total_bets_processed']}");

                $this->showBudgetStatus($budget->fresh(), "DESPUÉS DE ELIMINACIÓN CON RECÁLCULO");

            } catch (\Exception $e) {
                $this->error("❌ Error con nuevo método: {$e->getMessage()}");
            }
        }
    }
}