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
            // Update bet status and profit
            $bet->status = $result['status'];
            $bet->actual_profit = $result['profit'];
            $bet->resolved_at = now();
            $bet->save();

            // Update budget configuration
            if ($bet->budgetConfiguration) {
                $budget = $bet->budgetConfiguration;
                $newBudget = $budget->current_budget + $result['profit'];
                
                $this->line("   📊 Presupuesto: €{$budget->current_budget} → €" . number_format($newBudget, 2));
                
                $budget->current_budget = $newBudget;
                $budget->save();

                // Update bet's budget_after field
                $bet->budget_after = $newBudget;
                $bet->save();
            }
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