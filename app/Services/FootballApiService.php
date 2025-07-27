<?php

namespace App\Services;

use App\Models\Team;
use App\Models\FootballMatch;
use App\Models\TeamStatistic;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class FootballApiService
{
    private string $baseUrl;
    private string $apiKey;
    private array $headers;

    public function __construct()
    {
        $this->baseUrl = config('services.football_api.base_url');
        $this->apiKey = config('services.football_api.key');
        $this->headers = [
            'x-apisports-key' => $this->apiKey,
            'Accept' => 'application/json',
        ];
    }

    public function fetchTeams(string $league = 'PL'): array
    {
        try {
            $leagueId = $this->getLeagueId($league);
            $season = date('Y');
            
            $response = Http::withHeaders($this->headers)
                ->get("{$this->baseUrl}/teams", [
                    'league' => $leagueId,
                    'season' => $season
                ]);

            if ($response->successful()) {
                $data = $response->json();
                return $this->processTeamsData($data['response'] ?? []);
            }

            Log::error('Failed to fetch teams', [
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return [];
        } catch (\Exception $e) {
            Log::error('Exception fetching teams: ' . $e->getMessage());
            return [];
        }
    }

    public function fetchMatches(string $league = 'PL', ?string $season = null): array
    {
        try {
            $leagueId = $this->getLeagueId($league);
            $season = $season ?? date('Y');
            
            $response = Http::withHeaders($this->headers)
                ->get("{$this->baseUrl}/fixtures", [
                    'league' => $leagueId,
                    'season' => $season
                ]);

            if ($response->successful()) {
                $data = $response->json();
                return $this->processMatchesData($data['response'] ?? []);
            }

            Log::error('Failed to fetch matches', [
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return [];
        } catch (\Exception $e) {
            Log::error('Exception fetching matches: ' . $e->getMessage());
            return [];
        }
    }

    public function fetchLiveScores(): array
    {
        try {
            $response = Http::withHeaders($this->headers)
                ->get("{$this->baseUrl}/fixtures", [
                    'live' => 'all'
                ]);

            if ($response->successful()) {
                $data = $response->json();
                return $this->processMatchesData($data['response'] ?? []);
            }

            return [];
        } catch (\Exception $e) {
            Log::error('Exception fetching live scores: ' . $e->getMessage());
            return [];
        }
    }

    public function fetchTodayMatches(): array
    {
        try {
            $today = Carbon::today()->format('Y-m-d');
            
            $response = Http::withHeaders($this->headers)
                ->get("{$this->baseUrl}/fixtures", [
                    'date' => $today
                ]);

            if ($response->successful()) {
                $data = $response->json();
                return $this->processMatchesData($data['response'] ?? []);
            }

            Log::error('Failed to fetch today matches', [
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return [];
        } catch (\Exception $e) {
            Log::error('Exception fetching today matches: ' . $e->getMessage());
            return [];
        }
    }

    public function fetchTeamStatistics(int $teamId, string $season = null): ?array
    {
        try {
            $season = $season ?? date('Y');
            $response = Http::withHeaders($this->headers)
                ->get("{$this->baseUrl}/fixtures", [
                    'team' => $teamId,
                    'season' => $season
                ]);

            if ($response->successful()) {
                $data = $response->json();
                return $this->calculateTeamStats($data['response'] ?? [], $teamId);
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Exception fetching team statistics: ' . $e->getMessage());
            return null;
        }
    }

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
            
            $matchDate = Carbon::parse($fixture['date']);
            
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

    private function calculateTeamStats(array $matches, int $teamId): array
    {
        $stats = [
            'matches_played' => 0,
            'wins' => 0,
            'draws' => 0,
            'losses' => 0,
            'goals_for' => 0,
            'goals_against' => 0,
            'points' => 0,
            'form' => [],
            'home_stats' => ['played' => 0, 'wins' => 0, 'draws' => 0, 'losses' => 0],
            'away_stats' => ['played' => 0, 'wins' => 0, 'draws' => 0, 'losses' => 0],
        ];

        foreach ($matches as $match) {
            $fixture = $match['fixture'] ?? [];
            $teams = $match['teams'] ?? [];
            $goals = $match['goals'] ?? [];
            
            if ($fixture['status']['short'] !== 'FT') continue;

            $isHome = $teams['home']['id'] == $teamId;
            $homeGoals = $goals['home'] ?? 0;
            $awayGoals = $goals['away'] ?? 0;

            $stats['matches_played']++;

            if ($isHome) {
                $stats['goals_for'] += $homeGoals;
                $stats['goals_against'] += $awayGoals;
                $stats['home_stats']['played']++;

                if ($homeGoals > $awayGoals) {
                    $stats['wins']++;
                    $stats['points'] += 3;
                    $stats['home_stats']['wins']++;
                    array_unshift($stats['form'], 'win');
                } elseif ($homeGoals < $awayGoals) {
                    $stats['losses']++;
                    $stats['home_stats']['losses']++;
                    array_unshift($stats['form'], 'loss');
                } else {
                    $stats['draws']++;
                    $stats['points'] += 1;
                    $stats['home_stats']['draws']++;
                    array_unshift($stats['form'], 'draw');
                }
            } else {
                $stats['goals_for'] += $awayGoals;
                $stats['goals_against'] += $homeGoals;
                $stats['away_stats']['played']++;

                if ($awayGoals > $homeGoals) {
                    $stats['wins']++;
                    $stats['points'] += 3;
                    $stats['away_stats']['wins']++;
                    array_unshift($stats['form'], 'win');
                } elseif ($awayGoals < $homeGoals) {
                    $stats['losses']++;
                    $stats['away_stats']['losses']++;
                    array_unshift($stats['form'], 'loss');
                } else {
                    $stats['draws']++;
                    $stats['points'] += 1;
                    $stats['away_stats']['draws']++;
                    array_unshift($stats['form'], 'draw');
                }
            }
        }

        $stats['goals_difference'] = $stats['goals_for'] - $stats['goals_against'];
        $stats['avg_goals_for'] = $stats['matches_played'] > 0 ? 
            round($stats['goals_for'] / $stats['matches_played'], 2) : 0;
        $stats['avg_goals_against'] = $stats['matches_played'] > 0 ? 
            round($stats['goals_against'] / $stats['matches_played'], 2) : 0;
        $stats['form'] = array_slice($stats['form'], 0, 5);

        return $stats;
    }

    public function syncTeams(string $league = 'PL'): int
    {
        $teams = $this->fetchTeams($league);
        $synced = 0;

        foreach ($teams as $teamData) {
            Team::updateOrCreate(
                ['external_id' => $teamData['external_id']],
                $teamData
            );
            $synced++;
        }

        return $synced;
    }

    public function syncMatches(string $league = 'PL', ?string $season = null): int
    {
        $matches = $this->fetchMatches($league, $season);
        $synced = 0;

        foreach ($matches as $matchData) {
            $synced += $this->syncSingleMatch($matchData);
        }

        return $synced;
    }

    public function syncTodayMatches(): int
    {
        $matches = $this->fetchTodayMatches();
        $synced = 0;

        foreach ($matches as $matchData) {
            $synced += $this->syncSingleMatch($matchData);
        }

        return $synced;
    }

    public function syncSingleMatch(array $matchData): int
    {
        $homeTeam = Team::where('external_id', $matchData['home_team_external_id'])->first();
        $awayTeam = Team::where('external_id', $matchData['away_team_external_id'])->first();

        // Si los equipos no existen, crear equipos temporales
        if (!$homeTeam) {
            $homeTeam = Team::create([
                'name' => $matchData['home_team_name'],
                'short_name' => $this->generateSafeShortName($matchData['home_team_name']),
                'external_id' => $matchData['home_team_external_id'],
                'league' => $matchData['league'],
                'is_active' => true,
            ]);
        }

        if (!$awayTeam) {
            $awayTeam = Team::create([
                'name' => $matchData['away_team_name'],
                'short_name' => $this->generateSafeShortName($matchData['away_team_name']),
                'external_id' => $matchData['away_team_external_id'],
                'league' => $matchData['league'],
                'is_active' => true,
            ]);
        }

        $matchData['home_team_id'] = $homeTeam->id;
        $matchData['away_team_id'] = $awayTeam->id;
        unset($matchData['home_team_external_id'], $matchData['away_team_external_id'], 
              $matchData['home_team_name'], $matchData['away_team_name']);

        FootballMatch::updateOrCreate(
            ['external_id' => $matchData['external_id']],
            $matchData
        );

        return 1;
    }

    private function getLeagueId(string $league): int
    {
        return match($league) {
            'PL' => 39,  // Premier League
            'PD' => 140, // La Liga
            'BL1' => 78, // Bundesliga
            'SA' => 135, // Serie A
            'FL1' => 61, // Ligue 1
            default => 39,
        };
    }

    private function extractRoundNumber(?string $round): ?string
    {
        if (!$round) return null;
        
        // Extract number from strings like "Regular Season - 1", "Matchday 1", etc.
        if (preg_match('/(\d+)/', $round, $matches)) {
            return $matches[1];
        }
        
        return $round;
    }

    private function generateSafeShortName(string $teamName): string
    {
        // Remove accents and special characters
        $shortName = $this->removeAccents($teamName);
        
        // Take first 3 characters of each word, max 3 characters total
        $words = explode(' ', $shortName);
        $result = '';
        
        foreach ($words as $word) {
            if (strlen($result) >= 3) break;
            $cleanWord = preg_replace('/[^A-Za-z0-9]/', '', $word);
            if (!empty($cleanWord)) {
                $result .= strtoupper(substr($cleanWord, 0, 1));
            }
        }
        
        // If we don't have 3 characters, pad with the first word
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
}