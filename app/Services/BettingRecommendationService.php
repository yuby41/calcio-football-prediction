<?php

namespace App\Services;

use App\Models\BudgetConfiguration;
use App\Models\FootballMatch;
use App\Models\MatchPrediction;
use Carbon\Carbon;

class BettingRecommendationService
{
    private BettingStrategyService $strategyService;
    private FootballApiOddsService $oddsService;

    public function __construct(
        BettingStrategyService $strategyService,
        FootballApiOddsService $oddsService
    ) {
        $this->strategyService = $strategyService;
        $this->oddsService = $oddsService;
    }

    public function getRecommendationsForBudget(BudgetConfiguration $budget): array
    {
        // Obtener partidos próximos con predicciones
        $upcomingMatches = FootballMatch::with(['homeTeam', 'awayTeam', 'prediction'])
            ->where('status', 'scheduled')
            ->where('match_date', '>=', now())
            ->where('match_date', '<=', now()->addDays(7))
            ->whereHas('prediction')
            ->orderBy('match_date')
            ->get();

        $recommendations = [];

        foreach ($upcomingMatches as $match) {
            if (!$match->prediction) continue;

            $matchRecommendations = $this->analyzeMatch($match, $budget);
            if (!empty($matchRecommendations)) {
                $recommendations[] = [
                    'match' => $match,
                    'recommendations' => $matchRecommendations,
                    'match_analysis' => $this->getMatchAnalysis($match),
                ];
            }
        }

        // Ordenar por mejor oportunidad
        usort($recommendations, function($a, $b) {
            $scoreA = $this->getOpportunityScore($a['recommendations']);
            $scoreB = $this->getOpportunityScore($b['recommendations']);
            return $scoreB <=> $scoreA;
        });

        return $recommendations;
    }

    private function analyzeMatch(FootballMatch $match, BudgetConfiguration $budget): array
    {
        $prediction = $match->prediction;
        $recommendations = [];

        // Obtener odds reales de casas de apuestas
        $realOdds = $this->oddsService->getRealOddsForMatch($match);
        
        // Si no hay odds disponibles, saltar este partido
        if (empty($realOdds)) {
            return [];
        }

        // Analizar cada tipo de apuesta
        $betTypes = [
            'home_win' => [
                'probability' => $prediction->home_win_probability,
                'label' => 'Victoria Local',
                'description' => $match->homeTeam->name . ' gana'
            ],
            'away_win' => [
                'probability' => $prediction->away_win_probability,
                'label' => 'Victoria Visitante',
                'description' => $match->awayTeam->name . ' gana'
            ],
            'draw' => [
                'probability' => $prediction->draw_probability,
                'label' => 'Empate',
                'description' => 'Resultado empate'
            ],
            'both_teams_score' => [
                'probability' => $prediction->both_teams_score_probability,
                'label' => 'Ambos Marcan',
                'description' => 'Los dos equipos marcan gol'
            ],
            'over_2_5' => [
                'probability' => $prediction->over_2_5_probability,
                'label' => 'Más de 2.5 Goles',
                'description' => 'Más de 2.5 goles en total'
            ],
            'under_2_5' => [
                'probability' => $prediction->under_2_5_probability,
                'label' => 'Menos de 2.5 Goles',
                'description' => 'Menos de 2.5 goles en total'
            ],
        ];

        foreach ($betTypes as $betType => $info) {
            if (!$info['probability']) continue;

            // Solo analizar apuestas que tienen odds reales disponibles
            if (!isset($realOdds[$betType])) continue;

            $confidence = $info['probability'] * 100;
            
            // Solo recomendar si supera el mínimo de confianza
            if ($confidence >= $budget->min_confidence) {
                $oddsSource = $realOdds[$betType . '_source'] ?? $realOdds['source'] ?? 'bet365_fallback';
                
                $recommendation = $this->createRecommendationWithRealOdds(
                    $betType,
                    $info,
                    $confidence,
                    $realOdds[$betType],
                    $oddsSource,
                    $match,
                    $budget
                );
                
                if ($recommendation) {
                    $recommendations[] = $recommendation;
                }
            }
        }

        // Ordenar por value betting (probabilidad vs odds)
        usort($recommendations, function($a, $b) {
            $valueA = $a['value_rating'] ?? 0;
            $valueB = $b['value_rating'] ?? 0;
            return $valueB <=> $valueA;
        });

        return $recommendations;
    }

    private function createRecommendationWithRealOdds(
        string $betType,
        array $betInfo,
        float $confidence,
        float $realOdds,
        string $oddsSource,
        FootballMatch $match,
        BudgetConfiguration $budget
    ): ?array {
        // Calcular value betting (si nuestras probabilidades indican valor)
        $impliedProbability = 1 / $realOdds;
        $ourProbability = $betInfo['probability'];
        $valueRating = $ourProbability > $impliedProbability ? 
            (($ourProbability - $impliedProbability) / $impliedProbability) * 100 : 0;

        // Solo recomendar si hay value o confianza alta (relajado para mostrar odds reales)
        if ($valueRating < 2 && $confidence < 65) {
            return null;
        }

        // Calcular cantidad recomendada según estrategia
        $recommendedAmount = $this->strategyService->calculateBetAmount($budget, $match, $betType, $confidence);
        
        // Asegurar que sea número entero >= 2
        $recommendedAmount = max(2, floor($recommendedAmount));
        
        if ($recommendedAmount < 2 || $recommendedAmount > $budget->current_budget) {
            return null;
        }

        // Calcular potential profit con odds reales
        $potentialProfit = ($recommendedAmount * $realOdds) - $recommendedAmount;

        // Determinar nivel de recomendación basado en value y confianza
        $recommendationLevel = $this->getRecommendationLevelWithValue($confidence, $valueRating, $budget);

        // Verificar si es compatible con la estrategia
        $strategyFit = $this->analyzeStrategyFit($betType, $confidence, $budget);

        return [
            'bet_type' => $betType,
            'label' => $betInfo['label'],
            'description' => $betInfo['description'],
            'confidence' => round($confidence, 1),
            'estimated_odds' => $realOdds, // Odds reales de bet365/casas de apuestas
            'recommended_amount' => (int)$recommendedAmount, // Entero
            'potential_profit' => round($potentialProfit, 2),
            'recommendation_level' => $recommendationLevel,
            'strategy_fit' => $strategyFit,
            'risk_level' => $this->getRiskLevel($confidence, $recommendedAmount, $budget),
            'rationale' => $this->getRationaleWithValue($betType, $confidence, $valueRating, $match),
            'value_rating' => round($valueRating, 1),
            'odds_source' => $oddsSource, // Fuente real de las odds (bet365_real, pinnacle_real, etc.)
        ];
    }

    private function getRecommendationLevel(float $confidence, BudgetConfiguration $budget): string
    {
        if ($confidence >= 85) return 'EXCELENTE';
        if ($confidence >= 75) return 'MUY BUENA';
        if ($confidence >= $budget->min_confidence + 10) return 'BUENA';
        return 'ACEPTABLE';
    }

    private function analyzeStrategyFit(string $betType, float $confidence, BudgetConfiguration $budget): array
    {
        $fit = [];

        switch ($budget->strategy) {
            case 'mansaniello':
                $fit = [
                    'compatibility' => $confidence >= 70 ? 'ALTA' : 'MEDIA',
                    'reason' => 'Mansaniello requiere alta confianza para progresión controlada',
                    'ideal_confidence' => '70-85%',
                ];
                break;

            case 'fibonacci':
                $fit = [
                    'compatibility' => $confidence >= 65 ? 'ALTA' : 'MEDIA',
                    'reason' => 'Fibonacci permite más flexibilidad en confianza',
                    'ideal_confidence' => '65-80%',
                ];
                break;

            case 'martingale':
                $fit = [
                    'compatibility' => $confidence >= 75 ? 'ALTA' : 'BAJA',
                    'reason' => 'Martingala requiere muy alta confianza por su riesgo',
                    'ideal_confidence' => '75-90%',
                ];
                break;

            case 'fixed':
                $fit = [
                    'compatibility' => 'ALTA',
                    'reason' => 'Apuesta fija es compatible con cualquier confianza',
                    'ideal_confidence' => '60%+',
                ];
                break;

            case 'percentage':
                $fit = [
                    'compatibility' => 'ALTA',
                    'reason' => 'Kelly adapta automáticamente según confianza',
                    'ideal_confidence' => 'Variable según Kelly',
                ];
                break;

            default:
                $fit = [
                    'compatibility' => 'MEDIA',
                    'reason' => 'Estrategia no reconocida',
                    'ideal_confidence' => '70%+',
                ];
        }

        return $fit;
    }

    private function getRiskLevel(float $confidence, float $amount, BudgetConfiguration $budget): string
    {
        $percentageOfBudget = ($amount / $budget->current_budget) * 100;
        
        if ($confidence >= 80 && $percentageOfBudget <= 3) return 'BAJO';
        if ($confidence >= 70 && $percentageOfBudget <= 5) return 'MEDIO';
        if ($confidence >= 60 && $percentageOfBudget <= 2) return 'MEDIO';
        return 'ALTO';
    }

    private function getRationale(string $betType, float $confidence, FootballMatch $match): string
    {
        $prediction = $match->prediction;
        
        $rationales = [
            'home_win' => "El modelo predice victoria local con {$confidence}% de confianza basado en forma reciente y estadísticas",
            'away_win' => "El análisis favorece al visitante con {$confidence}% de probabilidad de victoria",
            'draw' => "Las estadísticas sugieren un partido igualado con {$confidence}% de probabilidad de empate",
            'both_teams_score' => "Ambos equipos muestran capacidad ofensiva: {$confidence}% probabilidad de que ambos marquen",
            'over_2_5' => "Esperamos un partido con goles: {$confidence}% de probabilidad de más de 2.5 goles",
            'under_2_5' => "Las defensas pueden predominar: {$confidence}% de probabilidad de menos de 2.5 goles",
        ];

        return $rationales[$betType] ?? "Recomendación basada en análisis ML con {$confidence}% de confianza";
    }

    private function getMatchAnalysis(FootballMatch $match): array
    {
        $prediction = $match->prediction;
        
        return [
            'expected_goals' => [
                'home' => round($prediction->home_goals_prediction ?? 1.5, 1),
                'away' => round($prediction->away_goals_prediction ?? 1.2, 1),
            ],
            'most_likely_outcome' => $prediction->predicted_outcome ?? 'unknown',
            'overall_confidence' => round(($prediction->confidence_score ?? 0.7) * 100, 1),
            'total_goals_expected' => round(
                ($prediction->home_goals_prediction ?? 1.5) + 
                ($prediction->away_goals_prediction ?? 1.2), 1
            ),
            'match_difficulty' => $this->getMatchDifficulty($prediction),
            'value_bets' => $this->findValueBets($prediction),
        ];
    }

    private function getMatchDifficulty(MatchPrediction $prediction): string
    {
        $maxProb = max(
            $prediction->home_win_probability ?? 0,
            $prediction->draw_probability ?? 0,
            $prediction->away_win_probability ?? 0
        );

        if ($maxProb >= 0.7) return 'FÁCIL';
        if ($maxProb >= 0.5) return 'MEDIO';
        return 'DIFÍCIL';
    }

    private function findValueBets(MatchPrediction $prediction): array
    {
        $valueBets = [];
        
        // Buscar apuestas con alta probabilidad pero potenciales odds atractivas
        $bets = [
            'both_teams_score' => $prediction->both_teams_score_probability,
            'over_2_5' => $prediction->over_2_5_probability,
            'under_2_5' => $prediction->under_2_5_probability,
        ];

        foreach ($bets as $bet => $probability) {
            if ($probability && $probability >= 0.6) {
                $valueBets[] = $bet;
            }
        }

        return $valueBets;
    }

    private function getOpportunityScore(array $recommendations): float
    {
        if (empty($recommendations)) return 0;

        $bestRecommendation = $recommendations[0];
        
        // Score basado en confianza y potential profit
        $confidenceScore = $bestRecommendation['confidence'] / 100;
        $profitScore = min($bestRecommendation['potential_profit'] / 50, 1); // Max 1 point for 50+ profit
        
        return ($confidenceScore * 0.7) + ($profitScore * 0.3);
    }

    private function calculateRealisticOdds(string $betType, float $probability, FootballMatch $match): float
    {
        if ($probability <= 0) {
            return 1.5; // Odds mínima de seguridad
        }

        // Calcular odds implícitas básicas
        $impliedOdds = 1 / $probability;
        
        // Aplicar márgenes realistas según el tipo de apuesta
        $margin = $this->getBookmakerMargin($betType);
        $baseOdds = $impliedOdds * (1 + $margin);

        // Ajustar odds según rangos realistas del mercado
        $adjustedOdds = $this->applyMarketAdjustments($betType, $baseOdds, $probability);

        // Aplicar límites realistas
        return $this->applyOddsLimits($betType, $adjustedOdds);
    }

    private function getBookmakerMargin(string $betType): float
    {
        // Márgenes típicos de casas de apuestas por tipo de mercado
        $margins = [
            'home_win' => 0.05,     // 5% - mercado principal
            'away_win' => 0.05,     // 5% - mercado principal
            'draw' => 0.05,         // 5% - mercado principal
            'both_teams_score' => 0.08,  // 8% - mercado secundario
            'over_2_5' => 0.07,     // 7% - mercado de goles
            'under_2_5' => 0.07,    // 7% - mercado de goles
        ];

        return $margins[$betType] ?? 0.06; // 6% por defecto
    }

    private function applyMarketAdjustments(string $betType, float $odds, float $probability): float
    {
        // Ajustes específicos por tipo de apuesta para hacer odds más realistas
        switch ($betType) {
            case 'home_win':
            case 'away_win':
                // Victoria: odds más conservadoras para favoritos
                if ($probability > 0.6) {
                    return $odds * 0.95; // Reducir odds para favoritos claros
                } elseif ($probability < 0.3) {
                    return $odds * 1.1; // Aumentar odds para underdogs
                }
                break;

            case 'draw':
                // Empate: siempre en rango medio-alto
                if ($odds < 2.5) {
                    return max(2.5, $odds * 1.15);
                } elseif ($odds > 5.0) {
                    return min(5.0, $odds * 0.9);
                }
                break;

            case 'both_teams_score':
                // Ambos marcan: rango típico 1.4 - 3.5
                if ($odds < 1.4) {
                    return 1.4 + ($odds - 1.0) * 0.5;
                } elseif ($odds > 3.5) {
                    return 3.5;
                }
                break;

            case 'over_2_5':
            case 'under_2_5':
                // Goles: rango típico 1.3 - 4.0
                if ($odds < 1.3) {
                    return 1.3 + ($odds - 1.0) * 0.6;
                } elseif ($odds > 4.0) {
                    return 4.0;
                }
                break;
        }

        return $odds;
    }

    private function applyOddsLimits(string $betType, float $odds): float
    {
        // Límites generales realistas según el mercado
        $limits = [
            'home_win' => ['min' => 1.05, 'max' => 15.0],
            'away_win' => ['min' => 1.05, 'max' => 20.0], 
            'draw' => ['min' => 2.4, 'max' => 6.0],
            'both_teams_score' => ['min' => 1.3, 'max' => 4.0],
            'over_2_5' => ['min' => 1.2, 'max' => 5.0],
            'under_2_5' => ['min' => 1.2, 'max' => 5.0],
        ];

        $limit = $limits[$betType] ?? ['min' => 1.1, 'max' => 10.0];
        
        // Aplicar límites y redondear a 2 decimales
        $finalOdds = max($limit['min'], min($limit['max'], $odds));
        
        // Redondear a valores más "naturales" (terminados en .0, .5, etc.)
        return $this->roundToNaturalOdds($finalOdds);
    }

    private function roundToNaturalOdds(float $odds): float
    {
        // Redondear a valores más naturales que se ven en casas de apuestas reales
        if ($odds < 2.0) {
            // Para odds bajas, redondear a centésimas (.05, .10, .15, etc.)
            return round($odds * 20) / 20;
        } elseif ($odds < 5.0) {
            // Para odds medias, redondear a décimas (.1, .2, .3, etc.)
            return round($odds * 10) / 10;
        } else {
            // Para odds altas, redondear a enteros o medios (.0, .5)
            return round($odds * 2) / 2;
        }
    }

    private function getRecommendationLevelWithValue(float $confidence, float $valueRating, BudgetConfiguration $budget): string
    {
        // Combinación de confianza y value betting
        if ($confidence >= 80 && $valueRating >= 15) return 'EXCELENTE';
        if ($confidence >= 75 && $valueRating >= 10) return 'MUY BUENA';
        if ($confidence >= 70 || $valueRating >= 8) return 'BUENA';
        if ($confidence >= $budget->min_confidence && $valueRating >= 5) return 'ACEPTABLE';
        
        return 'DESCARTADA';
    }

    private function getRationaleWithValue(string $betType, float $confidence, float $valueRating, FootballMatch $match): string
    {
        $baseRationale = $this->getRationale($betType, $confidence, $match);
        
        if ($valueRating > 10) {
            $baseRationale .= " ⭐ VALUE BET: Las odds reales ofrecen {$valueRating}% más valor que la probabilidad implícita.";
        } elseif ($valueRating > 5) {
            $baseRationale .= " 💡 Ligero value: {$valueRating}% de valor detectado en las odds actuales.";
        }

        return $baseRationale;
    }
}