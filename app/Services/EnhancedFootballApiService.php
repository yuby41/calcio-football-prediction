<?php

namespace App\Services;

use App\Models\Team;
use App\Models\FootballMatch;
use App\Models\TeamStatistic;
use App\Services\ApiQuotaManager;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

/**
 * Enhanced Football API Service for v3.football.api-sports.io
 * 
 * Implements all major endpoints with proper rate limiting and caching strategies.
 * 
 * API Rate Limits & Recommendations:
 * - Free Plan: 100 requests/day
 * - Basic Plan: 1,000 requests/day, 10/minute
 * - Pro Plan: 10,000 requests/day, 100/minute
 * - Ultra Plan: 100,000 requests/day, 300/minute
 * - Mega Plan: 1,000,000 requests/day, 900/minute
 */
class EnhancedFootballApiService
{
    private string $baseUrl;
    private string $apiKey;
    private string $timezone;
    private array $headers;
    private ApiQuotaManager $quotaManager;

    // Updated rate limits for 7500 daily requests plan
    private const RATE_LIMITS = [
        'free' => 3,       // 100/day conservative
        'basic' => 8,      // 1000/day, 10/min with buffer
        'enhanced' => 250, // 7500/day, ~5/min sustained, 250/min burst
        'pro' => 80,       // 10000/day, 100/min with buffer
        'ultra' => 240,    // 100000/day, 300/min with buffer
        'mega' => 720,     // 1000000/day, 900/min with buffer
    ];

    public function __construct(ApiQuotaManager $quotaManager = null)
    {
        $this->baseUrl = config('services.football_api.base_url');
        $this->apiKey = config('services.football_api.key');
        $this->timezone = config('services.football_api.timezone', 'Europe/Madrid');
        $this->quotaManager = $quotaManager ?? app(ApiQuotaManager::class);
        $this->headers = [
            'x-apisports-key' => $this->apiKey,
            'Accept' => 'application/json',
        ];
    }

    // =========================================================================
    // QUOTA-AWARE API REQUEST WRAPPER
    // =========================================================================
    
    private function makeApiRequest(string $endpoint, array $params = [], string $requestType = 'general', int $priority = 3): ?array
    {
        // Check quota before making request
        if (!$this->quotaManager->canMakeRequest($requestType, $priority)) {
            Log::warning("API request blocked due to quota limits", [
                'endpoint' => $endpoint,
                'type' => $requestType,
                'priority' => $priority
            ]);
            return null;
        }

        // Apply optimal delay
        $delay = $this->quotaManager->getOptimalDelay();
        if ($delay > 1) {
            sleep($delay);
        }

        try {
            $response = Http::withHeaders($this->headers)
                ->timeout(10)
                ->get("{$this->baseUrl}/{$endpoint}", $params);

            // Record the request
            $this->quotaManager->recordRequest($requestType);

            if ($response->successful()) {
                $data = $response->json();
                
                Log::info("API request successful", [
                    'endpoint' => $endpoint,
                    'type' => $requestType,
                    'response_count' => count($data['response'] ?? [])
                ]);

                return $data['response'] ?? [];
            }

            Log::error('API request failed', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return [];

        } catch (\Exception $e) {
            Log::error('API request exception', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    // =========================================================================
    // TIMEZONE ENDPOINT
    // Frequency: Once per application lifecycle (cache indefinitely)
    // =========================================================================
    
    public function fetchTimezones(): array
    {
        return Cache::remember('api_timezones', now()->addDays(30), function () {
            return $this->makeApiRequest('timezone', [], 'timezone', 5) ?? [];
        });
    }

    // =========================================================================
    // COUNTRIES ENDPOINT  
    // Frequency: Once per week (countries rarely change)
    // =========================================================================
    
    public function fetchCountries(): array
    {
        return Cache::remember('api_countries', now()->addWeek(), function () {
            try {
                $response = Http::withHeaders($this->headers)
                    ->get("{$this->baseUrl}/countries");

                if ($response->successful()) {
                    return $response->json()['response'] ?? [];
                }

                Log::error('Failed to fetch countries');
                return [];
            } catch (\Exception $e) {
                Log::error('Exception fetching countries: ' . $e->getMessage());
                return [];
            }
        });
    }

    // =========================================================================
    // LEAGUES ENDPOINT
    // Frequency: Once per day (league info changes rarely)
    // =========================================================================
    
    public function fetchLeagues(string $country = null, int $season = null): array
    {
        $cacheKey = "api_leagues_" . ($country ?? 'all') . "_" . ($season ?? date('Y'));
        
        return Cache::remember($cacheKey, now()->addDay(), function () use ($country, $season) {
            try {
                $params = [];
                if ($country) $params['country'] = $country;
                if ($season) $params['season'] = $season;

                $response = Http::withHeaders($this->headers)
                    ->get("{$this->baseUrl}/leagues", $params);

                if ($response->successful()) {
                    return $response->json()['response'] ?? [];
                }

                Log::error('Failed to fetch leagues');
                return [];
            } catch (\Exception $e) {
                Log::error('Exception fetching leagues: ' . $e->getMessage());
                return [];
            }
        });
    }

    // =========================================================================
    // SEASONS ENDPOINT
    // Frequency: Once per month (seasons change predictably)
    // =========================================================================
    
    public function fetchSeasons(): array
    {
        return Cache::remember('api_seasons', now()->addMonth(), function () {
            try {
                $response = Http::withHeaders($this->headers)
                    ->get("{$this->baseUrl}/seasons");

                if ($response->successful()) {
                    return $response->json()['response'] ?? [];
                }

                Log::error('Failed to fetch seasons');
                return [];
            } catch (\Exception $e) {
                Log::error('Exception fetching seasons: ' . $e->getMessage());
                return [];
            }
        });
    }

    // =========================================================================
    // TEAMS ENDPOINT
    // Frequency: Once per season (team info changes rarely within season)
    // =========================================================================
    
    public function fetchTeams(string $league = 'PL', int $season = null): array
    {
        $season = $season ?? date('Y');
        $cacheKey = "api_teams_{$league}_{$season}";
        
        return Cache::remember($cacheKey, now()->addDays(7), function () use ($league, $season) {
            try {
                $leagueId = $this->getLeagueId($league);
                
                $response = Http::withHeaders($this->headers)
                    ->get("{$this->baseUrl}/teams", [
                        'league' => $leagueId,
                        'season' => $season
                    ]);

                if ($response->successful()) {
                    $data = $response->json();
                    return $this->processTeamsData($data['response'] ?? []);
                }

                Log::error('Failed to fetch teams');
                return [];
            } catch (\Exception $e) {
                Log::error('Exception fetching teams: ' . $e->getMessage());
                return [];
            }
        });
    }

    // =========================================================================
    // STANDINGS ENDPOINT
    // Frequency: Every 6 hours (standings change after matches)
    // =========================================================================
    
    public function fetchStandings(string $league = 'PL', int $season = null): array
    {
        $season = $season ?? date('Y');
        $cacheKey = "api_standings_{$league}_{$season}";
        
        return Cache::remember($cacheKey, now()->addHours(6), function () use ($league, $season) {
            try {
                $leagueId = $this->getLeagueId($league);
                
                $response = Http::withHeaders($this->headers)
                    ->get("{$this->baseUrl}/standings", [
                        'league' => $leagueId,
                        'season' => $season
                    ]);

                if ($response->successful()) {
                    return $response->json()['response'] ?? [];
                }

                Log::error('Failed to fetch standings');
                return [];
            } catch (\Exception $e) {
                Log::error('Exception fetching standings: ' . $e->getMessage());
                return [];
            }
        });
    }

    // =========================================================================
    // FIXTURES ENDPOINT
    // Frequency: Varies by status
    // - Historical: Cache for 1 week
    // - Future: Cache for 1 hour  
    // - Live: No cache (real-time)
    // - Today: Cache for 15 minutes
    // =========================================================================
    
    public function fetchFixtures(string $league = null, int $season = null, string $date = null): array
    {
        try {
            $params = ['timezone' => $this->timezone];
            
            if ($league) {
                $params['league'] = $this->getLeagueId($league);
            }
            if ($season) {
                $params['season'] = $season;
            }
            if ($date) {
                $params['date'] = $date;
            }

            $response = Http::withHeaders($this->headers)
                ->get("{$this->baseUrl}/fixtures", $params);

            if ($response->successful()) {
                $data = $response->json();
                return $this->processMatchesData($data['response'] ?? []);
            }

            Log::error('Failed to fetch fixtures');
            return [];
        } catch (\Exception $e) {
            Log::error('Exception fetching fixtures: ' . $e->getMessage());
            return [];
        }
    }

    // =========================================================================
    // FIXTURES BY DATE (Optimized for different scenarios)
    // =========================================================================
    
    public function fetchTodayMatches(): array
    {
        $cacheKey = 'api_today_matches_' . date('Y-m-d');
        
        return Cache::remember($cacheKey, now()->addMinutes(15), function () {
            $today = Carbon::today($this->timezone)->format('Y-m-d');
            return $this->fetchFixtures(null, null, $today);
        });
    }

    public function fetchMatchesByDateRange(string $from, string $to, string $league = null): array
    {
        $cacheKey = "api_matches_{$from}_{$to}_" . ($league ?? 'all');
        $cacheTime = Carbon::parse($to)->isPast() ? now()->addWeek() : now()->addHour();
        
        return Cache::remember($cacheKey, $cacheTime, function () use ($from, $to, $league) {
            try {
                $params = [
                    'from' => $from,
                    'to' => $to,
                    'timezone' => $this->timezone
                ];
                
                if ($league) {
                    $params['league'] = $this->getLeagueId($league);
                }

                $response = Http::withHeaders($this->headers)
                    ->get("{$this->baseUrl}/fixtures", $params);

                if ($response->successful()) {
                    $data = $response->json();
                    return $this->processMatchesData($data['response'] ?? []);
                }

                return [];
            } catch (\Exception $e) {
                Log::error('Exception fetching matches by date range: ' . $e->getMessage());
                return [];
            }
        });
    }

    // =========================================================================
    // LIVE FIXTURES ENDPOINT
    // Frequency: Every 15 seconds during live matches (no cache)
    // =========================================================================
    
    public function fetchLiveMatches(): array
    {
        try {
            $response = Http::withHeaders($this->headers)
                ->get("{$this->baseUrl}/fixtures", [
                    'live' => 'all',
                    'timezone' => $this->timezone
                ]);

            if ($response->successful()) {
                $data = $response->json();
                return $this->processMatchesData($data['response'] ?? []);
            }

            return [];
        } catch (\Exception $e) {
            Log::error('Exception fetching live matches: ' . $e->getMessage());
            return [];
        }
    }

    // =========================================================================
    // FIXTURES EVENTS ENDPOINT
    // Frequency: Real-time for live matches, cache completed matches for 1 day
    // =========================================================================
    
    public function fetchFixtureEvents(int $fixtureId): array
    {
        try {
            $response = Http::withHeaders($this->headers)
                ->get("{$this->baseUrl}/fixtures/events", [
                    'fixture' => $fixtureId
                ]);

            if ($response->successful()) {
                return $response->json()['response'] ?? [];
            }

            return [];
        } catch (\Exception $e) {
            Log::error('Exception fetching fixture events: ' . $e->getMessage());
            return [];
        }
    }

    // =========================================================================
    // FIXTURES LINEUPS ENDPOINT  
    // Frequency: Cache for 24 hours after match starts
    // =========================================================================
    
    public function fetchFixtureLineups(int $fixtureId): array
    {
        $cacheKey = "api_lineups_{$fixtureId}";
        
        return Cache::remember($cacheKey, now()->addDay(), function () use ($fixtureId) {
            try {
                $response = Http::withHeaders($this->headers)
                    ->get("{$this->baseUrl}/fixtures/lineups", [
                        'fixture' => $fixtureId
                    ]);

                if ($response->successful()) {
                    return $response->json()['response'] ?? [];
                }

                return [];
            } catch (\Exception $e) {
                Log::error('Exception fetching fixture lineups: ' . $e->getMessage());
                return [];
            }
        });
    }

    // =========================================================================
    // FIXTURES STATISTICS ENDPOINT
    // Frequency: Cache for 1 hour during match, 1 day after completion
    // =========================================================================
    
    public function fetchFixtureStatistics(int $fixtureId): array
    {
        $cacheKey = "api_fixture_stats_{$fixtureId}";
        
        return Cache::remember($cacheKey, now()->addHour(), function () use ($fixtureId) {
            try {
                $response = Http::withHeaders($this->headers)
                    ->get("{$this->baseUrl}/fixtures/statistics", [
                        'fixture' => $fixtureId
                    ]);

                if ($response->successful()) {
                    return $response->json()['response'] ?? [];
                }

                return [];
            } catch (\Exception $e) {
                Log::error('Exception fetching fixture statistics: ' . $e->getMessage());
                return [];
            }
        });
    }

    // =========================================================================
    // PLAYERS ENDPOINT
    // Frequency: Once per week (player info changes rarely)
    // =========================================================================
    
    public function fetchPlayers(int $teamId, int $season = null): array
    {
        $season = $season ?? date('Y');
        $cacheKey = "api_players_{$teamId}_{$season}";
        
        return Cache::remember($cacheKey, now()->addWeek(), function () use ($teamId, $season) {
            try {
                $response = Http::withHeaders($this->headers)
                    ->get("{$this->baseUrl}/players", [
                        'team' => $teamId,
                        'season' => $season
                    ]);

                if ($response->successful()) {
                    return $response->json()['response'] ?? [];
                }

                return [];
            } catch (\Exception $e) {
                Log::error('Exception fetching players: ' . $e->getMessage());
                return [];
            }
        });
    }

    // =========================================================================
    // TEAM STATISTICS ENDPOINT
    // Frequency: Every 6 hours during season
    // =========================================================================
    
    public function fetchTeamStatistics(int $teamId, int $leagueId, int $season = null): array
    {
        $season = $season ?? date('Y');
        $cacheKey = "api_team_stats_{$teamId}_{$leagueId}_{$season}";
        
        return Cache::remember($cacheKey, now()->addHours(6), function () use ($teamId, $leagueId, $season) {
            try {
                $response = Http::withHeaders($this->headers)
                    ->get("{$this->baseUrl}/teams/statistics", [
                        'team' => $teamId,
                        'league' => $leagueId,
                        'season' => $season
                    ]);

                if ($response->successful()) {
                    return $response->json()['response'] ?? [];
                }

                return [];
            } catch (\Exception $e) {
                Log::error('Exception fetching team statistics: ' . $e->getMessage());
                return [];
            }
        });
    }

    // =========================================================================
    // INJURIES ENDPOINT
    // Frequency: Daily updates
    // =========================================================================
    
    public function fetchInjuries(int $leagueId = null, int $season = null): array
    {
        $season = $season ?? date('Y');
        $cacheKey = "api_injuries_" . ($leagueId ?? 'all') . "_{$season}";
        
        return Cache::remember($cacheKey, now()->addDay(), function () use ($leagueId, $season) {
            try {
                $params = ['season' => $season];
                if ($leagueId) $params['league'] = $leagueId;

                $response = Http::withHeaders($this->headers)
                    ->get("{$this->baseUrl}/injuries", $params);

                if ($response->successful()) {
                    return $response->json()['response'] ?? [];
                }

                return [];
            } catch (\Exception $e) {
                Log::error('Exception fetching injuries: ' . $e->getMessage());
                return [];
            }
        });
    }

    // =========================================================================
    // ODDS ENDPOINTS
    // Frequency: 
    // - Pre-match: Every 30 minutes
    // - Live: Every 15 seconds during match
    // =========================================================================
    
    public function fetchFixtureOdds(int $fixtureId): array
    {
        try {
            $response = Http::withHeaders($this->headers)
                ->get("{$this->baseUrl}/odds", [
                    'fixture' => $fixtureId
                ]);

            if ($response->successful()) {
                return $response->json()['response'] ?? [];
            }

            return [];
        } catch (\Exception $e) {
            Log::error('Exception fetching fixture odds: ' . $e->getMessage());
            return [];
        }
    }

    public function fetchLiveOdds(): array
    {
        try {
            $response = Http::withHeaders($this->headers)
                ->get("{$this->baseUrl}/odds/live");

            if ($response->successful()) {
                return $response->json()['response'] ?? [];
            }

            return [];
        } catch (\Exception $e) {
            Log::error('Exception fetching live odds: ' . $e->getMessage());
            return [];
        }
    }

    // =========================================================================
    // PREDICTIONS ENDPOINT
    // Frequency: Once per match (cache until match starts)
    // =========================================================================
    
    public function fetchPredictions(int $fixtureId): array
    {
        $cacheKey = "api_predictions_{$fixtureId}";
        
        return Cache::remember($cacheKey, now()->addHours(24), function () use ($fixtureId) {
            try {
                $response = Http::withHeaders($this->headers)
                    ->get("{$this->baseUrl}/predictions", [
                        'fixture' => $fixtureId
                    ]);

                if ($response->successful()) {
                    return $response->json()['response'] ?? [];
                }

                return [];
            } catch (\Exception $e) {
                Log::error('Exception fetching predictions: ' . $e->getMessage());
                return [];
            }
        });
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    private function processTeamsData(array $teams): array
    {
        $processedTeams = [];

        foreach ($teams as $teamData) {
            $team = $teamData['team'] ?? $teamData;
            $processedTeams[] = [
                'name' => $team['name'],
                'short_name' => $team['code'] ?? $this->generateSafeShortName($team['name']),
                'logo' => $team['logo'] ?? null,
                'external_id' => (string) $team['id'],
                'country' => $team['country'] ?? null,
                'league' => null,
                'is_active' => true,
            ];
        }

        return $processedTeams;
    }

    private function processMatchesData(array $matches): array
    {
        $processedMatches = [];

        foreach ($matches as $matchData) {
            $fixture = $matchData['fixture'] ?? $matchData;
            $teams = $matchData['teams'] ?? [];
            $goals = $matchData['goals'] ?? [];
            $league = $matchData['league'] ?? [];
            
            // Enhanced date processing with timezone handling
            $matchDate = Carbon::parse($fixture['date'])
                ->utc()
                ->setTimezone($this->timezone);
            
            // Fallback to timestamp if available and more accurate
            if ($matchDate->format('H:i') === '00:00' && isset($fixture['timestamp'])) {
                $matchDate = Carbon::createFromTimestamp($fixture['timestamp'])
                    ->setTimezone($this->timezone);
            }
            
            $processedMatches[] = [
                'external_id' => (string) $fixture['id'],
                'home_team_external_id' => (string) $teams['home']['id'],
                'away_team_external_id' => (string) $teams['away']['id'],
                'home_team_name' => $teams['home']['name'],
                'away_team_name' => $teams['away']['name'],
                'match_date' => $matchDate,
                'home_goals' => $goals['home'] ?? null,
                'away_goals' => $goals['away'] ?? null,
                'status' => $this->mapMatchStatus($fixture['status']['short']),
                'league' => $league['name'] ?? null,
                'season' => $league['season'] ?? date('Y'),
                'round' => $this->extractRoundNumber($league['round'] ?? null),
                'venue' => $fixture['venue']['name'] ?? null,
                'referee' => $fixture['referee'] ?? null,
                'odds' => null,
            ];
        }

        return $processedMatches;
    }

    private function mapMatchStatus(string $apiStatus): string
    {
        return match ($apiStatus) {
            'TBD', 'NS' => 'scheduled',
            '1H', '2H', 'HT', 'LIVE' => 'live',
            'FT', 'AET', 'PEN' => 'finished',
            'SUSP', 'PST', 'CANC', 'ABD' => 'postponed',
            default => 'scheduled',
        };
    }

    private function getLeagueId(string $league): int
    {
        return match($league) {
            'PL' => 39,   // Premier League
            'PD' => 140,  // La Liga
            'BL1' => 78,  // Bundesliga
            'SA' => 135,  // Serie A
            'FL1' => 61,  // Ligue 1
            'CL' => 2,    // Champions League
            'EL' => 3,    // Europa League
            'WC' => 1,    // World Cup
            'EC' => 4,    // European Championship
            default => 39,
        };
    }

    private function extractRoundNumber(?string $round): ?string
    {
        if (!$round) return null;
        
        if (preg_match('/(\d+)/', $round, $matches)) {
            return $matches[1];
        }
        
        return $round;
    }

    private function generateSafeShortName(string $teamName): string
    {
        $shortName = $this->removeAccents($teamName);
        $words = explode(' ', $shortName);
        $result = '';
        
        foreach ($words as $word) {
            if (strlen($result) >= 3) break;
            $cleanWord = preg_replace('/[^A-Za-z0-9]/', '', $word);
            if (!empty($cleanWord)) {
                $result .= strtoupper(substr($cleanWord, 0, 1));
            }
        }
        
        if (strlen($result) < 3 && !empty($words[0])) {
            $firstWord = preg_replace('/[^A-Za-z0-9]/', '', $words[0]);
            $result = strtoupper(substr($firstWord, 0, 3));
        }
        
        return substr($result ?: 'TBD', 0, 3);
    }

    private function removeAccents(string $string): string
    {
        $accents = [
            'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a', 'ā' => 'a', 'ą' => 'a', 'å' => 'a', 'ă' => 'a',
            'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e', 'ē' => 'e', 'ę' => 'e', 'ě' => 'e',
            'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i', 'ī' => 'i', 'į' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o', 'ō' => 'o', 'ø' => 'o', 'õ' => 'o',
            'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u', 'ū' => 'u', 'ů' => 'u',
            'ý' => 'y', 'ÿ' => 'y',
            'ñ' => 'n', 'ň' => 'n',
            'ç' => 'c', 'č' => 'c', 'ć' => 'c',
            'š' => 's', 'ś' => 's',
            'ž' => 'z', 'ź' => 'z', 'ż' => 'z',
            'ď' => 'd', 'đ' => 'd',
            'ť' => 't',
            'ř' => 'r',
            'ľ' => 'l', 'ł' => 'l',
            'ĺ' => 'l',
        ];
        
        return strtr(mb_strtolower($string), $accents);
    }

    // =========================================================================
    // RATE LIMITING HELPER
    // =========================================================================
    
    public function checkRateLimit(): array
    {
        try {
            $response = Http::withHeaders($this->headers)
                ->get("{$this->baseUrl}/status");

            if ($response->successful()) {
                return [
                    'requests_limit' => $response->header('x-ratelimit-requests-limit'),
                    'requests_remaining' => $response->header('x-ratelimit-requests-remaining'),
                    'requests_per_minute_limit' => $response->header('X-RateLimit-Limit'),
                    'requests_per_minute_remaining' => $response->header('X-RateLimit-Remaining'),
                ];
            }

            return [];
        } catch (\Exception $e) {
            Log::error('Exception checking rate limit: ' . $e->getMessage());
            return [];
        }
    }
}