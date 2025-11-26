<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * Real Odds Comparison Service
 * 
 * Gets real betting odds from multiple bookmakers for comparison
 */
class RealOddsComparisonService
{
    private const ODDS_API_BASE_URL = 'https://api.the-odds-api.com/v4';
    
    /**
     * Get real odds from multiple bookmakers
     */
    public function getRealOddsForMatch(string $matchKey, string $sport = 'soccer_epl'): array
    {
        $apiKey = env('ODDS_API_KEY'); // The-Odds-API.com
        
        if (!$apiKey) {
            Log::warning('ODDS_API_KEY not configured');
            return $this->getFallbackOdds();
        }

        $cacheKey = "real_odds_{$sport}_{$matchKey}";
        
        return Cache::remember($cacheKey, now()->addMinutes(15), function () use ($apiKey, $sport, $matchKey) {
            try {
                $response = Http::withHeaders([
                    'Accept' => 'application/json'
                ])->get(self::ODDS_API_BASE_URL . "/sports/{$sport}/odds", [
                    'apiKey' => $apiKey,
                    'regions' => 'eu,us,uk', // European, US, UK bookmakers
                    'markets' => 'h2h,totals,btts', // Match winner, Over/Under, Both teams score
                    'oddsFormat' => 'decimal',
                    'dateFormat' => 'iso',
                ]);

                if ($response->successful()) {
                    $data = $response->json();
                    return $this->processRealOddsData($data, $matchKey);
                }

                Log::error('Odds API request failed', [
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);

                return $this->getFallbackOdds();

            } catch (\Exception $e) {
                Log::error('Real odds fetch error: ' . $e->getMessage());
                return $this->getFallbackOdds();
            }
        });
    }

    /**
     * Process real odds data from API
     */
    private function processRealOddsData(array $data, string $matchKey): array
    {
        $processedOdds = [
            'match_key' => $matchKey,
            'data_source' => 'real_bookmakers',
            'last_updated' => now()->toISOString(),
            'bookmakers' => [],
            'best_odds' => [
                'home_win' => ['odds' => 0, 'bookmaker' => null],
                'draw' => ['odds' => 0, 'bookmaker' => null],
                'away_win' => ['odds' => 0, 'bookmaker' => null],
                'over_2_5' => ['odds' => 0, 'bookmaker' => null],
                'under_2_5' => ['odds' => 0, 'bookmaker' => null],
                'both_teams_score_yes' => ['odds' => 0, 'bookmaker' => null],
                'both_teams_score_no' => ['odds' => 0, 'bookmaker' => null],
            ],
            'average_odds' => [],
            'market_margin' => [],
        ];

        foreach ($data as $match) {
            if (!isset($match['bookmakers']) || empty($match['bookmakers'])) {
                continue;
            }

            foreach ($match['bookmakers'] as $bookmaker) {
                $bookmakerName = $bookmaker['title'];
                $bookmakerOdds = [
                    'name' => $bookmakerName,
                    'last_update' => $bookmaker['last_update'],
                    'markets' => []
                ];

                foreach ($bookmaker['markets'] as $market) {
                    $marketType = $market['key'];
                    
                    switch ($marketType) {
                        case 'h2h': // Match winner
                            foreach ($market['outcomes'] as $outcome) {
                                $key = $this->mapOutcomeToKey($outcome['name']);
                                $odds = $outcome['price'];
                                
                                $bookmakerOdds['markets'][$key] = $odds;
                                
                                // Track best odds
                                if ($odds > $processedOdds['best_odds'][$key]['odds']) {
                                    $processedOdds['best_odds'][$key] = [
                                        'odds' => $odds,
                                        'bookmaker' => $bookmakerName
                                    ];
                                }
                            }
                            break;
                            
                        case 'totals': // Over/Under
                            foreach ($market['outcomes'] as $outcome) {
                                if (strpos($outcome['name'], '2.5') !== false) {
                                    $key = strpos($outcome['name'], 'Over') !== false ? 'over_2_5' : 'under_2_5';
                                    $odds = $outcome['price'];
                                    
                                    $bookmakerOdds['markets'][$key] = $odds;
                                    
                                    if ($odds > $processedOdds['best_odds'][$key]['odds']) {
                                        $processedOdds['best_odds'][$key] = [
                                            'odds' => $odds,
                                            'bookmaker' => $bookmakerName
                                        ];
                                    }
                                }
                            }
                            break;
                            
                        case 'btts': // Both teams to score
                            foreach ($market['outcomes'] as $outcome) {
                                $key = $outcome['name'] === 'Yes' ? 'both_teams_score_yes' : 'both_teams_score_no';
                                $odds = $outcome['price'];
                                
                                $bookmakerOdds['markets'][$key] = $odds;
                                
                                if ($odds > $processedOdds['best_odds'][$key]['odds']) {
                                    $processedOdds['best_odds'][$key] = [
                                        'odds' => $odds,
                                        'bookmaker' => $bookmakerName
                                    ];
                                }
                            }
                            break;
                    }
                }

                $processedOdds['bookmakers'][] = $bookmakerOdds;
            }
        }

        // Calculate average odds and market margins
        $processedOdds['average_odds'] = $this->calculateAverageOdds($processedOdds['bookmakers']);
        $processedOdds['market_margin'] = $this->calculateMarketMargins($processedOdds['average_odds']);
        $processedOdds['value_opportunities'] = $this->identifyValueBets($processedOdds);

        return $processedOdds;
    }

    /**
     * Map outcome names to standardized keys
     */
    private function mapOutcomeToKey(string $outcomeName): string
    {
        $outcomeMap = [
            'Draw' => 'draw',
            'Tie' => 'draw',
        ];

        return $outcomeMap[$outcomeName] ?? strtolower(str_replace(' ', '_', $outcomeName));
    }

    /**
     * Calculate average odds across bookmakers
     */
    private function calculateAverageOdds(array $bookmakers): array
    {
        if (empty($bookmakers)) {
            return [];
        }

        $markets = ['home_win', 'draw', 'away_win', 'over_2_5', 'under_2_5', 'both_teams_score_yes', 'both_teams_score_no'];
        $averages = [];

        foreach ($markets as $market) {
            $oddsValues = [];
            
            foreach ($bookmakers as $bookmaker) {
                if (isset($bookmaker['markets'][$market]) && $bookmaker['markets'][$market] > 0) {
                    $oddsValues[] = $bookmaker['markets'][$market];
                }
            }

            if (!empty($oddsValues)) {
                $averages[$market] = round(array_sum($oddsValues) / count($oddsValues), 2);
            }
        }

        return $averages;
    }

    /**
     * Calculate market margins (bookmaker advantage)
     */
    private function calculateMarketMargins(array $averageOdds): array
    {
        $margins = [];

        // Match winner margin
        if (isset($averageOdds['home_win'], $averageOdds['draw'], $averageOdds['away_win'])) {
            $impliedProb = (1/$averageOdds['home_win']) + (1/$averageOdds['draw']) + (1/$averageOdds['away_win']);
            $margins['match_winner'] = round(($impliedProb - 1) * 100, 2); // Percentage
        }

        // Over/Under margin
        if (isset($averageOdds['over_2_5'], $averageOdds['under_2_5'])) {
            $impliedProb = (1/$averageOdds['over_2_5']) + (1/$averageOdds['under_2_5']);
            $margins['over_under'] = round(($impliedProb - 1) * 100, 2);
        }

        // Both teams score margin
        if (isset($averageOdds['both_teams_score_yes'], $averageOdds['both_teams_score_no'])) {
            $impliedProb = (1/$averageOdds['both_teams_score_yes']) + (1/$averageOdds['both_teams_score_no']);
            $margins['both_teams_score'] = round(($impliedProb - 1) * 100, 2);
        }

        return $margins;
    }

    /**
     * Identify value betting opportunities
     */
    private function identifyValueBets(array $oddsData): array
    {
        $valueBets = [];
        
        // Compare best odds with average odds to find value
        foreach ($oddsData['best_odds'] as $market => $bestOdd) {
            if ($bestOdd['odds'] > 0 && isset($oddsData['average_odds'][$market])) {
                $valuePercentage = (($bestOdd['odds'] / $oddsData['average_odds'][$market]) - 1) * 100;
                
                if ($valuePercentage > 3.0) { // 3%+ value threshold
                    $valueBets[] = [
                        'market' => $market,
                        'best_odds' => $bestOdd['odds'],
                        'bookmaker' => $bestOdd['bookmaker'],
                        'average_odds' => $oddsData['average_odds'][$market],
                        'value_percentage' => round($valuePercentage, 2),
                        'confidence' => $valuePercentage > 7 ? 'high' : 'medium'
                    ];
                }
            }
        }

        return $valueBets;
    }

    /**
     * Get fallback odds when API is unavailable
     */
    private function getFallbackOdds(): array
    {
        return [
            'match_key' => 'unknown',
            'data_source' => 'fallback_estimated',
            'error' => 'Real odds API unavailable',
            'bookmakers' => [],
            'best_odds' => [],
            'average_odds' => [],
            'market_margin' => [],
            'value_opportunities' => [],
        ];
    }

    /**
     * Get supported sports and leagues
     */
    public function getSupportedLeagues(): array
    {
        return [
            'soccer_epl' => 'English Premier League',
            'soccer_spain_la_liga' => 'Spanish La Liga',
            'soccer_germany_bundesliga' => 'German Bundesliga',
            'soccer_italy_serie_a' => 'Italian Serie A',
            'soccer_france_ligue_one' => 'French Ligue 1',
            'soccer_uefa_champs_league' => 'UEFA Champions League',
        ];
    }

    /**
     * Get API usage status
     */
    public function getApiStatus(): array
    {
        return [
            'odds_api' => [
                'available' => !empty(env('ODDS_API_KEY')),
                'quota' => '500 requests/month (free tier)',
                'cost_per_request' => '$0.002 (paid tiers)',
                'update_frequency' => 'Every 5-10 minutes',
                'supported_bookmakers' => 40,
            ],
            'recommended_upgrade' => [
                'pro_tier' => '$29/month - 10,000 requests',
                'premium_tier' => '$99/month - 100,000 requests',
            ]
        ];
    }
}