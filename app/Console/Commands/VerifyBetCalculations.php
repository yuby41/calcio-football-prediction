<?php

namespace App\Console\Commands;

use App\Models\Bet;
use Illuminate\Console\Command;

class VerifyBetCalculations extends Command
{
    protected $signature = 'bets:verify-calculations {--fix : Fix incorrect calculations} {--limit=100 : Limit the number of bets to verify}';
    protected $description = 'Verify bet calculations for accuracy and fix errors';

    public function handle()
    {
        $fix = $this->option('fix');
        $limit = $this->option('limit');
        
        $this->info($fix ? "🔧 Verificando y corrigiendo cálculos de apuestas..." : "🔍 Verificando cálculos de apuestas...");
        
        // Get resolved bets for finished matches
        $resolvedBets = Bet::whereHas('match', function($query) {
            $query->where('status', 'finished');
        })
        ->whereIn('status', ['won', 'lost'])
        ->with(['match'])
        ->limit($limit)
        ->get();

        if ($resolvedBets->isEmpty()) {
            $this->info('✅ No hay apuestas resueltas que verificar.');
            return 0;
        }

        $this->info("🎯 Verificando {$resolvedBets->count()} apuestas resueltas:");

        $errors = [];
        $corrected = 0;
        $progressBar = $this->output->createProgressBar($resolvedBets->count());
        $progressBar->start();

        foreach ($resolvedBets as $bet) {
            try {
                $error = $this->verifyBetCalculation($bet, $fix);
                if ($error) {
                    $errors[] = $error;
                    if ($fix) {
                        $corrected++;
                    }
                }
            } catch (\Exception $e) {
                $errors[] = [
                    'id' => $bet->id,
                    'type' => 'calculation_error',
                    'message' => $e->getMessage(),
                    'corrected' => false
                ];
            }
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();

        if (empty($errors)) {
            $this->info("✅ Todos los cálculos de apuestas son correctos.");
        } else {
            $this->warn("⚠️  Se encontraron {" . count($errors) . "} errores de cálculo:");
            $this->displayErrors($errors);
            
            if ($fix) {
                $this->info("🔧 Se corrigieron {$corrected} apuestas.");
            } else {
                $this->info("💡 Ejecuta con --fix para corregir automáticamente.");
            }
        }

        $this->showVerificationSummary($errors, $fix);

        return 0;
    }

    private function verifyBetCalculation(Bet $bet, bool $fix = false): ?array
    {
        $match = $bet->match;
        $actualCalculation = $bet->calculateResult();
        
        // Compare stored status with calculated status
        $storedStatus = $bet->status;
        $calculatedStatus = $actualCalculation['status'];
        
        // Compare stored profit with calculated profit
        $storedProfit = (float) $bet->actual_profit;
        $calculatedProfit = (float) $actualCalculation['profit'];
        
        $statusError = ($storedStatus !== $calculatedStatus);
        $profitError = (abs($storedProfit - $calculatedProfit) > 0.01); // Allow for small rounding differences
        
        if ($statusError || $profitError) {
            $error = [
                'id' => $bet->id,
                'bet_type' => $bet->bet_type,
                'match' => "{$match->homeTeam->name} {$match->home_goals}-{$match->away_goals} {$match->awayTeam->name}",
                'stored_status' => $storedStatus,
                'calculated_status' => $calculatedStatus,
                'stored_profit' => $storedProfit,
                'calculated_profit' => $calculatedProfit,
                'status_error' => $statusError,
                'profit_error' => $profitError,
                'corrected' => false
            ];
            
            if ($fix) {
                // Fix the bet
                $bet->status = $calculatedStatus;
                $bet->actual_profit = $calculatedProfit;
                $bet->save();
                
                // Update budget if needed
                if ($bet->budgetConfiguration && $profitError) {
                    $budget = $bet->budgetConfiguration;
                    $profitDifference = $calculatedProfit - $storedProfit;
                    $budget->current_budget += $profitDifference;
                    $budget->save();
                    
                    $bet->budget_after = $budget->current_budget;
                    $bet->save();
                    
                    $error['budget_adjusted'] = $profitDifference;
                }
                
                $error['corrected'] = true;
            }
            
            return $error;
        }
        
        return null;
    }

    private function displayErrors(array $errors)
    {
        foreach ($errors as $error) {
            if ($error['type'] ?? '' === 'calculation_error') {
                $this->error("❌ ID {$error['id']}: {$error['message']}");
                continue;
            }
            
            $this->line("");
            $this->warn("⚠️  Apuesta ID {$error['id']} ({$error['bet_type']}):");
            $this->line("   🏟️  {$error['match']}");
            
            if ($error['status_error']) {
                $status = $error['corrected'] ? '🔧 CORREGIDO' : '❌ ERROR';
                $this->line("   📊 Estado: {$error['stored_status']} → {$error['calculated_status']} {$status}");
            }
            
            if ($error['profit_error']) {
                $profit = $error['corrected'] ? '🔧 CORREGIDO' : '❌ ERROR';
                $this->line("   💵 Beneficio: €{$error['stored_profit']} → €{$error['calculated_profit']} {$profit}");
            }
            
            if (isset($error['budget_adjusted'])) {
                $this->line("   💰 Presupuesto ajustado: €" . number_format($error['budget_adjusted'], 2));
            }
        }
    }

    private function showVerificationSummary(array $errors, bool $fix)
    {
        $this->newLine();
        $this->info('📊 Resumen de Verificación:');
        
        $statusErrors = count(array_filter($errors, fn($e) => $e['status_error'] ?? false));
        $profitErrors = count(array_filter($errors, fn($e) => $e['profit_error'] ?? false));
        $corrected = count(array_filter($errors, fn($e) => $e['corrected'] ?? false));
        
        $this->line("   🎯 Errores de estado: {$statusErrors}");
        $this->line("   💵 Errores de beneficio: {$profitErrors}");
        
        if ($fix) {
            $this->line("   🔧 Apuestas corregidas: {$corrected}");
        }
        
        // Show potential budget impact
        $totalBudgetImpact = 0;
        foreach ($errors as $error) {
            if (isset($error['calculated_profit']) && isset($error['stored_profit'])) {
                $totalBudgetImpact += $error['calculated_profit'] - $error['stored_profit'];
            }
        }
        
        if (abs($totalBudgetImpact) > 0.01) {
            $impact = $totalBudgetImpact > 0 ? "beneficio adicional" : "pérdida adicional";
            $this->line("   💰 Impacto presupuestario: €" . number_format(abs($totalBudgetImpact), 2) . " ({$impact})");
        }
        
        $this->newLine();
        if ($fix && $corrected > 0) {
            $this->info("✅ Verificación y corrección completada exitosamente.");
        } elseif (empty($errors)) {
            $this->info("✅ Todos los cálculos verificados correctamente.");
        } else {
            $this->warn("⚠️  Se encontraron errores. Usa --fix para corregir.");
        }
    }
}