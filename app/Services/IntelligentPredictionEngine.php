<?php

namespace App\Services;

use App\Services\MultiSourceStatsService;
use App\Services\RealOddsComparisonService;
use App\Models\FootballMatch;
use App\Models\MatchPrediction;
use Illuminate\Support\Facades\Log;

/**
 * Intelligent Prediction Engine
 * 
 * Advanced algorithms using multi-source real data for superior predictions
 */
class IntelligentPredictionEngine
{
    private MultiSourceStatsService $multiSourceStats;
    private RealOddsComparisonService $oddsComparison;

    public function __construct(
        MultiSourceStatsService $multiSourceStats,
        RealOddsComparisonService $oddsComparison
    ) {
        $this->multiSourceStats = $multiSourceStats;
        $this->oddsComparison = $oddsComparison;
    }

    /**
     * Generate intelligent prediction using advanced algorithms
     */
    public function generateIntelligentPrediction(FootballMatch $match): ?array
    {
        Log::info("Generating intelligent prediction for: {$match->homeTeam->name} vs {$match->awayTeam->name}");

        try {
            // Step 1: Gather comprehensive data
            $homeStats = $this->multiSourceStats->getComprehensiveTeamStats(
                (int) $match->homeTeam->external_id
            );
            
            $awayStats = $this->multiSourceStats->getComprehensiveTeamStats(
                (int) $match->awayTeam->external_id
            );

            // Step 2: Advanced form analysis
            $formAnalysis = $this->analyzeRecentForm($homeStats, $awayStats);

            // Step 3: Intelligent goal prediction
            $goalPredictions = $this->predictGoalsIntelligently($homeStats, $awayStats, $formAnalysis);

            // Step 4: Dynamic probability calculation
            $probabilities = $this->calculateDynamicProbabilities($goalPredictions, $formAnalysis);

            // Step 5: Market value analysis (if odds available)
            $marketAnalysis = $this->analyzeMarketValue($match, $probabilities);

            // Step 6: Confidence scoring
            $confidence = $this->calculateIntelligentConfidence($homeStats, $awayStats, $marketAnalysis);

            return [
                'home_goals_prediction' => $goalPredictions['home_goals'],
                'away_goals_prediction' => $goalPredictions['away_goals'],
                'home_win_probability' => $probabilities['home_win'],
                'draw_probability' => $probabilities['draw'],
                'away_win_probability' => $probabilities['away_win'],
                'both_teams_score_probability' => $probabilities['both_teams_score'],
                'over_2_5_probability' => $probabilities['over_2_5'],
                'under_2_5_probability' => $probabilities['under_2_5'],
                'predicted_outcome' => $probabilities['predicted_outcome'],
                'confidence_score' => $confidence['overall_confidence'],
                'model_version' => 'intelligent_engine_v1.0',
                'features_used' => json_encode([
                    'algorithm' => 'intelligent_multi_source',
                    'data_sources' => $homeStats['sources_used'] ?? ['api_sports'],
                    'form_analysis' => $formAnalysis['method'],
                    'confidence_factors' => $confidence['factors'],
                    'market_analysis' => $marketAnalysis['available'],
                    'enhancement_level' => 'advanced',
                    'created_at' => now()->toISOString()
                ]),
                'analysis_details' => [
                    'form_analysis' => $formAnalysis,
                    'market_analysis' => $marketAnalysis,
                    'confidence_breakdown' => $confidence,
                ]
            ];

        } catch (\Exception $e) {
            Log::error("Intelligent prediction failed: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Advanced form analysis with trend detection
     */
    private function analyzeRecentForm(array $homeStats, array $awayStats): array
    {
        $analysis = [
            'method' => 'weighted_trend_analysis',
            'home_form_score' => 0,
            'away_form_score' => 0,
            'form_advantage' => 'neutral',
            'trend_direction' => ['home' => 'stable', 'away' => 'stable']
        ];

        // Extract recent form from multiple sources
        $homeForm = $homeStats['combined_stats']['form'] ?? null;
        $awayForm = $awayStats['combined_stats']['form'] ?? null;

        if ($homeForm && is_string($homeForm)) {
            $analysis['home_form_score'] = $this->calculateFormScore($homeForm);
            $analysis['trend_direction']['home'] = $this->detectTrend($homeForm);
        }

        if ($awayForm && is_string($awayForm)) {
            $analysis['away_form_score'] = $this->calculateFormScore($awayForm);
            $analysis['trend_direction']['away'] = $this->detectTrend($awayForm);
        }

        // Determine form advantage
        $formDiff = $analysis['home_form_score'] - $analysis['away_form_score'];
        if ($formDiff > 0.3) {
            $analysis['form_advantage'] = 'home';
        } elseif ($formDiff < -0.3) {
            $analysis['form_advantage'] = 'away';
        }

        return $analysis;
    }

    /**
     * Calculate form score from recent results
     */
    private function calculateFormScore(string $form): float
    {
        $results = str_split(strtoupper($form));
        $score = 0;
        $weight = 1.0;

        // Weight recent games more heavily
        foreach (array_reverse($results) as $result) {
            switch ($result) {
                case 'W':
                    $score += 3 * $weight;
                    break;
                case 'D':
                    $score += 1 * $weight;
                    break;
                case 'L':
                    $score += 0 * $weight;
                    break;
            }
            $weight *= 0.85; // Decay weight for older results
        }

        return $score / 15; // Normalize to 0-1 scale
    }

    /**
     * Detect trend in recent form
     */
    private function detectTrend(string $form): string
    {
        $results = str_split(strtoupper($form));
        if (count($results) < 3) return 'stable';

        $recent = array_slice($results, -3); // Last 3 games
        $older = array_slice($results, 0, -3); // Previous games

        $recentScore = $this->calculateFormScore(implode('', $recent));
        $olderScore = count($older) > 0 ? $this->calculateFormScore(implode('', $older)) : $recentScore;

        if ($recentScore > $olderScore + 0.15) {
            return 'improving';
        } elseif ($recentScore < $olderScore - 0.15) {
            return 'declining';
        }

        return 'stable';
    }

    /**
     * Intelligent goal prediction using multiple factors
     */
    private function predictGoalsIntelligently(array $homeStats, array $awayStats, array $formAnalysis): array
    {
        // Base predictions from statistics
        $homeBaseGoals = $homeStats['combined_stats']['goals_for']['average'] ?? 1.4;
        $awayBaseGoals = $awayStats['combined_stats']['goals_for']['average'] ?? 1.4;

        // Apply form adjustments
        $homeFormAdjustment = ($formAnalysis['home_form_score'] - 0.5) * 0.4; // Max ±0.2 goals
        $awayFormAdjustment = ($formAnalysis['away_form_score'] - 0.5) * 0.4;

        // Apply trend adjustments
        $homeTrendAdjustment = $this->getTrendAdjustment($formAnalysis['trend_direction']['home']);
        $awayTrendAdjustment = $this->getTrendAdjustment($formAnalysis['trend_direction']['away']);

        // Home advantage (dynamic based on team quality)
        $homeAdvantage = $this->calculateDynamicHomeAdvantage($homeStats, $awayStats);

        // Calculate final predictions
        $homeGoals = max(0.1, min(5.0, 
            $homeBaseGoals + $homeFormAdjustment + $homeTrendAdjustment + $homeAdvantage
        ));

        $awayGoals = max(0.1, min(5.0, 
            $awayBaseGoals + $awayFormAdjustment + $awayTrendAdjustment - ($homeAdvantage * 0.5)
        ));

        return [
            'home_goals' => round($homeGoals, 2),
            'away_goals' => round($awayGoals, 2),
            'total_goals' => round($homeGoals + $awayGoals, 2),
            'adjustments' => [
                'home_form' => $homeFormAdjustment,
                'away_form' => $awayFormAdjustment,
                'home_trend' => $homeTrendAdjustment,
                'away_trend' => $awayTrendAdjustment,
                'home_advantage' => $homeAdvantage,
            ]
        ];
    }

    /**
     * Get trend adjustment value
     */
    private function getTrendAdjustment(string $trend): float
    {
        return match ($trend) {
            'improving' => 0.15,
            'declining' => -0.15,
            default => 0.0,
        };
    }

    /**
     * Calculate dynamic home advantage based on team quality
     */
    private function calculateDynamicHomeAdvantage(array $homeStats, array $awayStats): float
    {
        $homeQuality = $homeStats['combined_stats']['matches']['wins'] ?? 0;
        $awayQuality = $awayStats['combined_stats']['matches']['wins'] ?? 0;
        
        $baseAdvantage = 0.25; // Standard home advantage
        
        // Reduce advantage if away team is significantly stronger
        $qualityDiff = $awayQuality - $homeQuality;
        $qualityAdjustment = max(-0.15, min(0.10, $qualityDiff * 0.02));
        
        return $baseAdvantage + $qualityAdjustment;
    }

    /**
     * Calculate dynamic probabilities using advanced methods
     */
    private function calculateDynamicProbabilities(array $goalPredictions, array $formAnalysis): array
    {
        $homeGoals = $goalPredictions['home_goals'];
        $awayGoals = $goalPredictions['away_goals'];

        // Poisson-based probability calculation (more accurate)
        $homeWinProb = $this->poissonMatchOutcome($homeGoals, $awayGoals, 'home');
        $drawProb = $this->poissonMatchOutcome($homeGoals, $awayGoals, 'draw');
        $awayWinProb = $this->poissonMatchOutcome($homeGoals, $awayGoals, 'away');

        // Normalize probabilities
        $total = $homeWinProb + $drawProb + $awayWinProb;
        $homeWinProb /= $total;
        $drawProb /= $total;
        $awayWinProb /= $total;

        // Both teams score probability
        $bothTeamsScoreProb = (1 - exp(-$homeGoals * 0.8)) * (1 - exp(-$awayGoals * 0.8));

        // Over/Under 2.5 goals
        $totalGoals = $homeGoals + $awayGoals;
        $over25Prob = $this->poissonOverUnder($totalGoals, 2.5, 'over');

        // Determine predicted outcome
        $outcomes = [
            'home_win' => $homeWinProb,
            'draw' => $drawProb,
            'away_win' => $awayWinProb
        ];
        $predictedOutcome = array_keys($outcomes, max($outcomes))[0];

        return [
            'home_win' => round($homeWinProb, 4),
            'draw' => round($drawProb, 4),
            'away_win' => round($awayWinProb, 4),
            'both_teams_score' => round($bothTeamsScoreProb, 4),
            'over_2_5' => round($over25Prob, 4),
            'under_2_5' => round(1 - $over25Prob, 4),
            'predicted_outcome' => $predictedOutcome,
        ];
    }

    /**
     * Poisson probability for match outcomes
     */
    private function poissonMatchOutcome(float $homeGoals, float $awayGoals, string $outcome): float
    {
        $prob = 0;
        
        for ($h = 0; $h <= 6; $h++) {
            for ($a = 0; $a <= 6; $a++) {
                $homeProb = (pow($homeGoals, $h) * exp(-$homeGoals)) / $this->factorial($h);
                $awayProb = (pow($awayGoals, $a) * exp(-$awayGoals)) / $this->factorial($a);
                $matchProb = $homeProb * $awayProb;

                if (($outcome === 'home' && $h > $a) ||
                    ($outcome === 'away' && $a > $h) ||
                    ($outcome === 'draw' && $h === $a)) {
                    $prob += $matchProb;
                }
            }
        }

        return $prob;
    }

    /**
     * Poisson probability for over/under goals
     */
    private function poissonOverUnder(float $expectedGoals, float $line, string $type): float
    {
        $prob = 0;
        $maxGoals = 8;

        for ($goals = 0; $goals <= $maxGoals; $goals++) {
            $goalProb = (pow($expectedGoals, $goals) * exp(-$expectedGoals)) / $this->factorial($goals);
            
            if (($type === 'over' && $goals > $line) || ($type === 'under' && $goals < $line)) {
                $prob += $goalProb;
            }
        }

        return $prob;
    }

    /**
     * Factorial function for Poisson calculations
     */
    private function factorial(int $n): int
    {
        return $n <= 1 ? 1 : $n * $this->factorial($n - 1);
    }

    /**
     * Analyze market value using real odds
     */
    private function analyzeMarketValue(FootballMatch $match, array $probabilities): array
    {
        $analysis = ['available' => false, 'value_opportunities' => []];

        try {
            $marketKey = $match->external_id ?? 'unknown';
            $realOdds = $this->oddsComparison->getRealOddsForMatch($marketKey);
            
            if ($realOdds['data_source'] !== 'fallback_estimated') {
                $analysis['available'] = true;
                $analysis['bookmaker_count'] = count($realOdds['bookmakers']);
                $analysis['value_opportunities'] = $realOdds['value_opportunities'];
                
                // Compare our probabilities with market probabilities
                if (!empty($realOdds['average_odds'])) {
                    $analysis['market_comparison'] = $this->compareWithMarket(
                        $probabilities, 
                        $realOdds['average_odds']
                    );
                }
            }
        } catch (\Exception $e) {
            Log::warning("Market analysis failed: " . $e->getMessage());
        }

        return $analysis;
    }

    /**
     * Compare our probabilities with market probabilities
     */
    private function compareWithMarket(array $ourProbs, array $marketOdds): array
    {
        $comparison = [];
        
        $mappings = [
            'home_win' => 'home_win',
            'draw' => 'draw', 
            'away_win' => 'away_win'
        ];

        foreach ($mappings as $ourKey => $marketKey) {
            if (isset($marketOdds[$marketKey]) && $marketOdds[$marketKey] > 0) {
                $marketProb = 1 / $marketOdds[$marketKey];
                $ourProb = $ourProbs[$ourKey];
                $difference = ($ourProb - $marketProb) * 100;
                
                $comparison[$ourKey] = [
                    'our_probability' => round($ourProb, 3),
                    'market_probability' => round($marketProb, 3),
                    'difference_percent' => round($difference, 2),
                    'our_confidence' => abs($difference) > 5 ? 'high' : 'medium'
                ];
            }
        }

        return $comparison;
    }

    /**
     * Calculate intelligent confidence score
     */
    private function calculateIntelligentConfidence(array $homeStats, array $awayStats, array $marketAnalysis): array
    {
        $confidence = 0.5; // Base confidence
        $factors = [];

        // Data quality factor
        $homeQuality = $homeStats['combined_stats']['data_quality'] ?? 'medium';
        $awayQuality = $awayStats['combined_stats']['data_quality'] ?? 'medium';
        
        if ($homeQuality === 'very_high' && $awayQuality === 'very_high') {
            $confidence += 0.25;
            $factors[] = 'multi_source_data_boost';
        } elseif ($homeQuality === 'high' && $awayQuality === 'high') {
            $confidence += 0.15;
            $factors[] = 'high_quality_data';
        }

        // Market validation factor
        if ($marketAnalysis['available'] && !empty($marketAnalysis['market_comparison'])) {
            $avgConfidence = 0;
            foreach ($marketAnalysis['market_comparison'] as $comp) {
                if ($comp['our_confidence'] === 'high') {
                    $avgConfidence += 0.1;
                }
            }
            $confidence += $avgConfidence;
            $factors[] = 'market_validation';
        }

        // Statistical significance
        $homeMatches = $homeStats['combined_stats']['matches']['played'] ?? 0;
        $awayMatches = $awayStats['combined_stats']['matches']['played'] ?? 0;
        
        if ($homeMatches >= 10 && $awayMatches >= 10) {
            $confidence += 0.1;
            $factors[] = 'sufficient_sample_size';
        }

        return [
            'overall_confidence' => round(min(0.95, $confidence), 4),
            'factors' => $factors,
            'data_quality' => [$homeQuality, $awayQuality],
            'market_validated' => $marketAnalysis['available']
        ];
    }
}