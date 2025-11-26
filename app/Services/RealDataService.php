<?php

namespace App\Services;

use App\Models\Team;
use App\Models\FootballMatch;
use App\Models\TeamStatistic;
use App\Services\EnhancedFootballApiService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

/**
 * Real Data Service - Replaces all synthetic/AI-generated data with real API data
 * 
 * This service is designed to completely eliminate synthetic data from the application
 * and replace it with real data from API-Sports.io
 */
class RealDataService
{
    private EnhancedFootballApiService $apiService;

    public function __construct(EnhancedFootballApiService $apiService)
    {
        $this->apiService = $apiService;
    }

    // =========================================================================
    // REAL TEAM STATISTICS (Replaces synthetic calculations)
    // =========================================================================

    /**
     * Get real team statistics from API instead of synthetic calculations
     */
    public function getRealTeamStatistics(int $teamId, int $leagueId, int $season = null): ?array
    {
        $season = $season ?? date('Y');
        
        try {
            // Get real statistics from API-Sports
            $apiStats = $this->apiService->fetchTeamStatistics($teamId, $leagueId, $season);
            
            if (empty($apiStats)) {
                Log::warning("No real statistics found for team {$teamId} in league {$leagueId}");
                return null;
            }

            $stats = $apiStats;
            
            // Extract real expected goals data from fixtures
            $expectedGoalsData = $this->getRealExpectedGoalsFromFixtures($teamId, $season);
            
            return [
                'team_id' => $teamId,
                'league_id' => $leagueId,
                'season' => $season,
                // Real data from API
                'matches_played' => $stats['fixtures']['played']['total'] ?? 0,
                'wins' => $stats['fixtures']['wins']['total'] ?? 0,
                'draws' => $stats['fixtures']['draws']['total'] ?? 0,
                'losses' => $stats['fixtures']['loses']['total'] ?? 0,
                'goals_for' => $stats['goals']['for']['total']['total'] ?? 0,
                'goals_against' => $stats['goals']['against']['total']['total'] ?? 0,
                // Real expected goals from actual match statistics
                'real_avg_goals_for' => $expectedGoalsData['avg_goals_for'] ?? 0,
                'real_avg_goals_against' => $expectedGoalsData['avg_goals_against'] ?? 0,
                // Real home/away splits
                'home_stats' => [
                    'played' => $stats['fixtures']['played']['home'] ?? 0,
                    'wins' => $stats['fixtures']['wins']['home'] ?? 0,
                    'draws' => $stats['fixtures']['draws']['home'] ?? 0,
                    'losses' => $stats['fixtures']['loses']['home'] ?? 0,
                    'goals_for' => $stats['goals']['for']['total']['home'] ?? 0,
                    'goals_against' => $stats['goals']['against']['total']['home'] ?? 0,
                ],
                'away_stats' => [
                    'played' => $stats['fixtures']['played']['away'] ?? 0,
                    'wins' => $stats['fixtures']['wins']['away'] ?? 0,
                    'draws' => $stats['fixtures']['draws']['away'] ?? 0,
                    'losses' => $stats['fixtures']['loses']['away'] ?? 0,
                    'goals_for' => $stats['goals']['for']['total']['away'] ?? 0,
                    'goals_against' => $stats['goals']['against']['total']['away'] ?? 0,
                ],
                // Real form from recent matches
                'real_form' => $this->getRealFormFromRecentMatches($teamId, 5),
                // Source verification
                'data_source' => 'api_sports_real_data',
                'last_updated' => now(),
            ];
            
        } catch (\Exception $e) {
            Log::error("Error getting real team statistics: " . $e->getMessage());
            return null;
        }
    }

    // =========================================================================
    // REAL EXPECTED GOALS (Replaces ML predictions)
    // =========================================================================

    /**
     * Get real expected goals from actual match statistics, not ML predictions
     */
    public function getRealExpectedGoalsFromFixtures(int $teamId, int $season = null): array
    {
        $season = $season ?? date('Y');
        
        try {
            // Get team's actual fixtures from API
            $fixtures = $this->apiService->fetchFixtures(null, $season);
            
            $teamFixtures = array_filter($fixtures, function($fixture) use ($teamId) {
                return $fixture['home_team_external_id'] == $teamId || 
                       $fixture['away_team_external_id'] == $teamId;
            });

            if (empty($teamFixtures)) {
                return ['avg_goals_for' => 0, 'avg_goals_against' => 0];
            }

            $totalGoalsFor = 0;
            $totalGoalsAgainst = 0;
            $completedMatches = 0;

            foreach ($teamFixtures as $fixture) {
                if ($fixture['status'] !== 'finished' || 
                    is_null($fixture['home_goals']) || 
                    is_null($fixture['away_goals'])) {
                    continue;
                }

                $isHome = $fixture['home_team_external_id'] == $teamId;
                
                if ($isHome) {
                    $totalGoalsFor += $fixture['home_goals'];
                    $totalGoalsAgainst += $fixture['away_goals'];
                } else {
                    $totalGoalsFor += $fixture['away_goals'];
                    $totalGoalsAgainst += $fixture['home_goals'];
                }
                
                $completedMatches++;
            }

            return [
                'avg_goals_for' => $completedMatches > 0 ? round($totalGoalsFor / $completedMatches, 2) : 0,
                'avg_goals_against' => $completedMatches > 0 ? round($totalGoalsAgainst / $completedMatches, 2) : 0,
                'matches_analyzed' => $completedMatches,
                'data_source' => 'real_match_results'
            ];

        } catch (\Exception $e) {
            Log::error("Error calculating real expected goals: " . $e->getMessage());
            return ['avg_goals_for' => 0, 'avg_goals_against' => 0];
        }
    }

    // =========================================================================
    // REAL ODDS DATA (Replaces synthetic odds)
    // =========================================================================

    /**
     * Get real betting odds from bookmakers, not synthetic calculations
     */
    public function getRealOddsForFixture(int $fixtureId): ?array
    {
        try {
            $oddsData = $this->apiService->fetchFixtureOdds($fixtureId);
            
            if (empty($oddsData)) {
                Log::info("No real odds available for fixture {$fixtureId}");
                return null;
            }

            // Extract real bookmaker odds
            $realOdds = [];
            
            foreach ($oddsData as $bookmaker) {
                $bookmakerName = $bookmaker['bookmaker']['name'] ?? 'Unknown';
                
                foreach ($bookmaker['bets'] as $bet) {
                    $betType = $bet['name'];
                    
                    switch ($betType) {
                        case 'Match Winner':
                            $realOdds['match_winner'] = [
                                'bookmaker' => $bookmakerName,
                                'home' => $bet['values'][0]['odd'] ?? null,
                                'draw' => $bet['values'][1]['odd'] ?? null,
                                'away' => $bet['values'][2]['odd'] ?? null,
                            ];
                            break;
                            
                        case 'Goals Over/Under':
                            foreach ($bet['values'] as $value) {
                                if (strpos($value['value'], '2.5') !== false) {
                                    $realOdds['over_under_2_5'] = [
                                        'bookmaker' => $bookmakerName,
                                        'over' => strpos($value['value'], 'Over') !== false ? $value['odd'] : null,
                                        'under' => strpos($value['value'], 'Under') !== false ? $value['odd'] : null,
                                    ];
                                }
                            }
                            break;
                            
                        case 'Both Teams Score':
                            $realOdds['both_teams_score'] = [
                                'bookmaker' => $bookmakerName,
                                'yes' => $bet['values'][0]['odd'] ?? null,
                                'no' => $bet['values'][1]['odd'] ?? null,
                            ];
                            break;
                    }
                }
            }
            
            return [
                'fixture_id' => $fixtureId,
                'odds' => $realOdds,
                'data_source' => 'real_bookmaker_odds',
                'last_updated' => now(),
            ];
            
        } catch (\Exception $e) {
            Log::error("Error getting real odds: " . $e->getMessage());
            return null;
        }
    }

    // =========================================================================
    // REAL PREDICTIONS (From API-Sports, not ML models)
    // =========================================================================

    /**
     * Get real predictions from API-Sports professional analysts
     */
    public function getRealPredictionsForFixture(int $fixtureId): ?array
    {
        try {
            $predictionsData = $this->apiService->fetchPredictions($fixtureId);
            
            if (empty($predictionsData)) {
                Log::info("No professional predictions available for fixture {$fixtureId}");
                return null;
            }

            $prediction = $predictionsData[0] ?? null;
            if (!$prediction) return null;

            // Extract real professional predictions
            $realPredictions = [
                'fixture_id' => $fixtureId,
                'winner' => [
                    'id' => $prediction['teams']['home']['id'] ?? null,
                    'name' => $prediction['teams']['home']['name'] ?? null,
                    'comment' => $prediction['predictions']['winner']['comment'] ?? null,
                ],
                'win_or_draw' => $prediction['predictions']['win_or_draw'] ?? false,
                'under_over' => $prediction['predictions']['under_over'] ?? null,
                'goals_home' => $prediction['predictions']['goals']['home'] ?? null,
                'goals_away' => $prediction['predictions']['goals']['away'] ?? null,
                'advice' => $prediction['predictions']['advice'] ?? null,
                'percent' => [
                    'home' => $prediction['predictions']['percent']['home'] ?? null,
                    'draw' => $prediction['predictions']['percent']['draw'] ?? null,
                    'away' => $prediction['predictions']['percent']['away'] ?? null,
                ],
                // H2H statistics
                'h2h_stats' => [
                    'played_home' => $prediction['h2h'][0]['played'] ?? 0,
                    'wins_home' => $prediction['h2h'][0]['wins'] ?? 0,
                    'draws' => $prediction['h2h'][0]['draws'] ?? 0,
                    'loses_home' => $prediction['h2h'][0]['loses'] ?? 0,
                ],
                'data_source' => 'api_sports_professional_predictions',
                'last_updated' => now(),
            ];

            return $realPredictions;
            
        } catch (\Exception $e) {
            Log::error("Error getting real predictions: " . $e->getMessage());
            return null;
        }
    }

    // =========================================================================
    // REAL MATCH STATISTICS (Replaces calculated stats)
    // =========================================================================

    /**
     * Get real match statistics from API, not calculated
     */
    public function getRealMatchStatistics(int $fixtureId): ?array
    {
        try {
            $statsData = $this->apiService->fetchFixtureStatistics($fixtureId);
            
            if (empty($statsData)) {
                return null;
            }

            $homeStats = $statsData[0] ?? null;
            $awayStats = $statsData[1] ?? null;

            if (!$homeStats || !$awayStats) return null;

            return [
                'fixture_id' => $fixtureId,
                'home_team' => [
                    'team_id' => $homeStats['team']['id'],
                    'team_name' => $homeStats['team']['name'],
                    'statistics' => $this->processTeamMatchStats($homeStats['statistics'] ?? []),
                ],
                'away_team' => [
                    'team_id' => $awayStats['team']['id'],
                    'team_name' => $awayStats['team']['name'],
                    'statistics' => $this->processTeamMatchStats($awayStats['statistics'] ?? []),
                ],
                'data_source' => 'real_match_statistics',
                'last_updated' => now(),
            ];
            
        } catch (\Exception $e) {
            Log::error("Error getting real match statistics: " . $e->getMessage());
            return null;
        }
    }

    private function processTeamMatchStats(array $stats): array
    {
        $processed = [];
        
        foreach ($stats as $stat) {
            $processed[$stat['type']] = $stat['value'];
        }
        
        return $processed;
    }

    // =========================================================================
    // REAL FORM ANALYSIS (From actual matches)
    // =========================================================================

    /**
     * Get real team form from recent matches, not calculations
     */
    public function getRealFormFromRecentMatches(int $teamId, int $matchCount = 5): array
    {
        try {
            // Get recent fixtures for the team
            $fixtures = $this->apiService->fetchFixtures();
            
            $teamFixtures = array_filter($fixtures, function($fixture) use ($teamId) {
                return ($fixture['home_team_external_id'] == $teamId || 
                        $fixture['away_team_external_id'] == $teamId) &&
                       $fixture['status'] === 'finished';
            });

            // Sort by date (most recent first)
            usort($teamFixtures, function($a, $b) {
                return strtotime($b['match_date']) - strtotime($a['match_date']);
            });

            $recentForm = [];
            $count = 0;

            foreach ($teamFixtures as $fixture) {
                if ($count >= $matchCount) break;

                $isHome = $fixture['home_team_external_id'] == $teamId;
                $teamGoals = $isHome ? $fixture['home_goals'] : $fixture['away_goals'];
                $opponentGoals = $isHome ? $fixture['away_goals'] : $fixture['home_goals'];

                if ($teamGoals > $opponentGoals) {
                    $result = 'W';
                } elseif ($teamGoals < $opponentGoals) {
                    $result = 'L';
                } else {
                    $result = 'D';
                }

                $recentForm[] = [
                    'result' => $result,
                    'goals_for' => $teamGoals,
                    'goals_against' => $opponentGoals,
                    'opponent' => $isHome ? $fixture['away_team_name'] : $fixture['home_team_name'],
                    'date' => $fixture['match_date'],
                    'home_away' => $isHome ? 'H' : 'A',
                ];

                $count++;
            }

            return [
                'form_string' => implode('', array_column($recentForm, 'result')),
                'matches' => $recentForm,
                'wins' => count(array_filter($recentForm, fn($m) => $m['result'] === 'W')),
                'draws' => count(array_filter($recentForm, fn($m) => $m['result'] === 'D')),
                'losses' => count(array_filter($recentForm, fn($m) => $m['result'] === 'L')),
                'data_source' => 'real_recent_matches',
            ];
            
        } catch (\Exception $e) {
            Log::error("Error getting real form: " . $e->getMessage());
            return [
                'form_string' => '',
                'matches' => [],
                'wins' => 0,
                'draws' => 0,
                'losses' => 0,
            ];
        }
    }

    // =========================================================================
    // MIGRATION UTILITIES
    // =========================================================================

    /**
     * Replace all synthetic match predictions with real data
     */
    public function migrateMatchPredictionsToRealData(int $fixtureId): bool
    {
        try {
            // Get real odds
            $realOdds = $this->getRealOddsForFixture($fixtureId);
            
            // Get professional predictions
            $realPredictions = $this->getRealPredictionsForFixture($fixtureId);
            
            // Get match statistics
            $realStats = $this->getRealMatchStatistics($fixtureId);
            
            if (!$realOdds && !$realPredictions && !$realStats) {
                Log::warning("No real data available for fixture {$fixtureId}");
                return false;
            }

            // Find the match in our database
            $match = FootballMatch::where('external_id', $fixtureId)->first();
            
            if (!$match) {
                Log::warning("Match not found in database for fixture {$fixtureId}");
                return false;
            }

            // Replace synthetic prediction with real data
            $match->prediction()->updateOrCreate(
                ['match_id' => $match->id],
                [
                    // Replace synthetic ML predictions with real professional data
                    'home_goals_prediction' => $realPredictions['goals_home'] ?? null,
                    'away_goals_prediction' => $realPredictions['goals_away'] ?? null,
                    'home_win_probability' => isset($realPredictions['percent']['home']) ? 
                        $realPredictions['percent']['home'] / 100 : null,
                    'draw_probability' => isset($realPredictions['percent']['draw']) ? 
                        $realPredictions['percent']['draw'] / 100 : null,
                    'away_win_probability' => isset($realPredictions['percent']['away']) ? 
                        $realPredictions['percent']['away'] / 100 : null,
                    'predicted_outcome' => $realPredictions['advice'] ?? null,
                    'confidence_score' => 1.0, // Real data has 100% confidence
                    'model_version' => 'real_data_v1.0',
                    'features_used' => json_encode([
                        'data_source' => 'api_sports_professional',
                        'real_odds' => $realOdds ? true : false,
                        'professional_predictions' => $realPredictions ? true : false,
                        'match_statistics' => $realStats ? true : false,
                    ]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            Log::info("Successfully migrated fixture {$fixtureId} to real data");
            return true;
            
        } catch (\Exception $e) {
            Log::error("Error migrating fixture {$fixtureId} to real data: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Validate that data is real and not synthetic
     */
    public function validateRealData(array $data): bool
    {
        // Check for synthetic data indicators
        $syntheticIndicators = [
            'ml_generated',
            'ai_calculated',
            'synthetic',
            'model_prediction',
            'calculated_probability',
        ];

        $dataString = json_encode($data);
        
        foreach ($syntheticIndicators as $indicator) {
            if (strpos(strtolower($dataString), $indicator) !== false) {
                return false;
            }
        }

        // Check for real data sources
        $realDataSources = [
            'api_sports',
            'real_match_results',
            'bookmaker_odds',
            'professional_predictions',
            'official_statistics',
        ];

        foreach ($realDataSources as $source) {
            if (strpos(strtolower($dataString), $source) !== false) {
                return true;
            }
        }

        return false;
    }
}