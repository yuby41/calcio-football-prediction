<?php

namespace App\Console\Commands;

use App\Models\Bet;
use App\Models\BudgetConfiguration;
use Illuminate\Console\Command;

class ResolvePendingBets extends Command
{
    protected $signature = 'bets:resolve-pending {--limit=50 : Limit the number of bets to resolve} {--dry-run : Show what would be resolved without actually resolving}';
    protected $description = 'Resolve pending bets for finished matches';

    public function handle()
    {
        $limit = $this->option('limit');
        $dryRun = $this->option('dry-run');
        
        $this->info($dryRun ? "🔍 DRY RUN: Analizando apuestas pendientes..." : "⚡ Resolviendo apuestas pendientes...");
        
        // Get pending bets for finished matches
        $pendingBets = Bet::whereHas('match', function($query) {
            $query->where('status', 'finished');
        })
        ->where('status', 'pending')
        ->with(['match', 'budgetConfiguration'])
        ->limit($limit)
        ->get();

        if ($pendingBets->isEmpty()) {
            $this->info('✅ No hay apuestas pendientes que resolver.');
            return 0;
        }

        $this->info("🎯 Encontradas {$pendingBets->count()} apuestas pendientes en partidos finalizados:");

        $resolved = 0;
        $errors = 0;
        $progressBar = $this->output->createProgressBar($pendingBets->count());
        $progressBar->start();

        foreach ($pendingBets as $bet) {
            try {
                $this->resolveBet($bet, $dryRun);
                $resolved++;
            } catch (\Exception $e) {
                $this->error("\n❌ Error resolviendo apuesta ID {$bet->id}: " . $e->getMessage());
                $errors++;
            }
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();

        if ($dryRun) {
            $this->info("🔍 DRY RUN COMPLETADO:");
            $this->info("   - {$resolved} apuestas serían resueltas");
            $this->info("   - {$errors} errores encontrados");
            $this->showPendingBetsSummary();
        } else {
            $this->info("✅ Apuestas resueltas exitosamente:");
            $this->info("   - ✅ Resueltas: {$resolved}");
            $this->info("   - ❌ Errores: {$errors}");
        }

        return 0;
    }

    private function resolveBet(Bet $bet, bool $dryRun = false)
    {
        $match = $bet->match;
        
        // PROTECCIÓN 1: Verificar si ya fue resuelta
        if ($bet->status !== 'pending') {
            $this->line("⚠️  Apuesta ID {$bet->id} ya fue resuelta (status: {$bet->status})");
            return;
        }
        
        // PROTECCIÓN 2: Verificar si ya existe entrada en BudgetHistory para resolución
        $existingHistory = \App\Models\BudgetHistory::where('bet_id', $bet->id)
            ->whereIn('type', ['bet_won', 'bet_lost'])
            ->exists();
            
        if ($existingHistory) {
            $this->line("⚠️  Apuesta ID {$bet->id} ya tiene historial de resolución");
            return;
        }
        
        $result = $bet->calculateResult();
        
        if ($result['status'] === 'pending') {
            throw new \Exception("No se puede resolver - datos insuficientes");
        }

        $this->line("\n🎲 Apuesta ID {$bet->id}: {$bet->bet_type_display}");
        $this->line("   🏟️  Partido: {$match->homeTeam->name} {$match->home_goals}-{$match->away_goals} {$match->awayTeam->name}");
        $this->line("   💰 Cantidad: €{$bet->amount} × {$bet->odds}");
        $this->line("   🎯 Resultado: " . ($result['status'] === 'won' ? '✅ GANADA' : '❌ PERDIDA'));
        $this->line("   💵 Beneficio: €" . number_format($result['profit'], 2));

        if (!$dryRun) {
            // PROTECCIÓN 3: Usar transacción para atomicidad
            \DB::transaction(function() use ($bet, $result) {
                // Verificar nuevamente dentro de la transacción
                $bet->refresh();
                if ($bet->status !== 'pending') {
                    $this->line("⚠️  Apuesta ya resuelta por otro proceso");
                    return;
                }
                
                // Update bet status and profit
                $bet->update([
                    'status' => $result['status'],
                    'actual_profit' => $result['profit'],
                    'resolved_at' => now(),
                ]);

                // Update budget configuration usando BudgetHistory (método correcto)
                if ($bet->budgetConfiguration) {
                    $budget = $bet->budgetConfiguration;
                    $oldBudget = $budget->current_budget;
                    $newBudget = $oldBudget + $result['profit'];
                    
                    $this->line("   📊 Presupuesto: €{$oldBudget} → €" . number_format($newBudget, 2));
                    
                    $budget->update(['current_budget' => $newBudget]);
                    $bet->update(['budget_after' => $newBudget]);

                    // CREAR REGISTRO EN BUDGETHISTORY (método correcto)
                    \App\Models\BudgetHistory::create([
                        'budget_configuration_id' => $budget->id,
                        'bet_id' => $bet->id,
                        'amount' => $result['profit'],
                        'balance_before' => $oldBudget,
                        'balance_after' => $newBudget,
                        'type' => $result['status'] === 'won' ? 'bet_won' : 'bet_lost',
                        'description' => $result['status'] === 'won' ? 
                            "Apuesta ganada: +" . number_format($result['profit'], 2) : 
                            "Apuesta perdida: " . number_format($result['profit'], 2),
                    ]);
                }
            });
        }
    }

    private function showPendingBetsSummary()
    {
        $this->newLine();
        $this->info('📊 Resumen de Apuestas Pendientes:');

        $pendingBets = Bet::whereHas('match', function($query) {
            $query->where('status', 'finished');
        })
        ->where('status', 'pending')
        ->with(['match'])
        ->get();

        $summary = [];
        $totalAmount = 0;
        $potentialWins = 0;
        $potentialLosses = 0;

        foreach ($pendingBets as $bet) {
            $result = $bet->calculateResult();
            $type = $bet->bet_type;
            
            if (!isset($summary[$type])) {
                $summary[$type] = [
                    'count' => 0,
                    'amount' => 0,
                    'wins' => 0,
                    'losses' => 0,
                    'profit' => 0
                ];
            }
            
            $summary[$type]['count']++;
            $summary[$type]['amount'] += $bet->amount;
            $totalAmount += $bet->amount;
            
            if ($result['status'] === 'won') {
                $summary[$type]['wins']++;
                $potentialWins++;
            } else {
                $summary[$type]['losses']++;
                $potentialLosses++;
            }
            
            $summary[$type]['profit'] += $result['profit'];
        }

        foreach ($summary as $type => $data) {
            $winRate = $data['count'] > 0 ? round(($data['wins'] / $data['count']) * 100, 1) : 0;
            $this->line(sprintf(
                "   %s: %d apuestas, €%.2f apostado, %d ganadas (%s%%), Beneficio: €%.2f",
                ucfirst(str_replace('_', ' ', $type)),
                $data['count'],
                $data['amount'],
                $data['wins'],
                $winRate,
                $data['profit']
            ));
        }

        $this->newLine();
        $this->line("📈 TOTALES:");
        $this->line("   💰 Total apostado: €" . number_format($totalAmount, 2));
        $this->line("   ✅ Apuestas ganadoras: {$potentialWins}");
        $this->line("   ❌ Apuestas perdedoras: {$potentialLosses}");
        $this->line("   💵 Beneficio neto potencial: €" . number_format(array_sum(array_column($summary, 'profit')), 2));
    }
}