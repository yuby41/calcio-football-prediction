<?php

namespace App\Services;

use App\Models\BudgetConfiguration;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SimpleBettingRecommendationService
{
    public function getRecommendationsForBudget(BudgetConfiguration $budget): array
    {
        try {
            // Obtener partidos próximos con predicciones usando SQL directo
            $matches = DB::select("
                SELECT 
                    m.id,
                    m.match_date,
                    m.status,
                    ht.name as home_team_name,
                    at.name as away_team_name,
                    mp.home_win_probability,
                    mp.draw_probability,
                    mp.away_win_probability,
                    mp.both_teams_score_probability,
                    mp.over_2_5_probability,
                    mp.under_2_5_probability,
                    mp.first_half_over_0_5_probability,
                    mp.home_goals_prediction,
                    mp.away_goals_prediction,
                    mp.confidence_score,
                    mp.predicted_outcome
                FROM matches m
                JOIN teams ht ON m.home_team_id = ht.id
                JOIN teams at ON m.away_team_id = at.id
                JOIN match_predictions mp ON m.id = mp.match_id
                WHERE m.status IN ('scheduled', 'live')
                AND m.match_date >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
                AND m.match_date <= DATE_ADD(NOW(), INTERVAL 7 DAY)
                ORDER BY CASE WHEN m.status = 'live' THEN 0 ELSE 1 END, m.match_date ASC
                LIMIT 15
            ");

            $recommendations = [];

            foreach ($matches as $match) {
                $matchRecommendations = $this->analyzeMatch($match, $budget);
                if (!empty($matchRecommendations)) {
                    $recommendations[] = [
                        'match' => $this->formatMatch($match),
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
            
        } catch (\Exception $e) {
            \Log::error('Error in getRecommendationsForBudget: ' . $e->getMessage());
            return [];
        }
    }

    private function formatMatch($match): array
    {
        return [
            'id' => $match->id,
            'match_date' => $match->match_date,
            'status' => $match->status,
            'home_team' => ['name' => $match->home_team_name],
            'away_team' => ['name' => $match->away_team_name],
        ];
    }

    private function analyzeMatch($match, BudgetConfiguration $budget): array
    {
        $recommendations = [];

        // Analizar cada tipo de apuesta
        $betTypes = [
            'home_win' => [
                'probability' => $match->home_win_probability,
                'label' => 'Victoria Local',
                'description' => $match->home_team_name . ' gana'
            ],
            'away_win' => [
                'probability' => $match->away_win_probability,
                'label' => 'Victoria Visitante',
                'description' => $match->away_team_name . ' gana'
            ],
            'draw' => [
                'probability' => $match->draw_probability,
                'label' => 'Empate',
                'description' => 'Resultado empate'
            ],
            'both_teams_score' => [
                'probability' => $match->both_teams_score_probability,
                'label' => 'Ambos Marcan',
                'description' => 'Los dos equipos marcan gol'
            ],
            'over_2_5' => [
                'probability' => $match->over_2_5_probability,
                'label' => 'Más de 2.5 Goles',
                'description' => 'Más de 2.5 goles en total'
            ],
            'under_2_5' => [
                'probability' => $match->under_2_5_probability,
                'label' => 'Menos de 2.5 Goles',
                'description' => 'Menos de 2.5 goles en total'
            ],
            'over_0_5_first_half' => [
                'probability' => $match->first_half_over_0_5_probability,
                'label' => 'Over 0.5 1T',
                'description' => 'Al menos 1 gol en el primer tiempo'
            ],
        ];

        foreach ($betTypes as $betType => $info) {
            // CRITICAL FIX: Skip if probability is null, 0, or empty, but allow valid small probabilities
            if ($info['probability'] === null || $info['probability'] === '' || (float)$info['probability'] <= 0) continue;

            $confidence = $info['probability'] * 100;
            
            // Solo recomendar si supera el mínimo de confianza
            if ($confidence >= $budget->min_confidence) {
                $recommendation = $this->createRecommendation(
                    $betType,
                    $info,
                    $confidence,
                    $match,
                    $budget
                );
                
                if ($recommendation) {
                    $recommendations[] = $recommendation;
                }
            }
        }

        // Ordenar por confianza
        usort($recommendations, function($a, $b) {
            return $b['confidence'] <=> $a['confidence'];
        });

        return $recommendations;
    }

    private function createRecommendation(
        string $betType,
        array $betInfo,
        float $confidence,
        $match,
        BudgetConfiguration $budget
    ): ?array {
        // Calcular odds estimadas basadas en probabilidad
        $impliedOdds = $betInfo['probability'] > 0 ? 1 / $betInfo['probability'] : 1.5;
        
        // Ajustar odds para ser más realistas (agregar margen de casa)
        $estimatedOdds = $impliedOdds * 1.05; // 5% margen

        // Calcular cantidad recomendada según estrategia
        $recommendedAmount = $this->calculateBetAmount($budget, $confidence, $estimatedOdds);
        
        if ($recommendedAmount <= 0) {
            return null;
        }

        // Calcular potential profit
        $potentialProfit = ($recommendedAmount * $estimatedOdds) - $recommendedAmount;

        // Determinar nivel de recomendación
        $recommendationLevel = $this->getRecommendationLevel($confidence, $budget);

        // Verificar si es compatible con la estrategia
        $strategyFit = $this->analyzeStrategyFit($betType, $confidence, $budget);

        return [
            'bet_type' => $betType,
            'label' => $betInfo['label'],
            'description' => $betInfo['description'],
            'confidence' => round($confidence, 1),
            'estimated_odds' => round($estimatedOdds, 2),
            'recommended_amount' => (int)$recommendedAmount,
            'potential_profit' => round($potentialProfit, 2),
            'recommendation_level' => $recommendationLevel,
            'strategy_fit' => $strategyFit,
            'risk_level' => $this->getRiskLevel($confidence, $recommendedAmount, $budget),
            'rationale' => $this->getRationale($betType, $confidence, $match),
        ];
    }

    private function calculateBetAmount(BudgetConfiguration $budget, float $confidence, float $odds): float
    {
        $maxBetAmount = ($budget->current_budget * $budget->max_bet_percentage) / 100;
        
        // Cálculo básico según estrategia
        switch ($budget->strategy) {
            case 'mansaniello':
                $baseAmount = $budget->strategy_parameters['base_amount'] ?? 5.0;
                $confidenceMultiplier = max(0.5, ($confidence - 50) / 50); // 0.5 a 1.0
                return min($baseAmount * $confidenceMultiplier, $maxBetAmount);
                
            case 'fibonacci':
                $baseAmount = ($budget->current_budget * 0.02); // 2% del budget
                $confidenceMultiplier = max(0.5, ($confidence - 50) / 50);
                return min($baseAmount * $confidenceMultiplier, $maxBetAmount);
                
            case 'fixed':
                return min(10.0, $maxBetAmount); // Cantidad fija
                
            default:
                $baseAmount = ($budget->current_budget * 0.01); // 1% del budget
                $confidenceMultiplier = ($confidence - 50) / 50;
                return min($baseAmount * $confidenceMultiplier, $maxBetAmount);
        }
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

            case 'fixed':
                $fit = [
                    'compatibility' => 'ALTA',
                    'reason' => 'Apuesta fija es compatible con cualquier confianza',
                    'ideal_confidence' => '60%+',
                ];
                break;

            default:
                $fit = [
                    'compatibility' => 'MEDIA',
                    'reason' => 'Estrategia estándar',
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

    private function getRationale(string $betType, float $confidence, $match): string
    {
        $rationales = [
            'home_win' => "El modelo predice victoria local con {$confidence}% de confianza basado en análisis estadístico",
            'away_win' => "El análisis favorece al visitante con {$confidence}% de probabilidad de victoria",
            'draw' => "Las estadísticas sugieren un partido igualado con {$confidence}% de probabilidad de empate",
            'both_teams_score' => "Ambos equipos muestran capacidad ofensiva: {$confidence}% probabilidad de que ambos marquen",
            'over_2_5' => "Esperamos un partido con goles: {$confidence}% de probabilidad de más de 2.5 goles",
            'under_2_5' => "Las defensas pueden predominar: {$confidence}% de probabilidad de menos de 2.5 goles",
        ];

        return $rationales[$betType] ?? "Recomendación basada en análisis ML con {$confidence}% de confianza";
    }

    private function getMatchAnalysis($match): array
    {
        return [
            'expected_goals' => [
                'home' => round($match->home_goals_prediction ?? 1.5, 1),
                'away' => round($match->away_goals_prediction ?? 1.2, 1),
            ],
            'most_likely_outcome' => $match->predicted_outcome ?? 'unknown',
            'overall_confidence' => round(($match->confidence_score ?? 0.7) * 100, 1),
            'total_goals_expected' => round(
                ($match->home_goals_prediction ?? 1.5) + 
                ($match->away_goals_prediction ?? 1.2), 1
            ),
            'match_difficulty' => $this->getMatchDifficulty($match),
            'value_bets' => $this->findValueBets($match),
        ];
    }

    private function getMatchDifficulty($match): string
    {
        $maxProb = max(
            $match->home_win_probability ?? 0,
            $match->draw_probability ?? 0,
            $match->away_win_probability ?? 0
        );

        if ($maxProb >= 0.7) return 'FÁCIL';
        if ($maxProb >= 0.5) return 'MEDIO';
        return 'DIFÍCIL';
    }

    private function findValueBets($match): array
    {
        $valueBets = [];
        
        // Buscar apuestas con alta probabilidad
        $bets = [
            'both_teams_score' => $match->both_teams_score_probability,
            'over_2_5' => $match->over_2_5_probability,
            'under_2_5' => $match->under_2_5_probability,
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
        $profitScore = min($bestRecommendation['potential_profit'] / 50, 1);
        
        return ($confidenceScore * 0.7) + ($profitScore * 0.3);
    }
}