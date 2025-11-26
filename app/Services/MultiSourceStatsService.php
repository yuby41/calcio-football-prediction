<?php

namespace App\Services;

use App\Services\EnhancedFootballApiService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * Multi-Source Statistics Service
 * 
 * Combines multiple real data sources for better team statistics
 */
class MultiSourceStatsService
{
    private EnhancedFootballApiService $apiSportsService;

    public function __construct(EnhancedFootballApiService $apiSportsService)
    {
        $this->apiSportsService = $apiSportsService;
    }

    /**
     * Get comprehensive team statistics from multiple sources
     */
    public function getComprehensiveTeamStats(int $teamId, int $season = null): array
    {
        $season = $season ?? date('Y');
        $cacheKey = "multi_stats_{$teamId}_{$season}";

        return Cache::remember($cacheKey, now()->addHours(6), function () use ($teamId, $season) {
            $stats = [
                'team_id' => $teamId,
                'season' => $season,
                'sources_used' => [],
                'api_sports' => null,
                'football_data' => null,
                'combined_stats' => null,
            ];

            // Source 1: API-Sports (ya implementado)
            try {
                $apiSportsStats = $this->apiSportsService->fetchTeamStatistics($teamId, 39, $season);
                if (!empty($apiSportsStats)) {
                    $stats['api_sports'] = $this->processApiSportsData($apiSportsStats);
                    $stats['sources_used'][] = 'api_sports';
                }
            } catch (\Exception $e) {
                Log::error("API-Sports stats failed for team {$teamId}: " . $e->getMessage());
            }

            // Source 2: Football-Data.org (FREE)
            try {
                $footballDataStats = $this->fetchFootballDataStats($teamId, $season);
                if (!empty($footballDataStats)) {
                    $stats['football_data'] = $footballDataStats;
                    $stats['sources_used'][] = 'football_data';
                }
            } catch (\Exception $e) {
                Log::error("Football-Data stats failed for team {$teamId}: " . $e->getMessage());
            }

            // Combine statistics from all sources
            $stats['combined_stats'] = $this->combineStatistics($stats);

            Log::info("Multi-source stats gathered", [
                'team_id' => $teamId,
                'sources' => $stats['sources_used'],
                'data_quality' => $this->assessDataQuality($stats)
            ]);

            return $stats;
        });
    }

    /**
     * Fetch from Football-Data.org (FREE - 10 requests/minute)
     */
    private function fetchFootballDataStats(int $teamId, int $season): ?array
    {
        $apiKey = env('FOOTBALL_DATA_API_KEY'); // Gratis en football-data.org
        
        if (!$apiKey) {
            return null;
        }

        try {
            // Football-Data uses different team IDs, need mapping
            $footballDataTeamId = $this->mapToFootballDataId($teamId);
            
            if (!$footballDataTeamId) {
                return null;
            }

            $response = Http::withHeaders([
                'X-Auth-Token' => $apiKey,
                'Accept' => 'application/json'
            ])->get("https://api.football-data.org/v4/teams/{$footballDataTeamId}");

            if ($response->successful()) {
                return $this->processFootballDataResponse($response->json());
            }

            return null;

        } catch (\Exception $e) {
            Log::error("Football-Data API error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Process API-Sports data into standard format
     */
    private function processApiSportsData(array $data): array
    {
        return [
            'source' => 'api_sports',
            'goals_for' => [
                'total' => $data['goals']['for']['total']['total'] ?? 0,
                'home' => $data['goals']['for']['total']['home'] ?? 0,
                'away' => $data['goals']['for']['total']['away'] ?? 0,
                'average' => $data['goals']['for']['average']['total'] ?? 0,
            ],
            'goals_against' => [
                'total' => $data['goals']['against']['total']['total'] ?? 0,
                'home' => $data['goals']['against']['total']['home'] ?? 0,
                'away' => $data['goals']['against']['total']['away'] ?? 0,
                'average' => $data['goals']['against']['average']['total'] ?? 0,
            ],
            'matches' => [
                'played' => $data['fixtures']['played']['total'] ?? 0,
                'wins' => $data['fixtures']['wins']['total'] ?? 0,
                'draws' => $data['fixtures']['draws']['total'] ?? 0,
                'losses' => $data['fixtures']['loses']['total'] ?? 0,
            ],
            'form' => $data['form'] ?? null,
            'data_quality' => 'high'
        ];
    }

    /**
     * Process Football-Data.org response
     */
    private function processFootballDataResponse(array $data): array
    {
        // Extract running competitions and recent matches
        $runningCompetitions = $data['runningCompetitions'] ?? [];
        $teamStats = null;

        // Find current season stats
        foreach ($runningCompetitions as $competition) {
            if ($competition['season']['currentMatchday'] ?? 0 > 0) {
                // Get team's matches in this competition
                $teamStats = [
                    'source' => 'football_data',
                    'competition' => $competition['name'],
                    'season' => $competition['season']['startDate'] ?? date('Y'),
                    'data_quality' => 'medium'
                ];
                break;
            }
        }

        return $teamStats ?? [
            'source' => 'football_data',
            'data_quality' => 'low',
            'error' => 'No current season data found'
        ];
    }

    /**
     * Combine statistics from multiple sources
     */
    private function combineStatistics(array $allStats): array
    {
        $apiStats = $allStats['api_sports'];
        $footballDataStats = $allStats['football_data'];

        if (!$apiStats && !$footballDataStats) {
            return ['error' => 'No data available from any source'];
        }

        // If we only have one source, use it
        if ($apiStats && !$footballDataStats) {
            return $apiStats;
        }
        
        if ($footballDataStats && !$apiStats) {
            return $footballDataStats;
        }

        // If we have both, combine intelligently
        return [
            'source' => 'combined_multi_source',
            'primary_source' => 'api_sports',
            'secondary_source' => 'football_data',
            
            // Use API-Sports as primary (higher quality)
            'goals_for' => $apiStats['goals_for'],
            'goals_against' => $apiStats['goals_against'],
            'matches' => $apiStats['matches'],
            'form' => $apiStats['form'],
            
            // Add Football-Data as validation
            'validation_data' => [
                'football_data_competition' => $footballDataStats['competition'] ?? null,
            ],
            
            'data_quality' => 'very_high', // Multi-source = highest quality
            'confidence_boost' => 0.15, // 15% confidence boost for multi-source
        ];
    }

    /**
     * Assess overall data quality
     */
    private function assessDataQuality(array $stats): string
    {
        $sourceCount = count($stats['sources_used']);
        
        if ($sourceCount >= 2) {
            return 'very_high'; // Multiple sources
        } elseif ($sourceCount === 1 && in_array('api_sports', $stats['sources_used'])) {
            return 'high'; // Single high-quality source
        } elseif ($sourceCount === 1) {
            return 'medium'; // Single medium-quality source
        } else {
            return 'low'; // No sources
        }
    }

    /**
     * Map API-Sports team ID to Football-Data team ID
     */
    private function mapToFootballDataId(int $apiSportsId): ?int
    {
        // Common Premier League teams mapping
        $mapping = [
            50 => 65,   // Manchester City
            47 => 73,   // Tottenham
            33 => 57,   // Manchester United  
            40 => 64,   // Liverpool
            42 => 61,   // Arsenal
            49 => 66,   // Chelsea
            // Add more mappings as needed
        ];

        return $mapping[$apiSportsId] ?? null;
    }

    /**
     * Get available data sources status
     */
    public function getDataSourcesStatus(): array
    {
        return [
            'api_sports' => [
                'available' => !empty(config('services.football_api.key')),
                'quota_remaining' => '150+ daily requests',
                'quality' => 'high',
                'cost' => 'paid'
            ],
            'football_data' => [
                'available' => !empty(env('FOOTBALL_DATA_API_KEY')),
                'quota_remaining' => '10 requests/minute (free)',
                'quality' => 'medium',
                'cost' => 'free'
            ],
            'recommended_next_sources' => [
                'RapidAPI Sports Bundle',
                'SportsRadar',
                'The Odds API'
            ]
        ];
    }
}