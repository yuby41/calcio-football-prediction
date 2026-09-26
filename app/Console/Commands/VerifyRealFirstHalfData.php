<?php

namespace App\Console\Commands;

use App\Models\FootballMatch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class VerifyRealFirstHalfData extends Command
{
    protected $signature = 'matches:verify-real-first-half
                           {--date= : Specific date to check (Y-m-d)}
                           {--fix : Update database with real first-half data}
                           {--limit=20 : Maximum number of matches to check}';
    
    protected $description = 'Verify and fetch real first-half data from Football API to replace synthetic data';

    private ?string $apiKey;
    private string $apiUrl;

    public function __construct()
    {
        parent::__construct();
        $this->apiKey = config('services.football_api.key');
        $this->apiUrl = config(
            'services.football_api.base_url',
            'https://v3.football.api-sports.io'
        );
    }

    public function handle()
    {
        $date = $this->option('date');
        $fix = $this->option('fix');
        $limit = $this->option('limit');
        
        $this->info('🔍 VERIFICANDO DATOS REALES DE PRIMER TIEMPO');
        $this->info('============================================');
        
        if (empty($this->apiKey)) {
            $this->error('❌ FOOTBALL_API_KEY no configurada. Configure en .env');
            return 1;
        }
        
        // Get target date
        $targetDate = $date ? Carbon::parse($date) : Carbon::today();
        
        $this->info("📅 Verificando partidos del: {$targetDate->format('d/m/Y')}");
        
        // Get finished matches from target date
        $matches = FootballMatch::where('status', 'finished')
            ->whereDate('match_date', $targetDate)
            ->whereNotNull('home_goals')
            ->whereNotNull('away_goals')
            ->with(['homeTeam', 'awayTeam', 'prediction'])
            ->limit($limit)
            ->get();
            
        if ($matches->isEmpty()) {
            $this->info('✅ No se encontraron partidos terminados para verificar');
            return 0;
        }
        
        $this->info("Found {$matches->count()} matches to verify");
        
        $verified = 0;
        $realDataFound = 0;
        $syntheticDataFound = 0;
        $updated = 0;
        
        foreach ($matches as $match) {
            $this->line("\n📊 {$match->homeTeam->name} vs {$match->awayTeam->name}");
            $this->line("   Current: {$match->home_goals}-{$match->away_goals} (1T: {$match->home_goals_first_half}-{$match->away_goals_first_half})");
            
            // Try to get real first-half data from API
            $realFirstHalfData = $this->getRealFirstHalfDataFromApi($match);
            
            if ($realFirstHalfData) {
                $this->info("   ✅ Real API data: 1T {$realFirstHalfData['home']}-{$realFirstHalfData['away']}");
                $realDataFound++;
                
                // Check if current data is different (synthetic)
                if ($match->home_goals_first_half != $realFirstHalfData['home'] || 
                    $match->away_goals_first_half != $realFirstHalfData['away']) {
                    
                    $this->warn("   ⚠️  SYNTHETIC DATA DETECTED! Should be: {$realFirstHalfData['home']}-{$realFirstHalfData['away']}");
                    $syntheticDataFound++;
                    
                    if ($fix) {
                        $oldHome = $match->home_goals_first_half;
                        $oldAway = $match->away_goals_first_half;
                        
                        $match->home_goals_first_half = $realFirstHalfData['home'];
                        $match->away_goals_first_half = $realFirstHalfData['away'];
                        $match->save();
                        
                        // Update prediction accuracy if exists
                        if ($match->prediction && !is_null($match->prediction->first_half_over_0_5_probability)) {
                            $totalFirstHalf = $realFirstHalfData['home'] + $realFirstHalfData['away'];
                            $predictedOver05 = $match->prediction->first_half_over_0_5_probability > 0.5;
                            $actualOver05 = $totalFirstHalf > 0.5;
                            
                            $match->prediction->first_half_over_0_5_correct = ($actualOver05 === $predictedOver05);
                            $match->prediction->save();
                            
                            $correct = $match->prediction->first_half_over_0_5_correct ? '✅' : '❌';
                            $this->line("      Over 0.5 1T: Predicted " . 
                                       ($predictedOver05 ? 'Yes' : 'No') . ", Actual " . 
                                       ($actualOver05 ? 'Yes' : 'No') . " {$correct}");
                        }
                        
                        $this->info("   ✅ UPDATED: {$oldHome}-{$oldAway} → {$realFirstHalfData['home']}-{$realFirstHalfData['away']}");
                        $updated++;
                    } else {
                        $this->warn("   🔍 DRY RUN: Would update to real data");
                    }
                } else {
                    $this->info("   ✅ Data matches API - already correct");
                }
            } else {
                $this->error("   ❌ No real API data available");
            }
            
            $verified++;
            usleep(100000); // 0.1 seconds delay to avoid API rate limits
        }
        
        // Summary
        $this->info("\n📋 RESUMEN DE VERIFICACIÓN");
        $this->info("========================");
        $this->info("Partidos verificados: {$verified}");
        $this->info("Con datos reales de API: {$realDataFound}");
        $this->info("Con datos sintéticos detectados: {$syntheticDataFound}");
        
        if ($fix) {
            $this->info("✅ Partidos actualizados con datos reales: {$updated}");
        } else {
            $this->warn("🔍 DRY RUN completado. Use --fix para aplicar cambios");
        }
        
        return 0;
    }
    
    /**
     * Get real first-half data from Football API
     */
    private function getRealFirstHalfDataFromApi(FootballMatch $match): ?array
    {
        try {
            // Get fixtures for the match date
            $matchDate = $match->match_date->format('Y-m-d');
            
            $response = Http::withHeaders([
                'x-apisports-key' => $this->apiKey
            ])->timeout(15)->get($this->apiUrl . '/fixtures', [
                'date' => $matchDate,
                'status' => 'FT', // Only finished matches
                'timezone' => 'UTC'
            ]);

            if (!$response->successful()) {
                return null;
            }

            $fixtures = $response->json()['response'] ?? [];
            
            // Find matching fixture
            $matchingFixture = $this->findMatchingFixture($fixtures, $match);
            
            if (!$matchingFixture) {
                return null;
            }
            
            // Extract first-half score from the fixture data
            $score = $matchingFixture['score'] ?? [];
            $halftime = $score['halftime'] ?? null;
            
            if ($halftime && isset($halftime['home']) && isset($halftime['away'])) {
                return [
                    'home' => (int)$halftime['home'],
                    'away' => (int)$halftime['away']
                ];
            }
            
            return null;
            
        } catch (\Exception $e) {
            Log::error('Error fetching real first-half data', [
                'match_id' => $match->id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Find matching fixture in API response
     */
    private function findMatchingFixture(array $fixtures, FootballMatch $match): ?array
    {
        $homeTeamName = $this->normalizeTeamName($match->homeTeam->name);
        $awayTeamName = $this->normalizeTeamName($match->awayTeam->name);
        
        foreach ($fixtures as $fixture) {
            $apiHome = $this->normalizeTeamName($fixture['teams']['home']['name'] ?? '');
            $apiAway = $this->normalizeTeamName($fixture['teams']['away']['name'] ?? '');
            
            // Check team name similarity
            if ($this->teamsMatch($homeTeamName, $apiHome) && 
                $this->teamsMatch($awayTeamName, $apiAway)) {
                
                // Verify final scores match to ensure it's the same match
                $fulltime = $fixture['score']['fulltime'] ?? null;
                if ($fulltime && 
                    (int)$fulltime['home'] === $match->home_goals && 
                    (int)$fulltime['away'] === $match->away_goals) {
                    return $fixture;
                }
            }
        }

        return null;
    }
    
    private function normalizeTeamName(string $name): string
    {
        return strtolower(trim(preg_replace('/[^a-zA-Z0-9\s]/', '', $name)));
    }
    
    private function teamsMatch(string $team1, string $team2): bool
    {
        $similarity = 0;
        similar_text($team1, $team2, $similarity);
        return $similarity > 75; // 75% similarity threshold
    }
}