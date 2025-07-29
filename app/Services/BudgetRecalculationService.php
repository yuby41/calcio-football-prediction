<?php

namespace App\Services;

use App\Models\BudgetConfiguration;
use App\Models\Bet;
use App\Models\BudgetHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BudgetRecalculationService
{
    /**
     * Recalcula completamente el historial de presupuesto desde el inicio
     */
    public function recalculateCompleteBudgetHistory(BudgetConfiguration $budget): array
    {
        return DB::transaction(function () use ($budget) {
            Log::info("Starting complete budget recalculation for budget ID: {$budget->id}");

            // 1. Limpiar historial existente (excepto depósito inicial)
            $initialDeposit = $budget->budgetHistory()
                ->where('type', 'deposit')
                ->where('description', 'Depósito inicial')
                ->first();

            // Eliminar todo el historial excepto el depósito inicial
            $budget->budgetHistory()
                ->where('id', '!=', $initialDeposit?->id)
                ->delete();

            // 2. Obtener todas las apuestas activas ordenadas cronológicamente
            $allBets = $budget->bets()
                ->orderBy('placed_at')
                ->get();

            // 3. Reconstruir historial paso a paso
            $currentBalance = $budget->initial_budget;
            $historyEntries = [];

            foreach ($allBets as $bet) {
                // Registro de apuesta colocada
                $balanceBefore = $currentBalance;
                $currentBalance -= $bet->amount;

                $historyEntries[] = [
                    'budget_configuration_id' => $budget->id,
                    'bet_id' => $bet->id,
                    'amount' => -$bet->amount,
                    'balance_before' => $balanceBefore,
                    'balance_after' => $currentBalance,
                    'type' => 'bet_placed',
                    'description' => "Apuesta en {$bet->match->homeTeam->name} vs {$bet->match->awayTeam->name}",
                    'created_at' => $bet->placed_at,
                    'updated_at' => $bet->placed_at,
                ];

                // Si está resuelta, agregar resolución
                if (in_array($bet->status, ['won', 'lost']) && $bet->resolved_at) {
                    $balanceBefore = $currentBalance;
                    $returnAmount = $bet->amount + ($bet->actual_profit ?? 0);
                    $currentBalance += $returnAmount;

                    $historyEntries[] = [
                        'budget_configuration_id' => $budget->id,
                        'bet_id' => $bet->id,
                        'amount' => $returnAmount,
                        'balance_before' => $balanceBefore,
                        'balance_after' => $currentBalance,
                        'type' => $bet->status === 'won' ? 'bet_won' : 'bet_lost',
                        'description' => $bet->status === 'won' ? 
                            "Apuesta ganada: +{$bet->actual_profit}" : 
                            "Apuesta perdida: {$bet->actual_profit}",
                        'created_at' => $bet->resolved_at,
                        'updated_at' => $bet->resolved_at,
                    ];
                }
            }

            // 4. Insertar nuevas entradas de historial
            if (!empty($historyEntries)) {
                BudgetHistory::insert($historyEntries);
            }

            // 5. Actualizar balance actual del budget
            $budget->update(['current_budget' => $currentBalance]);

            // 6. Verificar integridad
            $verification = $this->verifyBudgetIntegrity($budget);

            Log::info("Budget recalculation completed", [
                'budget_id' => $budget->id,
                'final_balance' => $currentBalance,
                'total_bets' => $allBets->count(),
                'history_entries_created' => count($historyEntries),
                'verification_passed' => $verification['valid']
            ]);

            return [
                'success' => true,
                'final_balance' => $currentBalance,
                'total_bets_processed' => $allBets->count(),
                'history_entries_created' => count($historyEntries),
                'verification' => $verification
            ];
        });
    }

    /**
     * Verifica la integridad del presupuesto
     */
    public function verifyBudgetIntegrity(BudgetConfiguration $budget): array
    {
        $lastHistoryEntry = $budget->budgetHistory()
            ->orderBy('created_at', 'desc')
            ->first();

        $calculatedBalance = $budget->initial_budget;
        
        // Sumar todas las transacciones
        $allTransactions = $budget->budgetHistory()
            ->where('type', '!=', 'deposit')
            ->orderBy('created_at')
            ->get();

        foreach ($allTransactions as $transaction) {
            $calculatedBalance += $transaction->amount;
        }

        $currentBalance = (float) $budget->current_budget;
        $isValid = abs($calculatedBalance - $currentBalance) < 0.01; // Tolerancia de 1 centavo

        return [
            'valid' => $isValid,
            'current_balance' => $currentBalance,
            'calculated_balance' => $calculatedBalance,
            'difference' => $currentBalance - $calculatedBalance,
            'last_history_balance' => $lastHistoryEntry?->balance_after ?? 0,
            'total_transactions' => $allTransactions->count()
        ];
    }

    /**
     * Elimina una apuesta y recalcula el historial completo
     */
    public function deleteBetWithRecalculation(BudgetConfiguration $budget, Bet $bet): array
    {
        return DB::transaction(function () use ($budget, $bet) {
            Log::info("Deleting bet with recalculation", [
                'budget_id' => $budget->id,
                'bet_id' => $bet->id,
                'bet_amount' => $bet->amount,
                'bet_status' => $bet->status
            ]);

            // Verificar que la apuesta pertenece al budget
            if ($bet->budget_configuration_id !== $budget->id) {
                throw new \Exception('La apuesta no pertenece a este budget.');
            }

            // Solo permitir eliminar apuestas pendientes
            if ($bet->status !== 'pending') {
                throw new \Exception('Solo se pueden eliminar apuestas pendientes.');
            }

            $betAmount = $bet->amount;
            $betType = $bet->getBetTypeDisplayAttribute();

            // Eliminar la apuesta
            $bet->delete();

            // Recalcular todo el historial
            $recalculationResult = $this->recalculateCompleteBudgetHistory($budget);

            if (!$recalculationResult['success']) {
                throw new \Exception('Error al recalcular el historial del presupuesto.');
            }

            return [
                'success' => true,
                'message' => "Apuesta eliminada y presupuesto recalculado. €{$betAmount} devueltos al budget.",
                'deleted_bet' => [
                    'amount' => $betAmount,
                    'type' => $betType
                ],
                'new_balance' => $budget->fresh()->current_budget,
                'recalculation_details' => $recalculationResult
            ];
        });
    }

    /**
     * Elimina múltiples apuestas y recalcula una sola vez
     */
    public function deleteMultipleBetsWithRecalculation(BudgetConfiguration $budget, array $betIds): array
    {
        return DB::transaction(function () use ($budget, $betIds) {
            Log::info("Deleting multiple bets with recalculation", [
                'budget_id' => $budget->id,
                'bet_ids' => $betIds,
                'count' => count($betIds)
            ]);

            $bets = $budget->bets()->whereIn('id', $betIds)->get();
            $deletedBets = [];
            $totalRefunded = 0;

            foreach ($bets as $bet) {
                if ($bet->status !== 'pending') {
                    throw new \Exception("La apuesta #{$bet->id} no se puede eliminar porque no está pendiente.");
                }

                $deletedBets[] = [
                    'id' => $bet->id,
                    'amount' => $bet->amount,
                    'type' => $bet->getBetTypeDisplayAttribute()
                ];
                $totalRefunded += $bet->amount;

                $bet->delete();
            }

            // Recalcular todo el historial una sola vez
            $recalculationResult = $this->recalculateCompleteBudgetHistory($budget);

            if (!$recalculationResult['success']) {
                throw new \Exception('Error al recalcular el historial del presupuesto.');
            }

            return [
                'success' => true,
                'message' => count($deletedBets) . " apuestas eliminadas y presupuesto recalculado. €{$totalRefunded} devueltos al budget.",
                'deleted_bets' => $deletedBets,
                'total_refunded' => $totalRefunded,
                'new_balance' => $budget->fresh()->current_budget,
                'recalculation_details' => $recalculationResult
            ];
        });
    }

    /**
     * Detecta inconsistencias en el presupuesto
     */
    public function detectBudgetInconsistencies(BudgetConfiguration $budget): array
    {
        $inconsistencies = [];

        // 1. Verificar que el balance actual coincide con el historial
        $verification = $this->verifyBudgetIntegrity($budget);
        if (!$verification['valid']) {
            $inconsistencies[] = [
                'type' => 'balance_mismatch',
                'description' => 'El balance actual no coincide con el historial',
                'details' => $verification
            ];
        }

        // 2. Verificar que todas las apuestas tienen entradas en el historial
        $betsWithoutHistory = $budget->bets()
            ->whereNotExists(function($query) {
                $query->select('id')
                      ->from('budget_history')
                      ->whereColumn('budget_history.bet_id', 'bets.id');
            })
            ->count();

        if ($betsWithoutHistory > 0) {
            $inconsistencies[] = [
                'type' => 'missing_history',
                'description' => "Hay {$betsWithoutHistory} apuestas sin entradas en el historial"
            ];
        }

        // 3. Verificar entradas huérfanas en el historial
        $orphanHistoryEntries = $budget->budgetHistory()
            ->whereNotNull('bet_id')
            ->whereNotExists(function($query) {
                $query->select('id')
                      ->from('bets')
                      ->whereColumn('bets.id', 'budget_history.bet_id');
            })
            ->count();

        if ($orphanHistoryEntries > 0) {
            $inconsistencies[] = [
                'type' => 'orphan_history',
                'description' => "Hay {$orphanHistoryEntries} entradas huérfanas en el historial"
            ];
        }

        // 4. Verificar balances negativos (excepto temporales)
        $negativeBalances = $budget->budgetHistory()
            ->where('balance_after', '<', 0)
            ->count();

        if ($negativeBalances > 0) {
            $inconsistencies[] = [
                'type' => 'negative_balance',
                'description' => "Hay {$negativeBalances} entradas con balance negativo"
            ];
        }

        return [
            'has_inconsistencies' => !empty($inconsistencies),
            'inconsistencies' => $inconsistencies,
            'total_issues' => count($inconsistencies)
        ];
    }
}