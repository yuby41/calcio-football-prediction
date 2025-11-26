<?php

namespace App\Services;

use App\Models\FootballMatch;
use App\Models\MatchPrediction;
use App\Models\BudgetConfiguration;
use App\Services\IntelligentPredictionEngine;
use App\Services\RealOddsComparisonService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

/**
 * Intelligent Alert System
 * 
 * Detects value betting opportunities and sends smart alerts
 */
class IntelligentAlertSystem
{
    private IntelligentPredictionEngine $predictionEngine;
    private RealOddsComparisonService $oddsService;

    public function __construct(
        IntelligentPredictionEngine $predictionEngine,
        RealOddsComparisonService $oddsService
    ) {
        $this->predictionEngine = $predictionEngine;
        $this->oddsService = $oddsService;
    }

    /**
     * Scan for value betting opportunities and send alerts
     */
    public function scanForValueOpportunities(array $options = []): array
    {
        $options = array_merge([
            'min_value_percentage' => 5.0,        // Minimum 5% value
            'min_confidence' => 0.7,              // Minimum 70% confidence
            'max_alerts_per_hour' => 10,          // Rate limiting
            'leagues' => ['PL', 'PD', 'BL1', 'SA', 'FL1'], // Major leagues only
        ], $options);

        Log::info('Starting value betting scan', $options);

        $alerts = [];
        $opportunities = $this->detectValueOpportunities($options);
        
        foreach ($opportunities as $opportunity) {
            $alert = $this->createIntelligentAlert($opportunity);
            if ($alert && $this->shouldSendAlert($alert, $options)) {
                $alerts[] = $alert;
                $this->sendAlert($alert);
            }
        }

        Log::info("Value scan completed", [
            'opportunities_found' => count($opportunities),
            'alerts_created' => count($alerts),
        ]);

        return [
            'scan_time' => now()->toISOString(),
            'opportunities_scanned' => count($opportunities),
            'alerts_sent' => count($alerts),
            'alerts' => $alerts,
            'scan_criteria' => $options,
        ];
    }

    /**
     * Detect value betting opportunities
     */
    private function detectValueOpportunities(array $options): array
    {
        $opportunities = [];

        // Get upcoming matches with intelligent predictions
        $matches = FootballMatch::whereHas('prediction', function($query) {
            $query->where('model_version', 'like', '%intelligent%')
                  ->where('confidence_score', '>=', 0.7);
        })
        ->with(['homeTeam', 'awayTeam', 'prediction'])
        ->where('match_date', '>', now())
        ->where('match_date', '<=', now()->addDays(3))
        ->get();

        foreach ($matches as $match) {
            try {
                $opportunity = $this->analyzeMatchForValue($match, $options);
                if ($opportunity) {
                    $opportunities[] = $opportunity;
                }
            } catch (\Exception $e) {
                Log::warning("Error analyzing match {$match->id}: " . $e->getMessage());
            }
        }

        // Sort by value percentage (best opportunities first)
        usort($opportunities, function($a, $b) {
            return $b['max_value_percentage'] <=> $a['max_value_percentage'];
        });

        return array_slice($opportunities, 0, 20); // Top 20 opportunities
    }

    /**
     * Analyze individual match for value betting opportunities
     */
    private function analyzeMatchForValue(FootballMatch $match, array $options): ?array
    {
        $prediction = $match->prediction;
        if (!$prediction) return null;

        // Get real market odds
        $marketKey = $match->external_id ?? 'unknown';
        $realOdds = $this->oddsService->getRealOddsForMatch($marketKey);
        
        if ($realOdds['data_source'] === 'fallback_estimated') {
            return null; // No real odds available
        }

        $valueOpportunities = [];

        // Analyze different betting markets
        $markets = [
            'home_win' => [
                'our_probability' => $prediction->home_win_probability,
                'market_key' => 'home_win'
            ],
            'draw' => [
                'our_probability' => $prediction->draw_probability,
                'market_key' => 'draw'
            ],
            'away_win' => [
                'our_probability' => $prediction->away_win_probability,
                'market_key' => 'away_win'
            ],
            'over_2_5' => [
                'our_probability' => $prediction->over_2_5_probability,
                'market_key' => 'over_2_5'
            ],
            'both_teams_score' => [
                'our_probability' => $prediction->both_teams_score_probability,
                'market_key' => 'both_teams_score_yes'
            ]
        ];

        foreach ($markets as $market => $data) {
            $value = $this->calculateValueBet(
                $data['our_probability'],
                $realOdds['best_odds'][$data['market_key']] ?? null,
                $realOdds['average_odds'][$data['market_key']] ?? null
            );

            if ($value && $value['value_percentage'] >= $options['min_value_percentage']) {
                $valueOpportunities[] = array_merge($value, [
                    'market' => $market,
                    'market_display' => $this->getMarketDisplayName($market),
                ]);
            }
        }

        if (empty($valueOpportunities)) {
            return null;
        }

        // Find best opportunity
        $bestOpportunity = collect($valueOpportunities)->sortByDesc('value_percentage')->first();

        return [
            'match_id' => $match->id,
            'match_name' => "{$match->homeTeam->name} vs {$match->awayTeam->name}",
            'match_date' => $match->match_date,
            'league' => $match->league,
            'prediction_confidence' => $prediction->confidence_score,
            'value_opportunities' => $valueOpportunities,
            'max_value_percentage' => $bestOpportunity['value_percentage'],
            'best_market' => $bestOpportunity['market'],
            'recommended_bet' => $bestOpportunity,
            'bookmakers_available' => count($realOdds['bookmakers']),
            'data_quality' => $this->assessOpportunityQuality($match, $prediction, $realOdds),
        ];
    }

    /**
     * Calculate value bet metrics
     */
    private function calculateValueBet(float $ourProbability, ?array $bestOdds, ?float $averageOdds): ?array
    {
        if (!$bestOdds || !$averageOdds || $bestOdds['odds'] <= 0 || $averageOdds <= 0) {
            return null;
        }

        $impliedProbability = 1 / $bestOdds['odds'];
        $averageImpliedProbability = 1 / $averageOdds;

        // Calculate value percentage
        $valueVsBest = (($ourProbability / $impliedProbability) - 1) * 100;
        $valueVsAverage = (($ourProbability / $averageImpliedProbability) - 1) * 100;

        if ($valueVsBest <= 0) {
            return null; // No value
        }

        // Calculate expected value
        $expectedValue = ($ourProbability * ($bestOdds['odds'] - 1)) - (1 - $ourProbability);

        return [
            'our_probability' => round($ourProbability, 4),
            'best_odds' => $bestOdds['odds'],
            'best_bookmaker' => $bestOdds['bookmaker'],
            'average_odds' => $averageOdds,
            'implied_probability' => round($impliedProbability, 4),
            'value_percentage' => round($valueVsBest, 2),
            'value_vs_average' => round($valueVsAverage, 2),
            'expected_value' => round($expectedValue, 4),
            'kelly_percentage' => $this->calculateKellyStake($ourProbability, $bestOdds['odds']),
        ];
    }

    /**
     * Calculate optimal Kelly Criterion stake
     */
    private function calculateKellyStake(float $probability, float $odds): float
    {
        $q = 1 - $probability;
        $b = $odds - 1;
        
        $kelly = ($probability * $b - $q) / $b;
        
        // Cap at 10% for risk management
        return round(max(0, min(0.1, $kelly)) * 100, 2);
    }

    /**
     * Create intelligent alert with context
     */
    private function createIntelligentAlert(array $opportunity): array
    {
        $recommendedBet = $opportunity['recommended_bet'];
        $match = FootballMatch::find($opportunity['match_id']);
        
        return [
            'id' => uniqid('alert_'),
            'type' => 'value_betting_opportunity',
            'priority' => $this->calculateAlertPriority($opportunity),
            'title' => "🎯 Value Bet: {$opportunity['match_name']}",
            'message' => $this->generateAlertMessage($opportunity),
            'match' => [
                'id' => $opportunity['match_id'],
                'name' => $opportunity['match_name'],
                'date' => $opportunity['match_date'],
                'league' => $opportunity['league'],
                'time_until_match' => now()->diffInHours($opportunity['match_date']),
            ],
            'betting_recommendation' => [
                'market' => $recommendedBet['market_display'],
                'bookmaker' => $recommendedBet['best_bookmaker'],
                'odds' => $recommendedBet['best_odds'],
                'value_percentage' => $recommendedBet['value_percentage'],
                'kelly_stake' => $recommendedBet['kelly_percentage'],
                'expected_value' => $recommendedBet['expected_value'],
            ],
            'confidence_factors' => [
                'prediction_confidence' => $opportunity['prediction_confidence'],
                'data_quality' => $opportunity['data_quality'],
                'bookmakers_count' => $opportunity['bookmakers_available'],
            ],
            'urgency' => $this->calculateUrgency($opportunity),
            'created_at' => now()->toISOString(),
            'expires_at' => Carbon::parse($opportunity['match_date'])->subHours(1)->toISOString(),
        ];
    }

    /**
     * Generate human-readable alert message
     */
    private function generateAlertMessage(array $opportunity): string
    {
        $bet = $opportunity['recommended_bet'];
        $hoursUntil = now()->diffInHours($opportunity['match_date']);
        
        return sprintf(
            "🚨 HIGH VALUE DETECTED!\n\n" .
            "Match: %s\n" .
            "Market: %s\n" .
            "Best Odds: %.2f at %s\n" .
            "Value: +%.1f%%\n" .
            "Kelly Stake: %.1f%%\n" .
            "Confidence: %.0f%%\n\n" .
            "⏰ Match in %d hours\n" .
            "📊 %d bookmakers analyzed",
            $opportunity['match_name'],
            $bet['market_display'],
            $bet['best_odds'],
            $bet['best_bookmaker'],
            $bet['value_percentage'],
            $bet['kelly_percentage'],
            $opportunity['prediction_confidence'] * 100,
            $hoursUntil,
            $opportunity['bookmakers_available']
        );
    }

    /**
     * Calculate alert priority (1-5, 5 = highest)
     */
    private function calculateAlertPriority(array $opportunity): int
    {
        $value = $opportunity['max_value_percentage'];
        $confidence = $opportunity['prediction_confidence'];
        
        // High value + high confidence = high priority
        if ($value > 15 && $confidence > 0.85) return 5;
        if ($value > 10 && $confidence > 0.8) return 4;
        if ($value > 7 && $confidence > 0.75) return 3;
        if ($value > 5 && $confidence > 0.7) return 2;
        
        return 1;
    }

    /**
     * Calculate urgency based on time until match
     */
    private function calculateUrgency(array $opportunity): string
    {
        $hoursUntil = now()->diffInHours($opportunity['match_date']);
        
        if ($hoursUntil <= 2) return 'critical';
        if ($hoursUntil <= 6) return 'high';
        if ($hoursUntil <= 24) return 'medium';
        
        return 'low';
    }

    /**
     * Assess overall opportunity quality
     */
    private function assessOpportunityQuality(FootballMatch $match, MatchPrediction $prediction, array $realOdds): string
    {
        $factors = [];
        
        // Prediction confidence
        if ($prediction->confidence_score > 0.85) $factors[] = 'high_confidence';
        
        // Number of bookmakers
        if (count($realOdds['bookmakers']) > 15) $factors[] = 'wide_market';
        
        // Data sources used
        $features = json_decode($prediction->features_used, true);
        if (isset($features['data_sources']) && count($features['data_sources']) > 1) {
            $factors[] = 'multi_source';
        }
        
        // Recent data
        if ($prediction->updated_at > now()->subHours(6)) {
            $factors[] = 'recent_analysis';
        }
        
        $qualityScore = count($factors);
        
        if ($qualityScore >= 3) return 'excellent';
        if ($qualityScore >= 2) return 'good';
        if ($qualityScore >= 1) return 'fair';
        
        return 'limited';
    }

    /**
     * Determine if alert should be sent
     */
    private function shouldSendAlert(array $alert, array $options): bool
    {
        // Rate limiting check
        $hourlyKey = 'alerts_sent_' . now()->format('Y-m-d-H');
        $sentThisHour = Cache::get($hourlyKey, 0);
        
        if ($sentThisHour >= $options['max_alerts_per_hour']) {
            Log::info("Alert rate limit reached for hour");
            return false;
        }
        
        // Minimum priority check
        if ($alert['priority'] < 2) {
            return false;
        }
        
        // Don't send duplicate alerts for same match
        $duplicateKey = 'alert_sent_match_' . $alert['match']['id'];
        if (Cache::has($duplicateKey)) {
            return false;
        }
        
        return true;
    }

    /**
     * Send alert through configured channels
     */
    private function sendAlert(array $alert): bool
    {
        try {
            // Update rate limiting
            $hourlyKey = 'alerts_sent_' . now()->format('Y-m-d-H');
            $sentThisHour = Cache::get($hourlyKey, 0);
            Cache::put($hourlyKey, $sentThisHour + 1, now()->addHour());
            
            // Prevent duplicates
            $duplicateKey = 'alert_sent_match_' . $alert['match']['id'];
            Cache::put($duplicateKey, true, now()->addHours(12));
            
            // Store alert in database/cache for dashboard
            $this->storeAlert($alert);
            
            // Send through configured channels
            $this->sendToConfiguredChannels($alert);
            
            Log::info("Alert sent successfully", [
                'alert_id' => $alert['id'],
                'match' => $alert['match']['name'],
                'value' => $alert['betting_recommendation']['value_percentage']
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            Log::error("Failed to send alert: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Store alert for dashboard display
     */
    private function storeAlert(array $alert): void
    {
        $alertsKey = 'recent_alerts';
        $recentAlerts = Cache::get($alertsKey, []);
        
        // Add new alert to beginning of list
        array_unshift($recentAlerts, $alert);
        
        // Keep only last 50 alerts
        $recentAlerts = array_slice($recentAlerts, 0, 50);
        
        Cache::put($alertsKey, $recentAlerts, now()->addDays(1));
    }

    /**
     * Send alert to configured channels (email, webhook, etc.)
     */
    private function sendToConfiguredChannels(array $alert): void
    {
        // Email notifications (if configured)
        $emailEnabled = env('ALERTS_EMAIL_ENABLED', false);
        if ($emailEnabled && $alert['priority'] >= 3) {
            $this->sendEmailAlert($alert);
        }
        
        // Webhook notifications (for external integrations)
        $webhookUrl = env('ALERTS_WEBHOOK_URL');
        if ($webhookUrl) {
            $this->sendWebhookAlert($alert, $webhookUrl);
        }
        
        // Log high priority alerts
        if ($alert['priority'] >= 4) {
            Log::notice("HIGH PRIORITY VALUE BET", [
                'match' => $alert['match']['name'],
                'value' => $alert['betting_recommendation']['value_percentage'] . '%',
                'odds' => $alert['betting_recommendation']['odds'],
                'bookmaker' => $alert['betting_recommendation']['bookmaker'],
            ]);
        }
    }

    /**
     * Send email alert
     */
    private function sendEmailAlert(array $alert): void
    {
        $to = env('ALERTS_EMAIL_TO');
        if (!$to) return;
        
        // Implementation would depend on your mail setup
        Log::info("Email alert would be sent to: {$to}", ['alert_id' => $alert['id']]);
    }

    /**
     * Send webhook alert
     */
    private function sendWebhookAlert(array $alert, string $webhookUrl): void
    {
        try {
            $response = \Http::timeout(5)->post($webhookUrl, $alert);
            Log::info("Webhook alert sent", ['status' => $response->status()]);
        } catch (\Exception $e) {
            Log::error("Webhook alert failed: " . $e->getMessage());
        }
    }

    /**
     * Get recent alerts for dashboard
     */
    public function getRecentAlerts(int $limit = 20): array
    {
        $alerts = Cache::get('recent_alerts', []);
        return array_slice($alerts, 0, $limit);
    }

    /**
     * Get market display name
     */
    private function getMarketDisplayName(string $market): string
    {
        return match ($market) {
            'home_win' => 'Victoria Local',
            'draw' => 'Empate',
            'away_win' => 'Victoria Visitante',
            'over_2_5' => 'Más de 2.5 Goles',
            'both_teams_score' => 'Ambos Equipos Anotan',
            default => ucfirst(str_replace('_', ' ', $market)),
        };
    }
}