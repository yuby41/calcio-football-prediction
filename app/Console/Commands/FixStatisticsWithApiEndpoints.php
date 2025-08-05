<?php

namespace App\Console\Commands;

use App\Models\FootballMatch;
use App\Models\MatchPrediction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FixStatisticsWithApiEndpoints extends Command
{
    protected $signature = 'statistics:fix-with-api-endpoints {--limit=100 : Number of matches to process} {--days=7 : Days back to check} {--force : Force update even if data exists} {--match-id= : Fix specific match by internal ID} {--external-id= : Fix specific match by external API ID}';
    protected $description = 'Fix statistics using FT and HT endpoints from API v3.football.api-sports.io';

    private $apiKey;
    private $baseUrl;
    private $requestCount = 0;
    private $maxRequestsPerMinute = 100; // API limit

    public function handle()
    {
        $limit = (int) $this->option('limit');
        $days = (int) $this->option('days');
        $force = $this->option('force');
        $matchId = $this->option('match-id');
        $externalId = $this->option('external-id');

        $this->apiKey = config('services.football_api.key');
        $this->baseUrl = 'https://v3.football.api-sports.io';

        if (!$this->apiKey || !$this->baseUrl) {
            $this->error('❌ API configuration missing. Please check FOOTBALL_API_KEY and FOOTBALL_API_BASE_URL');
            return 1;
        }

        // Handle specific match fixing
        if ($matchId || $externalId) {
            return $this->fixSpecificMatch($matchId, $externalId);
        }

        $this->info("🔧 Fixing statistics with API endpoints FT/HT...");
        $this->info("   📅 Days back: {$days}");
        $this->info("   📊 Limit: {$limit} matches");
        $this->info("   🔄 Force update: " . ($force ? 'Yes' : 'No'));
        $this->line("");

        // Step 1: Fix finished matches with FT endpoint
        $this->fixFinishedMatches($limit, $days, $force);

        // Step 2: Fix half-time data with HT endpoint  
        $this->fixHalfTimeData($limit, $days, $force);

        // Step 3: Update prediction accuracy
        $this->updatePredictionAccuracy();

        $this->info("✅ Statistics fixed successfully!");
        $this->info("📊 Total API requests made: {$this->requestCount}");

        return 0;
    }

    private function fixFinishedMatches(int $limit, int $days, bool $force)
    {
        $this->info("🎯 Step 1: Fixing finished matches with FT endpoint...");

        // Get matches that should be finished but have incorrect data
        $matches = FootballMatch::where('status', 'finished')
            ->whereNotNull('external_id')
            ->where('match_date', '>=', now()->subDays($days))
            ->when(!$force, function($query) {
                // Only process matches with suspicious data if not forcing
                $query->where(function($q) {
                    $q->whereNull('home_goals')
                      ->orWhereNull('away_goals')
                      ->orWhere('home_goals', 0)
                      ->where('away_goals', 0); // Suspicious 0-0 results
                });
            })
            ->orderBy('match_date', 'desc')
            ->limit($limit)
            ->get();

        if ($matches->isEmpty()) {
            $this->info("   ✅ No matches need fixing");
            return;
        }

        $this->info("   📊 Processing {$matches->count()} finished matches...");
        $progressBar = $this->output->createProgressBar($matches->count());
        $progressBar->start();

        $updated = 0;
        $errors = 0;

        foreach ($matches as $match) {
            try {
                $ftData = $this->fetchFinishedMatchData($match->external_id);
                
                if ($ftData) {
                    $oldScore = "{$match->home_goals}-{$match->away_goals}";
                    
                    $match->update([
                        'home_goals' => $ftData['home_goals'],
                        'away_goals' => $ftData['away_goals'],
                        'status' => 'finished'
                    ]);
                    
                    $newScore = "{$ftData['home_goals']}-{$ftData['away_goals']}";
                    
                    if ($oldScore !== $newScore) {
                        $this->line("\n   🔄 Updated: {$match->homeTeam->name} vs {$match->awayTeam->name}");
                        $this->line("      Old: {$oldScore} → New: {$newScore}");
                        $updated++;
                    }
                }

                $this->rateLimitDelay();
                
            } catch (\Exception $e) {
                $errors++;
                Log::error('Error fixing match FT data', [
                    'match_id' => $match->id,
                    'external_id' => $match->external_id,
                    'error' => $e->getMessage()
                ]);
            }
            
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->line("");
        $this->info("   ✅ Finished matches updated: {$updated}");
        $this->info("   ❌ Errors: {$errors}");
    }

    private function fixHalfTimeData(int $limit, int $days, bool $force)
    {
        $this->info("\n🕐 Step 2: Fixing half-time data...");

        // Get finished matches missing half-time data
        $matches = FootballMatch::where('status', 'finished')
            ->whereNotNull('external_id')
            ->where('match_date', '>=', now()->subDays($days))
            ->when(!$force, function($query) {
                $query->where(function($q) {
                    $q->whereNull('home_goals_first_half')
                      ->orWhereNull('away_goals_first_half');
                });
            })
            ->orderBy('match_date', 'desc')
            ->limit($limit)
            ->get();

        if ($matches->isEmpty()) {
            $this->info("   ✅ No matches need half-time data");
            return;
        }

        $this->info("   📊 Processing {$matches->count()} matches for half-time data...");
        $progressBar = $this->output->createProgressBar($matches->count());
        $progressBar->start();

        $updated = 0;
        $errors = 0;

        foreach ($matches as $match) {
            try {
                $htData = $this->fetchHalfTimeData($match->external_id);
                
                if ($htData) {
                    $match->update([
                        'home_goals_first_half' => $htData['home_goals_ht'],
                        'away_goals_first_half' => $htData['away_goals_ht']
                    ]);
                    
                    $this->line("\n   🕐 HT Updated: {$match->homeTeam->name} vs {$match->awayTeam->name}");
                    $this->line("      1T: {$htData['home_goals_ht']}-{$htData['away_goals_ht']}");
                    $updated++;
                }

                $this->rateLimitDelay();
                
            } catch (\Exception $e) {
                $errors++;
                Log::error('Error fixing match HT data', [
                    'match_id' => $match->id,
                    'external_id' => $match->external_id,
                    'error' => $e->getMessage()
                ]);
            }
            
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->line("");
        $this->info("   ✅ Half-time data updated: {$updated}");
        $this->info("   ❌ Errors: {$errors}");
    }

    private function fetchFinishedMatchData(string $externalId): ?array
    {
        $this->requestCount++;
        
        $response = Http::withHeaders([
            'x-apisports-key' => $this->apiKey,
            'Accept' => 'application/json',
        ])->timeout(10)->get("{$this->baseUrl}/fixtures", [
            'id' => $externalId,
            'status' => 'FT' // Only finished matches
        ]);

        if (!$response->successful()) {
            Log::warning('API FT request failed', [
                'external_id' => $externalId,
                'status' => $response->status(),
                'response' => $response->body()
            ]);
            return null;
        }

        $data = $response->json();
        
        if (!isset($data['response'][0])) {
            return null;
        }

        $fixture = $data['response'][0];
        
        // Verify match is actually finished
        if ($fixture['fixture']['status']['short'] !== 'FT') {
            return null;
        }

        // Use fulltime score from score object (more reliable)
        if (!isset($fixture['score']['fulltime'])) {
            return null;
        }
        
        $fulltime = $fixture['score']['fulltime'];
        
        if ($fulltime['home'] !== null && $fulltime['away'] !== null) {
            return [
                'home_goals' => (int) $fulltime['home'],
                'away_goals' => (int) $fulltime['away'],
                'status' => 'finished'
            ];
        }

        return null;
    }

    private function fetchHalfTimeData(string $externalId): ?array
    {
        $this->requestCount++;
        
        $response = Http::withHeaders([
            'x-apisports-key' => $this->apiKey,
            'Accept' => 'application/json',
        ])->timeout(10)->get("{$this->baseUrl}/fixtures", [
            'id' => $externalId
        ]);

        if (!$response->successful()) {
            Log::warning('API HT request failed', [
                'external_id' => $externalId,
                'status' => $response->status(),
                'response' => $response->body()
            ]);
            return null;
        }

        $data = $response->json();
        
        if (!isset($data['response'][0])) {
            return null;
        }

        $fixture = $data['response'][0];
        
        // Check if halftime score is available
        if (!isset($fixture['score']['halftime'])) {
            return null;
        }

        $halftime = $fixture['score']['halftime'];
        
        if ($halftime['home'] !== null && $halftime['away'] !== null) {
            return [
                'home_goals_ht' => (int) $halftime['home'],
                'away_goals_ht' => (int) $halftime['away']
            ];
        }

        return null;
    }

    private function updatePredictionAccuracy()
    {
        $this->info("\n📊 Step 3: Updating prediction accuracy...");
        
        // Update general prediction accuracy
        $this->call('predictions:update-accuracy', ['--limit' => 200]);
        
        // Verify prediction consistency
        $this->call('predictions:verify', ['--fix' => true, '--limit' => 100]);
        
        $this->info("   ✅ Prediction accuracy updated");
    }

    private function rateLimitDelay()
    {
        // Implement rate limiting to avoid hitting API limits
        if ($this->requestCount % $this->maxRequestsPerMinute === 0) {
            $this->line("\n   ⏳ Rate limit pause (60 seconds)...");
            sleep(60);
        } else {
            // Small delay between requests
            usleep(100000); // 0.1 seconds
        }
    }

    private function fixSpecificMatch($matchId, $externalId)
    {
        $this->info('🔧 Fixing specific match...');

        if ($externalId) {
            $match = FootballMatch::where('external_id', $externalId)->with(['homeTeam', 'awayTeam'])->first();
        } else {
            $match = FootballMatch::with(['homeTeam', 'awayTeam'])->find($matchId);
        }

        if (!$match) {
            $this->error('Match not found');
            return 1;
        }

        $this->info("🔍 Match: {$match->homeTeam->name} vs {$match->awayTeam->name}");
        $this->info("📊 Current result: {$match->home_goals}-{$match->away_goals}");

        // Fetch from API
        $this->info("🌐 Fetching data from API for external ID: {$match->external_id}");
        $apiData = $this->fetchFinishedMatchData($match->external_id);
        
        if (!$apiData) {
            $this->error('Could not fetch match data from API');
            
            // Try without FT filter
            $response = Http::withHeaders([
                'x-apisports-key' => $this->apiKey,
                'Accept' => 'application/json',
            ])->timeout(10)->get("{$this->baseUrl}/fixtures", [
                'id' => $match->external_id
            ]);
            
            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['response'][0]['score']['fulltime'])) {
                    $ft = $data['response'][0]['score']['fulltime'];
                    $this->info("🌐 API result: {$ft['home']}-{$ft['away']}");
                    
                    // Update manually
                    $match->update([
                        'home_goals' => (int) $ft['home'],
                        'away_goals' => (int) $ft['away']
                    ]);
                    
                    $this->info("✅ Match updated manually");
                    
                    // Update bets
                    $bets = $match->bets()->get();
                    foreach ($bets as $bet) {
                        $result = $bet->calculateResult();
                        $bet->update([
                            'status' => $result['status'],
                            'actual_profit' => $result['profit']
                        ]);
                        $this->info("🎯 Bet {$bet->id} ({$bet->bet_type}): {$bet->status} €{$bet->actual_profit}");
                    }
                    
                    return 0;
                } else {
                    $this->error("No fulltime score in API response");
                }
            } else {
                $this->error("API request failed: " . $response->status());
            }
            
            return 1;
        }

        $this->info("🌐 API result: {$apiData['home_goals']}-{$apiData['away_goals']}");

        // Update if different
        if ($match->home_goals != $apiData['home_goals'] || $match->away_goals != $apiData['away_goals']) {
            $match->update([
                'home_goals' => $apiData['home_goals'],
                'away_goals' => $apiData['away_goals']
            ]);

            $this->info("✅ Match updated");

            // Update related bets
            $bets = $match->bets;
            foreach ($bets as $bet) {
                $result = $bet->calculateResult();
                $bet->update([
                    'status' => $result['status'],
                    'actual_profit' => $result['profit']
                ]);
                $this->info("🎯 Bet {$bet->id} ({$bet->bet_type}): {$bet->status} €{$bet->actual_profit}");
            }
        } else {
            $this->info("✅ Match already correct");
        }

        return 0;
    }
}