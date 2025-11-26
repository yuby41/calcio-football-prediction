<?php

namespace App\Http\Controllers;

use App\Models\BudgetConfiguration;
use App\Models\Bet;
use App\Models\BudgetHistory;
use App\Models\BettingStrategy;
use App\Models\FootballMatch;
use App\Services\BettingStrategyService;
use App\Services\BettingRecommendationService;
use App\Services\FootballApiOddsService;
use App\Services\DynamicPredictionService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class BudgetController extends Controller
{
    protected BettingStrategyService $bettingService;
    protected BettingRecommendationService $recommendationService;
    protected FootballApiOddsService $oddsService;
    protected DynamicPredictionService $dynamicPredictionService;

    public function __construct(
        BettingStrategyService $bettingService,
        BettingRecommendationService $recommendationService,
        FootballApiOddsService $oddsService,
        DynamicPredictionService $dynamicPredictionService
    ) {
        $this->bettingService = $bettingService;
        $this->recommendationService = $recommendationService;
        $this->oddsService = $oddsService;
        $this->dynamicPredictionService = $dynamicPredictionService;
    }

    public function index()
    {
        $configurations = BudgetConfiguration::with(['bets' => function($query) {
            $query->latest('created_at');
        }])->get();

        $totalBudget = $configurations->sum('current_budget');
        $totalProfit = $configurations->sum(function($config) {
            return $config->getNetProfitAttribute();
        });

        $recentBets = Bet::with(['match.homeTeam', 'match.awayTeam', 'budgetConfiguration'])
            ->latest('placed_at')
            ->limit(10)
            ->get();

        return view('budget.index', compact('configurations', 'totalBudget', 'totalProfit', 'recentBets'));
    }

    public function create()
    {
        $strategies = BettingStrategy::where('is_active', true)->get();
        return view('budget.create', compact('strategies'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'strategy' => 'required|in:mansaniello,fibonacci,martingale,fixed,percentage',
            'initial_budget' => 'required|numeric|min:1',
            'target_profit' => 'nullable|numeric|min:0',
            'max_bet_percentage' => 'required|numeric|min:0.1|max:50',
            'min_confidence' => 'required|numeric|min:50|max:99',
            'strategy_parameters' => 'nullable|array',
        ]);

        $config = BudgetConfiguration::create([
            'name' => $validated['name'],
            'strategy' => $validated['strategy'],
            'initial_budget' => $validated['initial_budget'],
            'current_budget' => $validated['initial_budget'],
            'target_profit' => $validated['target_profit'],
            'max_bet_percentage' => $validated['max_bet_percentage'],
            'min_confidence' => $validated['min_confidence'],
            'strategy_parameters' => $validated['strategy_parameters'] ?? [],
        ]);

        // Registrar depósito inicial
        BudgetHistory::create([
            'budget_configuration_id' => $config->id,
            'amount' => $validated['initial_budget'],
            'balance_before' => 0,
            'balance_after' => $validated['initial_budget'],
            'type' => 'deposit',
            'description' => 'Depósito inicial',
        ]);

        return redirect()->route('budget.show', $config)
            ->with('success', 'Configuración de budget creada exitosamente.');
    }

    public function show(BudgetConfiguration $budget)
    {
        // Cargar solo lo esencial inicialmente
        $budget->load(['bets' => function($query) {
            $query->latest()->limit(20)->with(['match.homeTeam', 'match.awayTeam']);
        }]);
        
        $performance = $this->bettingService->getStrategyPerformance($budget, 30);
        
        // Datos básicos para el gráfico (se cargarán más datos via AJAX si es necesario)
        $chartData = $this->getChartData($budget);
        
        return view('budget.show', compact('budget', 'performance', 'chartData'));
    }

    public function recommendations(BudgetConfiguration $budget)
    {
        // Get active prediction types with their accuracies
        $activePredictions = $this->dynamicPredictionService->getActivePredictionTypes();
        $predictionsSummary = $this->dynamicPredictionService->getActivePredictionsSummary();
        
        return view('budget.recommendations', compact('budget', 'activePredictions', 'predictionsSummary'));
    }

    public function placeBet(Request $request, BudgetConfiguration $budget)
    {
        try {
            // Log the incoming request for debugging
            \Log::info('placeBet request', [
                'budget_id' => $budget->id,
                'request_data' => $request->all()
            ]);

            $validated = $request->validate([
                'match_id' => 'required|exists:matches,id',
                'bet_type' => 'required|in:home_win,away_win,draw,over_2_5,under_2_5,over_1_5,under_1_5,over_0_5,under_0_5,both_teams_score,over_0_5_first_half',
                'odds' => 'required|numeric|min:1.01|max:50',
                'amount' => 'nullable|integer|min:2',
            ]);

            try {
                $match = FootballMatch::with('prediction')->findOrFail($validated['match_id']);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Partido no encontrado.'
                ], 404);
            }
            
            if (!$match->prediction) {
                return response()->json([
                    'success' => false,
                    'message' => 'Este partido no tiene predicción disponible.'
                ], 422);
            }

            // Obtener confianza de la predicción
            try {
                $confidence = $this->getConfidenceForBetType($match, $validated['bet_type']);
                
                if ($confidence <= 0) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No se puede calcular la confianza para este tipo de apuesta.'
                    ], 422);
                }
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error al calcular la confianza de la predicción.'
                ], 500);
            }
            
            if ($confidence < $budget->min_confidence) {
                return response()->json([
                    'success' => false,
                    'message' => "La confianza ({$confidence}%) es menor al mínimo requerido ({$budget->min_confidence}%)."
                ], 422);
            }

            // Calcular cantidad recomendada si no se especifica
            $amount = $validated['amount'] ?? $this->bettingService->calculateBetAmount(
                $budget, 
                $match, 
                $validated['bet_type'], 
                $confidence
            );

            // CRITICAL FIX: Enhanced budget validation including target profit
            if ($budget->hasReachedTargetProfit()) {
                return response()->json([
                    'success' => false,
                    'message' => '¡Objetivo de ganancia alcanzado! Sistema detenido para proteger beneficios.'
                ], 422);
            }
            
            if (!$budget->canBet()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pueden realizar apuestas en este momento.'
                ], 422);
            }

            if ($budget->isExhausted()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Presupuesto por debajo del límite mínimo. Sistema de apuestas desactivado.'
                ], 422);
            }

            // Check if amount is 0 (indicating strategy says no betting)
            if ($amount <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Presupuesto insuficiente para apostar según la estrategia actual.'
                ], 422);
            }

            if ($amount > $budget->current_budget) {
                return response()->json([
                    'success' => false,
                    'message' => 'Fondos insuficientes.'
                ], 422);
            }

            // Check if bet would exhaust budget completely
            if (($budget->current_budget - $amount) < 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Esta apuesta agotaría completamente el presupuesto.'
                ], 422);
            }

            // Crear la apuesta
            $bet = $this->bettingService->createBet(
                $budget,
                $match,
                $validated['bet_type'],
                $amount,
                $validated['odds'],
                $confidence
            );

            return response()->json([
                'success' => true,
                'message' => "Apuesta realizada: €{$amount} en {$bet->getBetTypeDisplayAttribute()}",
                'bet' => [
                    'id' => $bet->id,
                    'amount' => $amount,
                    'odds' => $validated['odds'],
                    'bet_type' => $validated['bet_type'],
                    'potential_win' => ($amount * $validated['odds']) - $amount
                ],
                'new_balance' => $budget->fresh()->current_budget
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Datos de entrada inválidos',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error interno: ' . $e->getMessage()
            ], 500);
        }
    }

    public function resolveBets(BudgetConfiguration $budget)
    {
        $pendingBets = $budget->bets()->where('status', 'pending')->get();
        $resolvedCount = 0;

        foreach ($pendingBets as $bet) {
            if ($bet->isResolvable()) {
                $this->bettingService->resolveBet($bet);
                $resolvedCount++;
            }
        }

        return back()->with('success', "Se resolvieron {$resolvedCount} apuestas.");
    }

    public function chartData(BudgetConfiguration $budget): JsonResponse
    {
        return response()->json($this->getChartData($budget));
    }

    public function opportunities(BudgetConfiguration $budget): JsonResponse
    {
        $opportunities = $this->getBettingOpportunities($budget);
        return response()->json($opportunities);
    }

    private function getChartData(BudgetConfiguration $budget): array
    {
        // Cache key específico para este budget y su última actualización
        $cacheKey = "budget_chart_{$budget->id}_{$budget->updated_at->timestamp}";
        
        return \Cache::remember($cacheKey, 1800, function() use ($budget) { // 30 minutos de cache
            // Evolución del budget basada en el historial
            $history = $budget->budgetHistory()
                ->orderBy('created_at')
                ->get();

        $dates = [];
        $balances = [];
        
        // Agregar punto inicial
        $dates[] = $budget->created_at->format('d/m');
        $balances[] = (float) $budget->initial_budget;
        
        // Procesar historial día por día
        $dailyHistory = $history->groupBy(function($item) {
            return $item->created_at->format('Y-m-d');
        });

        foreach ($dailyHistory as $date => $records) {
            $lastRecord = $records->last();
            $dates[] = \Carbon\Carbon::parse($date)->format('d/m');
            $balances[] = (float) $lastRecord->balance_after;
        }
        
        // Si no hay historial, usar balance actual
        if (empty($dates) || count($dates) === 1) {
            $dates = ['Inicio', 'Actual'];
            $balances = [(float) $budget->initial_budget, (float) $budget->current_budget];
        }

        // Distribución de tipos de apuesta
        $betTypes = $budget->bets()
            ->selectRaw('bet_type, COUNT(*) as count, SUM(actual_profit) as profit')
            ->whereIn('status', ['won', 'lost'])
            ->groupBy('bet_type')
            ->get()
            ->map(function($item) {
                return [
                    'type' => $item->bet_type,
                    'count' => $item->count,
                    'profit' => (float) $item->profit,
                ];
            });

        // Performance por mes
        $monthlyPerformance = $budget->bets()
            ->whereIn('status', ['won', 'lost'])
            ->get()
            ->filter(function($bet) {
                return $bet->resolved_at !== null;
            })
            ->groupBy(function($bet) {
                return $bet->resolved_at->format('Y-m');
            })
            ->map(function($bets, $month) {
                return [
                    'month' => $month,
                    'total_bets' => $bets->count(),
                    'won_bets' => $bets->where('status', 'won')->count(),
                    'profit' => $bets->sum('actual_profit'),
                    'win_rate' => $bets->count() > 0 ? ($bets->where('status', 'won')->count() / $bets->count()) * 100 : 0,
                ];
            })
            ->values();

            return [
                'dates' => $dates,
                'balances' => $balances,
                'bet_types_distribution' => $betTypes,
                'monthly_performance' => $monthlyPerformance,
            ];
        }); // Cierre del Cache::remember
    }

    private function getBettingOpportunities(BudgetConfiguration $budget): array
    {
        // Cache key específico para este budget y timestamp
        $cacheKey = "budget_opportunities_{$budget->id}_" . now()->format('Y-m-d-H');
        
        return \Cache::remember($cacheKey, 900, function() use ($budget) { // 15 minutos de cache
            // Obtener partidos en vivo y programados priorizando equipos con datos reales
            $liveMatches = FootballMatch::with(['homeTeam.statistics', 'awayTeam.statistics', 'prediction'])
                ->where('status', 'live')
                ->where('match_date', '>=', now()->subDay())
                ->where('match_date', '<=', now()->addHours(3))
                ->whereHas('prediction')
                ->whereHas('homeTeam.statistics')
                ->whereHas('awayTeam.statistics')
                ->orderBy('match_date')
                ->limit(30)
                ->get();
                
            $scheduledMatches = FootballMatch::with(['homeTeam.statistics', 'awayTeam.statistics', 'prediction'])
                ->where('status', 'scheduled')
                ->where('match_date', '>=', now())
                ->where('match_date', '<=', now()->addDays(7))
                ->whereHas('prediction')
                ->whereHas('homeTeam.statistics')
                ->whereHas('awayTeam.statistics')
                ->orderBy('match_date')
                ->limit(30)
                ->get();
                
            // Fallback: si no hay suficientes matches con datos reales, incluir algunos sintéticos
            if ($liveMatches->count() + $scheduledMatches->count() < 20) {
                $fallbackMatches = FootballMatch::with(['homeTeam', 'awayTeam', 'prediction'])
                    ->where('status', 'scheduled')
                    ->where('match_date', '>=', now())
                    ->where('match_date', '<=', now()->addDays(3))
                    ->whereHas('prediction')
                    ->whereDoesntHave('homeTeam.statistics')
                    ->orWhereDoesntHave('awayTeam.statistics')
                    ->orderBy('match_date')
                    ->limit(10)
                    ->get();
                    
                $scheduledMatches = $scheduledMatches->concat($fallbackMatches);
            }
                
            // Combinar ambas colecciones
            $upcomingMatches = $liveMatches->concat($scheduledMatches);

        $opportunities = [];
        $filteredMatches = 0;
        $filteredBets = 0;

        foreach ($upcomingMatches as $match) {
            if (!$match->prediction) continue;

            // Verificar si el partido ya tiene resultados conocidos que invaliden apuestas
            if ($this->hasKnownResults($match)) {
                $filteredMatches++;
                \Log::info('Partido filtrado por resultados conocidos', [
                    'match' => "{$match->homeTeam->name} vs {$match->awayTeam->name}",
                    'match_id' => $match->id,
                    'status' => $match->status,
                    'match_date' => $match->match_date,
                    'home_goals' => $match->home_goals,
                    'away_goals' => $match->away_goals,
                    'reason' => 'Partido con resultados conocidos'
                ]);
                continue; // Saltar partidos con resultados ya conocidos
            }

            $betTypes = ['home_win', 'away_win', 'draw', 'over_2_5', 'both_teams_score', 'over_0_5_first_half'];
            
            foreach ($betTypes as $betType) {
                $confidence = $this->getConfidenceForBetType($match, $betType);
                
                // Verificar si esta apuesta específica aún es válida
                if (!$this->isBetTypeStillValid($match, $betType)) {
                    $filteredBets++;
                    \Log::debug('Apuesta filtrada por resultado conocido', [
                        'match' => "{$match->homeTeam->name} vs {$match->awayTeam->name}",
                        'bet_type' => $betType,
                        'status' => $match->status,
                        'time_elapsed' => $match->status === 'live' ? $this->getMatchTimeElapsed($match) : null,
                        'score' => $match->status === 'live' ? "{$match->home_goals}-{$match->away_goals}" : null,
                        'reason' => 'Apuesta ya resuelta o no válida'
                    ]);
                    continue; // Saltar apuestas que ya no son válidas
                }
                
                if ($confidence >= $budget->min_confidence) {
                    $recommendedAmount = $this->bettingService->calculateBetAmount(
                        $budget, 
                        $match, 
                        $betType, 
                        $confidence
                    );
                    
                    // Obtener odds reales del servicio de odds
                    $realOdds = $this->oddsService->getRealOddsForMatch($match);
                    
                    // Si no hay odds reales disponibles para este tipo de apuesta, saltar
                    if (!isset($realOdds[$betType])) {
                        continue;
                    }
                    
                    $odds = $realOdds[$betType];
                    $oddsSource = $realOdds[$betType . '_source'] ?? $realOdds['source'] ?? 'unknown';
                    
                    // Validar que las odds sean realistas
                    $originalOdds = $odds;
                    if (!$this->areOddsRealistic($betType, $odds, $confidence)) {
                        // Usar odds corregidas
                        $odds = $this->correctUnrealisticOdds($betType, $odds, $confidence);
                        
                        // Log para debugging
                        \Log::info('Odds no realistas corregidas en recomendaciones IA', [
                            'match' => "{$match->homeTeam->name} vs {$match->awayTeam->name}",
                            'match_id' => $match->id,
                            'bet_type' => $betType,
                            'original_odds' => $originalOdds,
                            'corrected_odds' => $odds,
                            'confidence' => $confidence,
                            'source' => $oddsSource,
                            'improvement' => 'Odds ajustadas a rango realista con coherencia IA'
                        ]);
                    } else {
                        // Log odds válidas para tracking
                        \Log::debug('Odds válidas en recomendaciones IA', [
                            'match' => "{$match->homeTeam->name} vs {$match->awayTeam->name}",
                            'bet_type' => $betType,
                            'odds' => $odds,
                            'confidence' => $confidence,
                            'source' => $oddsSource
                        ]);
                    }
                    
                    // Ajustar odds para partidos en vivo (mayor volatilidad)
                    if ($match->status === 'live') {
                        $odds = $this->adjustLiveOdds($odds, $betType, $match);
                    }

                    $opportunities[] = [
                        'match_id' => $match->id,
                        'match_name' => "{$match->homeTeam->name} vs {$match->awayTeam->name}",
                        'match_date' => $match->match_date->format('d/m/Y H:i'),
                        'league' => $match->league,
                        'country' => $this->getCountryFromLeague($match->league),
                        'season' => $match->season,
                        'round' => $match->round,
                        'home_team' => [
                            'id' => $match->homeTeam->id,
                            'name' => $match->homeTeam->name,
                            'short_name' => $this->getShortName($match->homeTeam->name),
                            'has_real_data' => $match->homeTeam->statistics()->exists()
                        ],
                        'away_team' => [
                            'id' => $match->awayTeam->id,
                            'name' => $match->awayTeam->name,
                            'short_name' => $this->getShortName($match->awayTeam->name),
                            'has_real_data' => $match->awayTeam->statistics()->exists()
                        ],
                        'bet_type' => $betType,
                        'bet_type_display' => (new Bet(['bet_type' => $betType]))->getBetTypeDisplayAttribute(),
                        'confidence' => $confidence,
                        'recommended_amount' => $recommendedAmount,
                        'odds' => $odds,
                        'odds_source' => $oddsSource,
                        'potential_profit' => round(($recommendedAmount * $odds) - $recommendedAmount, 2),
                        'is_live' => $match->status === 'live',
                        'match_status' => $match->status,
                        'live_indicator' => $match->status === 'live' ? '🔴 EN VIVO' : '',
                        'urgency' => $match->status === 'live' ? 'ALTA' : $this->getMatchUrgency($match),
                        'current_score' => $match->status === 'live' ? 
                            "{$match->home_goals}-{$match->away_goals}" : null,
                        'prediction_details' => [
                            'home_goals_prediction' => $match->prediction->home_goals_prediction ?? 0,
                            'away_goals_prediction' => $match->prediction->away_goals_prediction ?? 0,
                            'total_goals_prediction' => ($match->prediction->home_goals_prediction ?? 0) + ($match->prediction->away_goals_prediction ?? 0),
                            'both_teams_score_probability' => ($match->prediction->both_teams_score_probability ?? 0) * 100,
                            'both_teams_score_prediction' => ($match->prediction->both_teams_score_probability ?? 0) > 0.5 ? 'Sí' : 'No',
                            'both_teams_score_confident' => $this->isBothTeamsScoreConfident($match->prediction, $budget->min_confidence),
                            'model_version' => $match->prediction->model_version ?? 'unknown'
                        ],
                        'analysis' => [
                            'difficulty_level' => $this->getMatchDifficulty($confidence),
                            'value_rating' => $this->calculateValueRating($confidence, $odds),
                            'recommendation_level' => $this->getRecommendationLevel($confidence),
                            'risk_level' => $this->getRiskLevel($odds, $confidence)
                        ]
                    ];
                }
            }
        }

        // Agrupar por partido
        $groupedOpportunities = [];
        foreach ($opportunities as $opportunity) {
            $matchId = $opportunity['match_id'];
            
            if (!isset($groupedOpportunities[$matchId])) {
                // Crear la estructura del partido
                $groupedOpportunities[$matchId] = [
                    'match_id' => $opportunity['match_id'],
                    'match_name' => $opportunity['match_name'],
                    'match_date' => $opportunity['match_date'],
                    'league' => $opportunity['league'],
                    'country' => $opportunity['country'],
                    'season' => $opportunity['season'],
                    'round' => $opportunity['round'],
                    'home_team' => $opportunity['home_team'],
                    'away_team' => $opportunity['away_team'],
                    'is_live' => $opportunity['is_live'],
                    'match_status' => $opportunity['match_status'],
                    'live_indicator' => $opportunity['live_indicator'],
                    'current_score' => $opportunity['current_score'],
                    'prediction_details' => $opportunity['prediction_details'],
                    'max_confidence' => round($opportunity['confidence'], 2),
                    'urgency' => $opportunity['urgency'],
                    'recommendations' => []
                ];
            }
            
            // Agregar la recomendación específica
            $groupedOpportunities[$matchId]['recommendations'][] = [
                'bet_type' => $opportunity['bet_type'],
                'bet_type_display' => $opportunity['bet_type_display'],
                'confidence' => round($opportunity['confidence'], 2),
                'recommended_amount' => $opportunity['recommended_amount'],
                'odds' => $opportunity['odds'],
                'odds_source' => $opportunity['odds_source'] ?? 'unknown',
                'potential_profit' => $opportunity['potential_profit'],
                'analysis' => $opportunity['analysis'],
                'prediction_details' => $opportunity['prediction_details']
            ];
            
            // Actualizar la confianza máxima para ordenamiento
            if ($opportunity['confidence'] > $groupedOpportunities[$matchId]['max_confidence']) {
                $groupedOpportunities[$matchId]['max_confidence'] = round($opportunity['confidence'], 2);
            }
        }

        // Convertir a array indexado y separar live vs scheduled
        $groupedArray = array_values($groupedOpportunities);
        
        // Separar partidos en vivo de programados
        $liveMatches = array_filter($groupedArray, fn($match) => $match['is_live']);
        $scheduledMatches = array_filter($groupedArray, fn($match) => !$match['is_live']);
        
        // Ordenar cada grupo por score de calidad
        usort($liveMatches, function($a, $b) {
            $scoreA = $this->calculateMatchRecommendationScore($a);
            $scoreB = $this->calculateMatchRecommendationScore($b);
            return $scoreB <=> $scoreA; // Mayor a menor
        });
        
        usort($scheduledMatches, function($a, $b) {
            $scoreA = $this->calculateMatchRecommendationScore($a);
            $scoreB = $this->calculateMatchRecommendationScore($b);
            return $scoreB <=> $scoreA; // Mayor a menor
        });
        
        // Tomar los mejores: 8 live + 7 scheduled
        $topLiveMatches = array_slice($liveMatches, 0, 8);
        $topScheduledMatches = array_slice($scheduledMatches, 0, 7);
        
        // Combinar: live primero, luego scheduled
        $finalMatches = array_merge($topLiveMatches, $topScheduledMatches);

        // Log de resumen del filtrado
        $totalValidRecommendations = array_sum(array_map(fn($match) => count($match['recommendations']), $finalMatches));
        
        // Log scores of top matches for debugging
        $topLiveScores = array_slice(array_map(fn($m) => [
            'match' => $m['match_name'], 
            'score' => $this->calculateMatchRecommendationScore($m),
            'confidence' => $m['max_confidence']
        ], $liveMatches), 0, 3);
        
        $topScheduledScores = array_slice(array_map(fn($m) => [
            'match' => $m['match_name'], 
            'score' => $this->calculateMatchRecommendationScore($m),
            'confidence' => $m['max_confidence']
        ], $scheduledMatches), 0, 3);
        
        \Log::info('Resumen filtrado de recomendaciones IA', [
            'total_matches_analyzed' => $upcomingMatches->count(),
            'filtered_matches' => $filteredMatches,
            'filtered_individual_bets' => $filteredBets,
            'live_matches_available' => count($liveMatches),
            'scheduled_matches_available' => count($scheduledMatches),
            'top_live_matches_returned' => count($topLiveMatches),
            'top_scheduled_matches_returned' => count($topScheduledMatches),
            'total_final_matches' => count($finalMatches),
            'total_valid_recommendations' => $totalValidRecommendations,
            'top_live_samples' => $topLiveScores,
            'top_scheduled_samples' => $topScheduledScores
        ]);

            return $finalMatches; // 8 mejores live + 7 mejores scheduled
        }); // Cierre del Cache::remember
    }

    /**
     * Calcula un score de calidad para un partido basado en múltiples factores
     * Combina: confianza, valor, cantidad de recomendaciones, urgencia
     */
    private function calculateMatchRecommendationScore(array $matchData): float
    {
        $score = 0;
        
        // Factor 1: Confianza máxima (0-100 puntos)
        $confidenceScore = $matchData['max_confidence'];
        $score += $confidenceScore;
        
        // Factor 2: Cantidad de recomendaciones (más opciones = mejor, máx 30 puntos)
        $recommendationCount = count($matchData['recommendations']);
        $countScore = min($recommendationCount * 10, 30);
        $score += $countScore;
        
        // Factor 3: Value Rating promedio (puede agregar hasta 50 puntos)
        $totalValueRating = 0;
        $valueCount = 0;
        foreach ($matchData['recommendations'] as $rec) {
            if ($rec['analysis']['value_rating'] > 0) {
                $totalValueRating += $rec['analysis']['value_rating'];
                $valueCount++;
            }
        }
        $avgValueRating = $valueCount > 0 ? $totalValueRating / $valueCount : 0;
        $valueScore = min($avgValueRating * 0.5, 50); // Máximo 50 puntos por valor
        $score += $valueScore;
        
        // Factor 4: Bonus menor por partidos en vivo (ya tienen prioridad absoluta en sort)
        if ($matchData['is_live']) {
            $liveBonus = 5; // Bonus pequeño - la prioridad ya se maneja en el sort principal
            $score += $liveBonus;
        }
        
        // Factor 5: Bonus por múltiples recomendaciones de alta calidad
        $highQualityCount = 0;
        foreach ($matchData['recommendations'] as $rec) {
            if ($rec['confidence'] >= 75) {
                $highQualityCount++;
            }
        }
        if ($highQualityCount >= 2) {
            $qualityBonus = $highQualityCount * 5; // 5 puntos por cada recomendación de alta calidad
            $score += $qualityBonus;
        }
        
        // Factor 6: Penalización por baja confianza general
        if ($matchData['max_confidence'] < 60) {
            $lowConfidencePenalty = (60 - $matchData['max_confidence']) * 0.5;
            $score -= $lowConfidencePenalty;
        }
        
        return $score;
    }

    private function getShortName(string $name): string
    {
        $words = explode(' ', $name);
        if (count($words) <= 2) {
            return $name;
        }
        
        // Tomar primera palabra y última palabra si hay más de 2
        return $words[0] . ' ' . end($words);
    }

    private function getMatchDifficulty(float $confidence): string
    {
        if ($confidence >= 85) return 'Fácil';
        if ($confidence >= 70) return 'Moderada';
        if ($confidence >= 55) return 'Difícil';
        return 'Muy Difícil';
    }

    private function calculateValueRating(float $confidence, float $odds): float
    {
        // Calcular valor implícito vs probabilidad de la IA
        $impliedProbability = (1 / $odds) * 100;
        $valueRating = $confidence - $impliedProbability;
        
        return round(max(0, $valueRating), 2);
    }

    private function getRecommendationLevel(float $confidence): string
    {
        if ($confidence >= 80) return 'EXCELENTE';
        if ($confidence >= 70) return 'MUY BUENA';
        if ($confidence >= 60) return 'BUENA';
        return 'ACEPTABLE';
    }

    private function getRiskLevel(float $odds, float $confidence): string
    {
        // Combinar odds y confianza para evaluar riesgo
        $riskScore = ($odds - 1) * (100 - $confidence) / 100;
        
        if ($riskScore <= 1.5) return 'BAJO';
        if ($riskScore <= 3.0) return 'MEDIO';
        return 'ALTO';
    }

    private function getConfidenceForBetType(FootballMatch $match, string $betType): float
    {
        if (!$match->prediction) return 0;

        $probability = match($betType) {
            'home_win' => $match->prediction->home_win_probability ?? 0,
            'away_win' => $match->prediction->away_win_probability ?? 0,
            'draw' => $match->prediction->draw_probability ?? 0,
            'over_2_5' => $match->prediction->over_2_5_probability ?? 0,
            'under_2_5' => $match->prediction->under_2_5_probability ?? 0,
            'over_1_5' => $this->calculateOver15Probability($match),
            'under_1_5' => $this->calculateUnder15Probability($match),
            'over_0_5' => $this->calculateOver05Probability($match),
            'under_0_5' => $this->calculateUnder05Probability($match),
            'both_teams_score' => $match->prediction->both_teams_score_probability ?? 0,
            'over_0_5_first_half' => $match->prediction->first_half_over_0_5_probability ?? 0,
            default => 0,
        };

        // Convertir de probabilidad (0-1) a porcentaje (0-100) y redondear a 2 decimales
        return round($probability * 100, 2);
    }

    /**
     * Ajusta las odds para partidos en vivo considerando la volatilidad
     */
    private function adjustLiveOdds(float $baseOdds, string $betType, FootballMatch $match): float
    {
        // Obtener tiempo transcurrido del partido (asumiendo 90 min total)
        $timeElapsed = $this->getMatchTimeElapsed($match);
        $volatilityFactor = $this->calculateLiveVolatility($betType, $timeElapsed, $match);
        
        // Ajustar odds según volatilidad
        $adjustedOdds = $baseOdds * (1 + $volatilityFactor);
        
        // Aplicar límites realistas para partidos en vivo
        return max(1.01, min(50.0, round($adjustedOdds, 2)));
    }

    /**
     * Calcula la volatilidad de las odds en vivo
     */
    private function calculateLiveVolatility(string $betType, int $timeElapsed, FootballMatch $match): float 
    {
        $baseVolatility = 0;
        
        // Volatilidad base por tipo de apuesta en vivo
        $volatilities = [
            'home_win' => 0.15,      // 15% volatilidad base
            'away_win' => 0.15,
            'draw' => 0.20,          // Empate más volátil en vivo
            'over_2_5' => 0.25,      // Goles muy volátiles en vivo
            'under_2_5' => 0.25,
            'both_teams_score' => 0.20,
            'over_0_5_first_half' => 0.30,  // Muy volátil - se decide rápido en 1T
        ];
        
        $baseVolatility = $volatilities[$betType] ?? 0.15;
        
        // Ajustar por tiempo transcurrido (más tiempo = menos volatilidad)
        $timeAdjustment = max(0.5, 1 - ($timeElapsed / 120)); // Reduce con el tiempo
        
        // Ajustar por marcador actual (partidos igualados = más volatilidad)
        $scoreAdjustment = 1.0;
        if ($match->home_goals !== null && $match->away_goals !== null) {
            $goalDifference = abs($match->home_goals - $match->away_goals);
            $scoreAdjustment = $goalDifference === 0 ? 1.3 : // Empate = +30% volatilidad
                              ($goalDifference === 1 ? 1.1 : 0.9); // 1 gol diferencia = +10%, más = -10%
        }
        
        return $baseVolatility * $timeAdjustment * $scoreAdjustment;
    }

    /**
     * Obtiene el tiempo transcurrido del partido en minutos
     */
    private function getMatchTimeElapsed(FootballMatch $match): int
    {
        if ($match->status !== 'live') {
            return 0;
        }
        
        // Calcular tiempo transcurrido desde el inicio del partido
        $now = now();
        $matchStart = $match->match_date;
        
        $minutesElapsed = $matchStart->diffInMinutes($now);
        
        // Limitar a un rango realista (0-120 minutos incluyendo extra time)
        return (int) min(120, max(0, $minutesElapsed));
    }

    /**
     * Determina la urgencia de un partido basado en cuándo comienza
     */
    private function getMatchUrgency(FootballMatch $match): string
    {
        if ($match->status === 'live') {
            return 'ALTA';
        }
        
        $hoursUntilMatch = now()->diffInHours($match->match_date, false);
        
        if ($hoursUntilMatch <= 2) return 'ALTA';
        if ($hoursUntilMatch <= 6) return 'MEDIA';
        if ($hoursUntilMatch <= 24) return 'BAJA';
        
        return 'MUY BAJA';
    }

    public function edit(BudgetConfiguration $budget)
    {
        $strategies = BettingStrategy::where('is_active', true)->get();
        return view('budget.edit', compact('budget', 'strategies'));
    }

    public function update(Request $request, BudgetConfiguration $budget)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'strategy' => 'required|in:mansaniello,fibonacci,martingale,fixed,percentage',
            'target_profit' => 'nullable|numeric|min:0',
            'max_bet_percentage' => 'required|numeric|min:0.1|max:50',
            'min_confidence' => 'required|numeric|min:50|max:99',
            'strategy_parameters' => 'nullable|array',
        ]);

        $budget->update($validated);

        return redirect()->route('budget.show', $budget)
            ->with('success', 'Configuración de budget actualizada exitosamente.');
    }

    public function destroy(BudgetConfiguration $budget)
    {
        // Verificar si hay apuestas pendientes
        $pendingBets = $budget->bets()->where('status', 'pending')->count();
        
        if ($pendingBets > 0) {
            return back()->withErrors([
                'budget' => 'No se puede eliminar una configuración con apuestas pendientes.'
            ]);
        }

        $budgetName = $budget->name;
        $budget->delete();

        return redirect()->route('budget.index')
            ->with('success', "Configuración '{$budgetName}' eliminada exitosamente.");
    }


    public function deleteBet(Request $request, BudgetConfiguration $budget, Bet $bet): JsonResponse
    {
        try {
            // Log the request for debugging
            \Log::info('deleteBet request', [
                'budget_id' => $budget->id,
                'bet_id' => $bet->id,
                'bet_status' => $bet->status
            ]);

            $recalculationService = app(\App\Services\BudgetRecalculationService::class);
            $result = $recalculationService->deleteBetWithRecalculation($budget, $bet);

            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
                'refunded_amount' => $result['deleted_bet']['amount'],
                'new_balance' => $result['new_balance'],
                'recalculation_details' => $result['recalculation_details']
            ]);

        } catch (\Exception $e) {
            \Log::error('Error deleting bet', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Elimina múltiples apuestas de una vez
     */
    public function deleteMultipleBets(Request $request, BudgetConfiguration $budget): JsonResponse
    {
        try {
            $validated = $request->validate([
                'bet_ids' => 'required|array|min:1',
                'bet_ids.*' => 'required|integer|exists:bets,id'
            ]);

            \Log::info('deleteMultipleBets request', [
                'budget_id' => $budget->id,
                'bet_ids' => $validated['bet_ids'],
                'count' => count($validated['bet_ids'])
            ]);

            $recalculationService = app(\App\Services\BudgetRecalculationService::class);
            $result = $recalculationService->deleteMultipleBetsWithRecalculation($budget, $validated['bet_ids']);

            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
                'deleted_bets' => $result['deleted_bets'],
                'total_refunded' => $result['total_refunded'],
                'new_balance' => $result['new_balance'],
                'recalculation_details' => $result['recalculation_details']
            ]);

        } catch (\Exception $e) {
            \Log::error('Error deleting multiple bets', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verifica la integridad del presupuesto
     */
    public function checkIntegrity(BudgetConfiguration $budget): JsonResponse
    {
        try {
            $recalculationService = app(\App\Services\BudgetRecalculationService::class);
            $inconsistencies = $recalculationService->detectBudgetInconsistencies($budget);
            $verification = $recalculationService->verifyBudgetIntegrity($budget);

            return response()->json([
                'success' => true,
                'budget_name' => $budget->name,
                'verification' => $verification,
                'inconsistencies' => $inconsistencies
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Recalcula el historial del presupuesto
     */
    public function recalculateHistory(BudgetConfiguration $budget): JsonResponse
    {
        try {
            $recalculationService = app(\App\Services\BudgetRecalculationService::class);
            $result = $recalculationService->recalculateCompleteBudgetHistory($budget);

            return response()->json([
                'success' => $result['success'],
                'message' => 'Historial de presupuesto recalculado exitosamente.',
                'final_balance' => $result['final_balance'],
                'details' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Valida si las odds son realistas para el tipo de apuesta y confianza
     */
    private function areOddsRealistic(string $betType, float $odds, float $confidence): bool
    {
        // Rangos realistas para cada tipo de apuesta
        $realisticRanges = [
            'home_win' => ['min' => 1.1, 'max' => 15.0],
            'away_win' => ['min' => 1.1, 'max' => 20.0],
            'draw' => ['min' => 2.2, 'max' => 6.5],
            'over_2_5' => ['min' => 1.2, 'max' => 5.0],
            'under_2_5' => ['min' => 1.2, 'max' => 5.0],
            'both_teams_score' => ['min' => 1.3, 'max' => 4.0],
            'over_0_5_first_half' => ['min' => 1.15, 'max' => 2.3],
        ];

        $range = $realisticRanges[$betType] ?? ['min' => 1.1, 'max' => 10.0];
        
        // Verificar que las odds estén en rango realista
        if ($odds < $range['min'] || $odds > $range['max']) {
            return false;
        }

        // Verificar coherencia con la confianza (probabilidad vs odds)
        $impliedProbability = (1 / $odds) * 100;
        $probabilityDifference = abs($confidence - $impliedProbability);
        
        // Si la diferencia es muy grande (>40%), las odds pueden ser irreales
        if ($probabilityDifference > 40) {
            return false;
        }

        // Para confianzas muy altas, las odds no pueden ser muy altas
        if ($confidence >= 80 && $odds > 2.5) {
            return false;
        }

        // Para confianzas bajas, las odds no pueden ser muy bajas
        if ($confidence <= 60 && $odds < 1.5) {
            return false;
        }

        return true;
    }

    /**
     * Corrige odds no realistas aplicando límites y coherencia
     */
    private function correctUnrealisticOdds(string $betType, float $odds, float $confidence): float
    {
        // Rangos realistas para cada tipo de apuesta
        $realisticRanges = [
            'home_win' => ['min' => 1.1, 'max' => 15.0],
            'away_win' => ['min' => 1.1, 'max' => 20.0], 
            'draw' => ['min' => 2.2, 'max' => 6.5],
            'over_2_5' => ['min' => 1.2, 'max' => 5.0],
            'under_2_5' => ['min' => 1.2, 'max' => 5.0],
            'both_teams_score' => ['min' => 1.3, 'max' => 4.0],
            'over_0_5_first_half' => ['min' => 1.15, 'max' => 2.3],
        ];

        $range = $realisticRanges[$betType] ?? ['min' => 1.1, 'max' => 10.0];
        
        // Primero aplicar límites de rango
        $correctedOdds = max($range['min'], min($range['max'], $odds));
        
        // Luego ajustar por coherencia con confianza
        $targetProbability = $confidence / 100;
        $targetOdds = 1 / $targetProbability;
        
        // Aplicar margen típico de bookmaker (5-8%)
        $margin = match($betType) {
            'home_win', 'away_win', 'draw' => 1.05,
            'over_2_5', 'under_2_5' => 1.07,
            'both_teams_score' => 1.08,
            'over_0_5_first_half' => 1.06,
            default => 1.06
        };
        
        $targetOdds *= $margin;
        
        // Usar un promedio ponderado entre odds original corregida y odds basada en confianza
        $finalOdds = ($correctedOdds * 0.6) + ($targetOdds * 0.4);
        
        // Aplicar límites finales y redondear
        $finalOdds = max($range['min'], min($range['max'], $finalOdds));
        
        return $this->roundOddsToNatural($finalOdds);
    }

    /**
     * Redondea odds a valores más naturales que se ven en casas de apuestas
     */
    private function roundOddsToNatural(float $odds): float
    {
        if ($odds < 2.0) {
            // Para odds bajas, redondear a .05 (.05, .10, .15, etc.)
            return round($odds * 20) / 20;
        } elseif ($odds < 5.0) {
            // Para odds medias, redondear a .1 (.1, .2, .3, etc.)
            return round($odds * 10) / 10;
        } else {
            // Para odds altas, redondear a .0 o .5 
            return round($odds * 2) / 2;
        }
    }

    /**
     * Verifica si el partido ya tiene resultados conocidos que invaliden apuestas
     */
    private function hasKnownResults(FootballMatch $match): bool
    {
        // Si el partido ya terminó, no debe aparecer en recomendaciones
        if ($match->status === 'finished') {
            return true;
        }

        // Si el partido fue cancelado o pospuesto
        if (in_array($match->status, ['cancelled', 'postponed', 'suspended'])) {
            return true;
        }

        // Para partidos en vivo, verificar si ya es muy tarde para apostar
        if ($match->status === 'live') {
            $timeElapsed = $this->getMatchTimeElapsed($match);
            
            // Si el partido está en tiempo de descuento o ya terminó prácticamente
            if ($timeElapsed >= 90) {
                return true;
            }
        }

        return false;
    }

    /**
     * Verifica si un tipo de apuesta específico aún es válido para el partido
     */
    private function isBetTypeStillValid(FootballMatch $match, string $betType): bool
    {
        // Para partidos programados, todas las apuestas son válidas
        if ($match->status === 'scheduled') {
            return true;
        }

        // Para partidos en vivo, verificar según el tipo de apuesta
        if ($match->status === 'live') {
            $timeElapsed = $this->getMatchTimeElapsed($match);
            $homeGoals = $match->home_goals;
            $awayGoals = $match->away_goals;
            
            // Si no tenemos datos de goles actualizados, ser conservadores
            if ($homeGoals === null || $awayGoals === null) {
                // Sin datos de goles, solo permitir apuestas en los primeros 30 minutos
                return $timeElapsed < 30;
            }
            
            $totalGoals = $homeGoals + $awayGoals;

            return match($betType) {
                // Resultado del partido: válido hasta minuto 85
                'home_win', 'away_win', 'draw' => $timeElapsed < 85,
                
                // Over 2.5: inválido si ya hay 3+ goles
                'over_2_5' => $totalGoals < 3,
                
                // Under 2.5: inválido si ya hay 3+ goles
                'under_2_5' => $totalGoals < 3,
                
                // Ambos marcan: inválido si ya marcaron ambos o es imposible
                'both_teams_score' => $this->isBothTeamsScoreStillValid($homeGoals, $awayGoals, $timeElapsed),
                
                // Over 0.5 1T: inválido si ya terminó el primer tiempo o ya hay goles
                'over_0_5_first_half' => $this->isOver05FirstHalfStillValid($homeGoals, $awayGoals, $timeElapsed),
                
                default => $timeElapsed < 80 // Otras apuestas válidas hasta minuto 80
            };
        }

        return false;
    }

    /**
     * Verifica si "Ambos Equipos Marcan" aún es válida
     */
    private function isBothTeamsScoreStillValid(int $homeGoals, int $awayGoals, int $timeElapsed): bool
    {
        // Si ambos ya marcaron, la apuesta ya se resolvió
        if ($homeGoals > 0 && $awayGoals > 0) {
            return false;
        }

        // Si es muy tarde en el partido y uno no ha marcado, muy improbable
        if ($timeElapsed >= 85 && ($homeGoals === 0 || $awayGoals === 0)) {
            return false;
        }

        return true;
    }

    /**
     * Verifica si "Over 0.5 First Half" aún es válida
     */
    private function isOver05FirstHalfStillValid(int $homeGoals, int $awayGoals, int $timeElapsed): bool
    {
        // Si ya terminó el primer tiempo (45+ minutos)
        if ($timeElapsed >= 45) {
            return false;
        }

        // Si ya hay goles en el primer tiempo, la apuesta ya se resolvió
        // Nota: Necesitaríamos datos específicos del primer tiempo para ser más precisos
        // Por ahora asumimos que si hay goles y aún no llegamos al minuto 45, puede ser válida
        if ($homeGoals + $awayGoals > 0 && $timeElapsed < 45) {
            // Si ya hay goles y estamos en primer tiempo, la apuesta Over 0.5 1T ya ganó
            return false;
        }

        return true;
    }

    /**
     * Calcula la probabilidad de Over 1.5 goles basada en las predicciones existentes
     */
    private function calculateOver15Probability(FootballMatch $match): float
    {
        if (!$match->prediction) return 0;
        
        $homeGoals = $match->prediction->home_goals_prediction ?? 1.2;
        $awayGoals = $match->prediction->away_goals_prediction ?? 1.0;
        $totalExpected = $homeGoals + $awayGoals;
        
        // Si esperamos más de 1.5 goles, alta probabilidad
        if ($totalExpected >= 2.2) return 0.85;
        if ($totalExpected >= 1.8) return 0.70;
        if ($totalExpected >= 1.5) return 0.60;
        
        return 0.45; // Probabilidad base para partidos con pocas expectativas de gol
    }

    /**
     * Calcula la probabilidad de Under 1.5 goles
     */
    private function calculateUnder15Probability(FootballMatch $match): float
    {
        return 1 - $this->calculateOver15Probability($match);
    }

    /**
     * Calcula la probabilidad de Over 0.5 goles (casi siempre alta)
     */
    private function calculateOver05Probability(FootballMatch $match): float
    {
        if (!$match->prediction) return 0.90; // Por defecto alta probabilidad
        
        $homeGoals = $match->prediction->home_goals_prediction ?? 1.2;
        $awayGoals = $match->prediction->away_goals_prediction ?? 1.0;
        $totalExpected = $homeGoals + $awayGoals;
        
        // Over 0.5 es muy probable en la mayoría de partidos
        if ($totalExpected >= 1.5) return 0.95;
        if ($totalExpected >= 1.0) return 0.88;
        if ($totalExpected >= 0.8) return 0.75;
        
        return 0.65; // Incluso partidos defensivos suelen tener al menos 1 gol
    }

    /**
     * Calcula la probabilidad de Under 0.5 goles (muy baja normalmente)
     */
    private function calculateUnder05Probability(FootballMatch $match): float
    {
        return 1 - $this->calculateOver05Probability($match);
    }
    
    /**
     * Mapea ligas a países para mostrar información geográfica
     */
    private function getCountryFromLeague(?string $league): string
    {
        if (!$league) return '';
        
        $leagueCountryMap = [
            // España
            'Primera Division' => 'España',
            'La Liga' => 'España',
            'Segunda Division' => 'España',
            'Segunda División' => 'España',
            'Primera Federación' => 'España',
            'Segunda Federación' => 'España',
            'Tercera Federación' => 'España',
            'Copa del Rey' => 'España',
            
            // Inglaterra  
            'Premier League' => 'Inglaterra',
            'Championship' => 'Inglaterra',
            'League One' => 'Inglaterra',
            'League Two' => 'Inglaterra',
            'Non League Premier' => 'Inglaterra',
            'National League' => 'Inglaterra',
            'National League North' => 'Inglaterra',
            'National League South' => 'Inglaterra',
            'Northern Premier League' => 'Inglaterra',
            'Southern League Premier Division' => 'Inglaterra',
            'Isthmian League Premier Division' => 'Inglaterra',
            
            // Italia
            'Serie A' => 'Italia',
            'Serie B' => 'Italia',
            'Serie C' => 'Italia',
            'Serie D' => 'Italia',
            'Coppa Italia' => 'Italia',
            '3. Division' => 'Italia',
            '4. Division' => 'Italia',
            
            // Alemania
            'Bundesliga' => 'Alemania',
            '2. Bundesliga' => 'Alemania',
            'Regionalliga' => 'Alemania',
            'Oberliga' => 'Alemania',
            'U19 Bundesliga' => 'Alemania',
            
            // Francia
            'Ligue 1' => 'Francia',
            'Ligue 2' => 'Francia',
            'National 1' => 'Francia',
            
            // Portugal
            'Primeira Liga' => 'Portugal',
            'Liga Portugal' => 'Portugal',
            'II Liga' => 'Portugal',
            'Liga Pro' => 'Portugal',
            'Campeonato de Portugal' => 'Portugal',
            
            // Holanda
            'Eredivisie' => 'Holanda',
            'Eerste Divisie' => 'Holanda',
            
            // Argentina
            'Liga Profesional Argentina' => 'Argentina',
            'Liga Profesional' => 'Argentina',
            'Primera División' => 'Argentina',
            'Primera Nacional' => 'Argentina',
            'Primera B' => 'Argentina',
            'Primera C' => 'Argentina',
            'Copa Argentina' => 'Argentina',
            'Torneo Federal A' => 'Argentina',
            
            // Brasil
            'Brasileirao Serie A' => 'Brasil',
            'Serie A Brazil' => 'Brasil',
            'Liga Pro Serie B' => 'Brasil',
            'Brasileiro U17' => 'Brasil',
            'Brasileiro U20 A' => 'Brasil',
            'Brasileiro Women' => 'Brasil',
            'Copa Do Brasil' => 'Brasil',
            'Copa Paulista' => 'Brasil',
            'Paulista' => 'Brasil',
            'Carioca' => 'Brasil',
            'Mineiro' => 'Brasil',
            'Gaúcho' => 'Brasil',
            'Baiano' => 'Brasil',
            'Catarinense' => 'Brasil',
            'Cearense' => 'Brasil',
            'Goiano' => 'Brasil',
            'Matogrossense' => 'Brasil',
            'Paranaense' => 'Brasil',
            'Paraibano' => 'Brasil',
            'Potiguar' => 'Brasil',
            'Capixaba' => 'Brasil',
            'Brasiliense' => 'Brasil',
            
            // México
            'Liga MX' => 'México',
            'Liga MX Femenil' => 'México',
            'Liga de Expansión MX' => 'México',
            
            // Colombia
            'Liga BetPlay' => 'Colombia',
            'Primera A' => 'Colombia',
            'Copa Colombia' => 'Colombia',
            
            // Chile
            'Primera Division Chile' => 'Chile',
            
            // Uruguay
            'Primera Division Uruguay' => 'Uruguay',
            'Copa Uruguay' => 'Uruguay',
            
            // Estados Unidos
            'Major League Soccer' => 'Estados Unidos',
            'MLS Next Pro' => 'Estados Unidos',
            'USL Championship' => 'Estados Unidos',
            'USL League One' => 'Estados Unidos',
            'USL League Two' => 'Estados Unidos',
            
            // Canadá
            'Canadian Premier League' => 'Canadá',
            'Canadian Soccer League' => 'Canadá',
            
            // Australia
            'Brisbane Premier League' => 'Australia',
            'Capital Territory NPL' => 'Australia',
            'New South Wales NPL' => 'Australia',
            'Northern NSW NPL' => 'Australia',
            'Queensland NPL' => 'Australia',
            'Queensland Premier League' => 'Australia',
            'South Australia NPL' => 'Australia',
            'Tasmania NPL' => 'Australia',
            'Victoria NPL' => 'Australia',
            'Western Australia NPL' => 'Australia',
            'Northern Territory Premier League' => 'Australia',
            
            // Países Bálticos
            '1 Lyga' => 'Lituania',
            'A Lyga' => 'Lituania',
            'Optibet Liga' => 'Letonia',
            'Meistriliiga' => 'Estonia',
            'Esiliiga' => 'Estonia',
            
            // Nórdicos
            '1. Deild' => 'Islandia',
            'Úrvalsdeild' => 'Islandia',
            'Meistaradeildin' => 'Islas Feroe',
            '1. Division' => 'Dinamarca',
            'DBU Pokalen' => 'Dinamarca',
            'Allsvenskan' => 'Suecia',
            'Superettan' => 'Suecia',
            'Division 2' => 'Suecia',
            'Ettan' => 'Suecia',
            'Svenska Cupen' => 'Suecia',
            'Damallsvenskan' => 'Suecia',
            'Elitettan' => 'Suecia',
            'Eliteserien' => 'Noruega',
            'Toppserien' => 'Noruega',
            'Veikkausliiga' => 'Finlandia',
            'Ykkönen' => 'Finlandia',
            'Kakkonen' => 'Finlandia',
            'Kansallinen Liiga' => 'Finlandia',
            
            // Europa del Este
            'Premier Liga' => 'Rusia',
            'FNL' => 'Rusia',
            'Ekstraklasa' => 'Polonia',
            'Ekstraliga Women' => 'Polonia',
            'I Liga' => 'Polonia',
            'II Liga' => 'Polonia',
            '1. Lig' => 'Turquía',
            'Süper Lig' => 'Turquía',
            '1. Liga' => 'Austria',
            '2. Liga' => 'Austria',
            'Regionalliga' => 'Austria',
            'Czech Liga' => 'República Checa',
            'Druha Liga' => 'República Checa',
            'Fortuna Liga' => 'Eslovaquia',
            'NB I' => 'Hungría',
            'NB II' => 'Hungría',
            'NB III' => 'Hungría',
            'Magyar Kupa' => 'Hungría',
            'Liga I' => 'Rumania',
            'Liga II' => 'Rumania',
            'Cupa României' => 'Rumania',
            'Persha Liga' => 'Ucrania',
            'Vysshaya Liga' => 'Bielorrusia',
            
            // Otros europeos
            'Pro League' => 'Bélgica',
            'Challenger Pro League' => 'Bélgica',
            'Jupiler Pro League' => 'Bélgica',
            'Super League' => 'Grecia',
            'Challenge League' => 'Suiza',
            'Erovnuli Liga' => 'Georgia',
            'Primera Liga' => 'Serbia',
            'Prva Liga' => 'Serbia',
            'HNL' => 'Croacia',
            'Superliga' => 'Serbia',
            'Premijer Liga' => 'Bosnia',
            '1. SNL' => 'Eslovenia',
            '2. SNL' => 'Eslovenia',
            'Virsliga' => 'Letonia',
            'National Division' => 'Luxemburgo',
            'Scottish Premiership' => 'Escocia',
            'Scottish Championship' => 'Escocia',
            'Scottish League One' => 'Escocia',
            'Scottish League Two' => 'Escocia',
            'Premier Division' => 'Irlanda',
            'First Division' => 'Irlanda',
            
            // Países que faltaban
            'Cymru Premier' => 'Gales',
            'Welsh Premier League' => 'Gales',
            'Cymru South' => 'Gales',
            'Cymru North' => 'Gales',
            'NIFL Premiership' => 'Irlanda del Norte',
            'Championship (IRL)' => 'Irlanda del Norte',
            'Superliga e Kosovës' => 'Kosovo',
            'Liga e Parë' => 'Kosovo',
            'Kategoria Superiore' => 'Albania',
            'Kategoria e Parë' => 'Albania',
            'BGL Ligue' => 'Luxemburgo',
            'División de Honor' => 'Luxemburgo',
            
            // Asia
            'J1 League' => 'Japón',
            'J2 League' => 'Japón',
            'J3 League' => 'Japón',
            'Japan Football League' => 'Japón',
            'Emperor Cup' => 'Japón',
            'WE League' => 'Japón',
            'K League 1' => 'Corea del Sur',
            'K League 2' => 'Corea del Sur',
            'K3 League' => 'Corea del Sur',
            
            // Ligas que estaban sin mapeo
            '1st Division' => 'Sudáfrica',
            '1st League - FBiH' => 'Bosnia y Herzegovina',
            '1st League - RS' => 'Bosnia y Herzegovina', 
            '2. Deild' => 'Islandia',
            '2. Lig' => 'Turquía',
            '3. Liga' => 'Eslovaquia',
            '3. liga - CFL A' => 'República Checa',
            '3. liga - CFL B' => 'República Checa', 
            '3. liga - Center' => 'República Checa',
            '3. liga - East' => 'República Checa',
            '3. liga - MSFL' => 'República Checa',
            '3. liga - West' => 'República Checa',
            '4. liga - Divizie A' => 'República Checa',
            '4. liga - Divizie B' => 'República Checa',
            '4. liga - Divizie C' => 'República Checa',
            '4. liga - Divizie D' => 'República Checa',
            '4. liga - Divizie E' => 'República Checa',
            '4. liga - Divizie F' => 'República Checa',
            'Alagoano - 2' => 'Brasil',
            'Alagoano U20' => 'Brasil',
            'All-Island Cup - Women' => 'Irlanda',
            'Bermuda Premier Division' => 'Bermudas',
            'Botola Pro' => 'Marruecos',
            'CAF Champions League' => 'África',
            'CONMEBOL Libertadores' => 'Sudamérica',
            'CONMEBOL Sudamericana' => 'Sudamérica',
            'UEFA Champions League' => 'Europa',
            'UEFA Europa League' => 'Europa',
            'UEFA Conference League' => 'Europa',
            
            // Otros
            'Liga Nacional' => 'Honduras',
            'Liga Panameña de Fútbol' => 'Panamá',
            'Liga Mayor' => 'Ecuador',
            'Copa Ecuador' => 'Ecuador',
            'Liga Femenina' => 'Venezuela',
            'Copa Venezuela' => 'Venezuela',
            'Division Profesional' => 'Paraguay',
            'Copa de la División Profesional' => 'Paraguay',
            'Liga 1' => 'Perú',
            'Liga 3' => 'Perú',
            'Kvindeliga' => 'Dinamarca',
            'NWSL Women' => 'Estados Unidos',
            'Ykkösliiga' => 'Finlandia',
            'Supreme Division Women' => 'Inglaterra',
            'Premiership Women' => 'Escocia',
            'Ýokary Liga' => 'Turkmenistán'
        ];
        
        // Buscar coincidencia exacta
        if (isset($leagueCountryMap[$league])) {
            return $leagueCountryMap[$league];
        }
        
        // Buscar coincidencia parcial (más específica primero)
        foreach ($leagueCountryMap as $leaguePattern => $country) {
            if (stripos($league, $leaguePattern) !== false) {
                return $country;
            }
        }
        
        // Buscar patrones específicos para casos especiales (orden importa)
        $specialPatterns = [
            // Patrones específicos primero para evitar conflictos
            '/Welsh.*Premier/i' => 'Gales',
            '/Scottish.*Premier/i' => 'Escocia',
            '/Scottish.*Championship/i' => 'Escocia',
            '/Northern.*Irish/i' => 'Irlanda del Norte',
            '/NIFL/i' => 'Irlanda del Norte',
            '/Liga.*MX/i' => 'México',
            '/Primeira.*Liga/i' => 'Portugal',
            '/La.*Liga/i' => 'España',
            
            // Patrones por división/sistema numérico
            '/^3\.\s*Division.*Girone/i' => 'Italia',
            '/^4\.\s*Division/i' => 'Italia', 
            '/^3\.\s*Division/i' => 'Italia',
            '/^2\.\s*Division/i' => 'Inglaterra',
            '/^1\.\s*Division/i' => 'Dinamarca',
            '/Liga\s*[0-9]/i' => 'Austria',
            
            // Patrones generales
            '/Bundesliga/i' => 'Alemania',
            '/Serie\s*[A-D]/i' => 'Italia',
            '/Ligue\s*[12]/i' => 'Francia',
            '/Eredivisie/i' => 'Holanda',
            '/Premier.*League/i' => 'Inglaterra',
            
            // Patrones regionales brasileños
            '/Carioca|Paulista|Mineiro|Gaúcho|Baiano/i' => 'Brasil',
            '/Campeonato.*Brasil/i' => 'Brasil',
            '/Copa.*Brasil/i' => 'Brasil'
        ];
        
        foreach ($specialPatterns as $pattern => $country) {
            if (preg_match($pattern, $league)) {
                return $country;
            }
        }
        
        return ''; // Si no se encuentra, no mostrar país
    }

    /**
     * Determine if "Both Teams Score" prediction meets confidence threshold
     */
    private function isBothTeamsScoreConfident($prediction, $minConfidence): bool
    {
        if (!$prediction || is_null($prediction->both_teams_score_probability)) {
            return false;
        }
        
        $bothTeamsScoreProb = $prediction->both_teams_score_probability * 100;
        
        // Consider confident if probability is very high (>= minConfidence%) or very low (<= 100-minConfidence%)
        // High confidence for "Yes": >= minConfidence%
        // High confidence for "No": <= (100 - minConfidence)%
        return ($bothTeamsScoreProb >= $minConfidence) || ($bothTeamsScoreProb <= (100 - $minConfidence));
    }
}