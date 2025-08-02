<?php

namespace App\Console\Commands;

use App\Models\FootballMatch;
use App\Models\Bet;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VerifyMatchResults extends Command
{
    protected $signature = 'matches:verify-results {--match-id= : Specific match ID to verify} {--external-id= : Specific external ID to verify} {--fix : Fix incorrect results} {--limit=10 : Limit the number of matches to verify}';
    protected $description = 'Verify match results against external API and fix discrepancies';

    private string $apiKey;
    private string $apiUrl;

    public function __construct()
    {
        parent::__construct();
        $this->apiKey = config('services.football_api.key', '');
        $this->apiUrl = 'https://v3.football.api-sports.io';
    }

    public function handle()
    {
        $matchId = $this->option('match-id');
        $externalId = $this->option('external-id');
        $fix = $this->option('fix');
        $limit = $this->option('limit');
        
        $this->info($fix ? "🔧 Verificando y corrigiendo resultados de partidos..." : "🔍 Verificando resultados de partidos...");
        
        if ($matchId) {
            $matches = FootballMatch::where('id', $matchId)->with(['homeTeam', 'awayTeam'])->get();
        } elseif ($externalId) {
            $matches = FootballMatch::where('external_id', $externalId)->with(['homeTeam', 'awayTeam'])->get();
        } else {
            // Get recent finished matches
            $matches = FootballMatch::where('status', 'finished')
                ->where('match_date', '>=', now()->subDays(7))
                ->whereNotNull('external_id')
                ->with(['homeTeam', 'awayTeam'])
                ->orderBy('match_date', 'desc')
                ->limit($limit)
                ->get();
        }

        if ($matches->isEmpty()) {
            $this->info('✅ No hay partidos que verificar.');
            return 0;
        }

        $this->info("🎯 Verificando {$matches->count()} partidos:");

        $discrepancies = [];
        $corrected = 0;
        $progressBar = $this->output->createProgressBar($matches->count());
        $progressBar->start();

        foreach ($matches as $match) {
            try {
                $discrepancy = $this->verifyMatchResult($match, $fix);
                if ($discrepancy) {
                    $discrepancies[] = $discrepancy;
                    if ($fix && $discrepancy['corrected']) {
                        $corrected++;
                    }
                }
            } catch (\Exception $e) {
                $this->error("\n❌ Error verificando partido ID {$match->id}: " . $e->getMessage());
            }
            $progressBar->advance();
            
            // Add small delay to respect API rate limits
            usleep(100000); // 0.1 seconds
        }

        $progressBar->finish();
        $this->newLine();

        if (empty($discrepancies)) {
            $this->info("✅ Todos los resultados son correctos.");
        } else {
            $this->warn("⚠️  Se encontraron {" . count($discrepancies) . "} discrepancias:");
            $this->displayDiscrepancies($discrepancies);
            
            if ($fix) {
                $this->info("🔧 Se corrigieron {$corrected} partidos.");
                $this->resolveAffectedBets($discrepancies);
            } else {
                $this->info("💡 Ejecuta con --fix para corregir automáticamente.");
            }
        }

        return 0;
    }

    private function verifyMatchResult(FootballMatch $match, bool $fix = false): ?array
    {
        if (empty($this->apiKey)) {
            throw new \Exception("API key no configurada");
        }

        // Get match result from API
        $apiResult = $this->getMatchResultFromApi($match->external_id);
        
        if (!$apiResult) {
            return null; // No API data available
        }

        $dbHomeGoals = (int) $match->home_goals;
        $dbAwayGoals = (int) $match->away_goals;
        $apiHomeGoals = (int) $apiResult['home_goals'];
        $apiAwayGoals = (int) $apiResult['away_goals'];

        $hasDiscrepancy = ($dbHomeGoals !== $apiHomeGoals) || ($dbAwayGoals !== $apiAwayGoals);

        if ($hasDiscrepancy) {
            $discrepancy = [
                'match_id' => $match->id,
                'external_id' => $match->external_id,
                'match_info' => "{$match->homeTeam->name} vs {$match->awayTeam->name}",
                'match_date' => $match->match_date->format('Y-m-d H:i'),
                'db_result' => "{$dbHomeGoals}-{$dbAwayGoals}",
                'api_result' => "{$apiHomeGoals}-{$apiAwayGoals}",
                'db_total_goals' => $dbHomeGoals + $dbAwayGoals,
                'api_total_goals' => $apiHomeGoals + $apiAwayGoals,
                'corrected' => false,
                'bets_affected' => 0
            ];

            if ($fix) {
                // Update match result
                $match->home_goals = $apiHomeGoals;
                $match->away_goals = $apiAwayGoals;
                $match->save();

                $discrepancy['corrected'] = true;
                
                // Count affected bets
                $affectedBets = Bet::where('match_id', $match->id)
                    ->whereIn('status', ['won', 'lost'])
                    ->count();
                    
                $discrepancy['bets_affected'] = $affectedBets;

                Log::info("Match result corrected", [
                    'match_id' => $match->id,
                    'from' => "{$dbHomeGoals}-{$dbAwayGoals}",
                    'to' => "{$apiHomeGoals}-{$apiAwayGoals}",
                    'bets_affected' => $affectedBets
                ]);
            }

            return $discrepancy;
        }

        return null;
    }

    private function getMatchResultFromApi(string $externalId): ?array
    {
        try {
            $response = Http::withHeaders([
                'x-apisports-key' => $this->apiKey
            ])->timeout(10)->get($this->apiUrl . '/fixtures', [
                'id' => $externalId
            ]);

            if (!$response->successful()) {
                return null;
            }

            $data = $response->json();
            
            if (empty($data['response'])) {
                return null;
            }

            $fixture = $data['response'][0];
            
            // Only return results for finished matches
            if ($fixture['fixture']['status']['short'] !== 'FT') {
                return null;
            }

            return [
                'home_goals' => $fixture['goals']['home'] ?? 0,
                'away_goals' => $fixture['goals']['away'] ?? 0,
                'status' => $fixture['fixture']['status']['short']
            ];

        } catch (\Exception $e) {
            Log::warning("API call failed for external_id {$externalId}: " . $e->getMessage());
            return null;
        }
    }

    private function displayDiscrepancies(array $discrepancies)
    {
        foreach ($discrepancies as $discrepancy) {
            $this->line("");
            $status = $discrepancy['corrected'] ? '🔧 CORREGIDO' : '❌ DISCREPANCIA';
            $this->warn("⚠️  {$discrepancy['match_info']} ({$discrepancy['match_date']}) {$status}");
            $this->line("   🏟️  Base de datos: {$discrepancy['db_result']} ({$discrepancy['db_total_goals']} goles)");
            $this->line("   🌐 API: {$discrepancy['api_result']} ({$discrepancy['api_total_goals']} goles)");
            
            if ($discrepancy['bets_affected'] > 0) {
                $this->line("   🎲 Apuestas afectadas: {$discrepancy['bets_affected']}");
            }
        }
    }

    private function resolveAffectedBets(array $discrepancies)
    {
        $this->newLine();
        $this->info("🔄 Recalculando apuestas afectadas...");
        
        $totalBetsRecalculated = 0;
        
        foreach ($discrepancies as $discrepancy) {
            if ($discrepancy['corrected'] && $discrepancy['bets_affected'] > 0) {
                $matchId = $discrepancy['match_id'];
                
                // Get affected bets
                $affectedBets = Bet::where('match_id', $matchId)
                    ->whereIn('status', ['won', 'lost'])
                    ->with(['match', 'budgetConfiguration'])
                    ->get();

                foreach ($affectedBets as $bet) {
                    $originalStatus = $bet->status;
                    $originalProfit = $bet->actual_profit;
                    
                    // Recalculate bet result
                    $newResult = $bet->calculateResult();
                    
                    if ($newResult['status'] !== $originalStatus || abs($newResult['profit'] - $originalProfit) > 0.01) {
                        $bet->status = $newResult['status'];
                        $bet->actual_profit = $newResult['profit'];
                        $bet->save();
                        
                        // Update budget if needed
                        if ($bet->budgetConfiguration) {
                            $profitDifference = $newResult['profit'] - $originalProfit;
                            $budget = $bet->budgetConfiguration;
                            $budget->current_budget += $profitDifference;
                            $budget->save();
                            
                            $bet->budget_after = $budget->current_budget;
                            $bet->save();
                        }
                        
                        $totalBetsRecalculated++;
                        
                        $this->line("   🎲 Apuesta ID {$bet->id}: {$originalStatus} → {$newResult['status']} (€" . number_format($newResult['profit'] - $originalProfit, 2) . ")");
                    }
                }
            }
        }
        
        if ($totalBetsRecalculated > 0) {
            $this->info("✅ Se recalcularon {$totalBetsRecalculated} apuestas.");
        } else {
            $this->info("ℹ️  No se requirieron cambios en las apuestas.");
        }
    }
}