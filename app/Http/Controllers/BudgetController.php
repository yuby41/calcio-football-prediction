<?php

namespace App\Http\Controllers;

use App\Models\BudgetConfiguration;
use App\Models\Bet;
use App\Models\BudgetHistory;
use App\Models\BettingStrategy;
use App\Models\FootballMatch;
use App\Services\BettingStrategyService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class BudgetController extends Controller
{
    protected BettingStrategyService $bettingService;

    public function __construct(BettingStrategyService $bettingService)
    {
        $this->bettingService = $bettingService;
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
        $budget->load(['bets.match.homeTeam', 'bets.match.awayTeam', 'budgetHistory']);
        
        $performance = $this->bettingService->getStrategyPerformance($budget, 30);
        
        // Datos para gráficos
        $chartData = $this->getChartData($budget);
        
        // Próximas oportunidades de apuesta
        $opportunities = $this->getBettingOpportunities($budget);

        return view('budget.show', compact('budget', 'performance', 'chartData', 'opportunities'));
    }

    public function placeBet(Request $request, BudgetConfiguration $budget)
    {
        $validated = $request->validate([
            'match_id' => 'required|exists:matches,id',
            'bet_type' => 'required|in:home_win,away_win,draw,over_2_5,under_2_5,both_teams_score',
            'odds' => 'required|numeric|min:1.01|max:50',
            'amount' => 'nullable|numeric|min:0.01',
        ]);

        $match = FootballMatch::with('prediction')->findOrFail($validated['match_id']);
        
        if (!$match->prediction) {
            return back()->withErrors(['match_id' => 'Este partido no tiene predicción disponible.']);
        }

        // Obtener confianza de la predicción
        $confidence = $this->getConfidenceForBetType($match, $validated['bet_type']);
        
        if ($confidence < $budget->min_confidence) {
            return back()->withErrors(['bet_type' => "La confianza ({$confidence}%) es menor al mínimo requerido ({$budget->min_confidence}%).)"]);
        }

        // Calcular cantidad recomendada si no se especifica
        $amount = $validated['amount'] ?? $this->bettingService->calculateBetAmount(
            $budget, 
            $match, 
            $validated['bet_type'], 
            $confidence
        );

        if ($amount > $budget->current_budget) {
            return back()->withErrors(['amount' => 'Fondos insuficientes.']);
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

        return back()->with('success', "Apuesta realizada: €{$amount} en {$bet->getBetTypeDisplayAttribute()}");
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
        return response()->json($this->getBettingOpportunities($budget));
    }

    private function getChartData(BudgetConfiguration $budget): array
    {
        // Evolución del budget
        $history = $budget->budgetHistory()
            ->orderBy('created_at')
            ->get()
            ->groupBy(function($item) {
                return $item->created_at->format('Y-m-d');
            });

        $budgetEvolution = [];
        foreach ($history as $date => $records) {
            $budgetEvolution[] = [
                'date' => $date,
                'balance' => (float) $records->last()->balance_after,
            ];
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
            'budget_evolution' => $budgetEvolution,
            'bet_types_distribution' => $betTypes,
            'monthly_performance' => $monthlyPerformance,
        ];
    }

    private function getBettingOpportunities(BudgetConfiguration $budget): array
    {
        $upcomingMatches = FootballMatch::with(['homeTeam', 'awayTeam', 'prediction'])
            ->where('status', 'scheduled')
            ->where('match_date', '>=', now())
            ->where('match_date', '<=', now()->addDays(7))
            ->whereHas('prediction')
            ->get();

        $opportunities = [];

        foreach ($upcomingMatches as $match) {
            if (!$match->prediction) continue;

            $betTypes = ['home_win', 'away_win', 'draw', 'over_2_5', 'both_teams_score'];
            
            foreach ($betTypes as $betType) {
                $confidence = $this->getConfidenceForBetType($match, $betType);
                
                if ($confidence >= $budget->min_confidence) {
                    $recommendedAmount = $this->bettingService->calculateBetAmount(
                        $budget, 
                        $match, 
                        $betType, 
                        $confidence
                    );
                    
                    $odds = $this->bettingService->getRecommendedOdds($betType, $match);
                    
                    $opportunities[] = [
                        'match_id' => $match->id,
                        'match_name' => "{$match->homeTeam->name} vs {$match->awayTeam->name}",
                        'match_date' => $match->match_date->format('d/m/Y H:i'),
                        'bet_type' => $betType,
                        'bet_type_display' => (new Bet(['bet_type' => $betType]))->getBetTypeDisplayAttribute(),
                        'confidence' => $confidence,
                        'recommended_amount' => $recommendedAmount,
                        'odds' => $odds,
                        'potential_profit' => ($recommendedAmount * $odds) - $recommendedAmount,
                    ];
                }
            }
        }

        // Ordenar por confianza descendente
        usort($opportunities, function($a, $b) {
            return $b['confidence'] <=> $a['confidence'];
        });

        return array_slice($opportunities, 0, 20); // Top 20 oportunidades
    }

    private function getConfidenceForBetType(FootballMatch $match, string $betType): float
    {
        if (!$match->prediction) return 0;

        return match($betType) {
            'home_win' => $match->prediction->home_win_probability ?? 0,
            'away_win' => $match->prediction->away_win_probability ?? 0,
            'draw' => $match->prediction->draw_probability ?? 0,
            'over_2_5' => $match->prediction->over_2_5_probability ?? 0,
            'under_2_5' => $match->prediction->under_2_5_probability ?? 0,
            'both_teams_score' => $match->prediction->both_teams_score_probability ?? 0,
            default => 0,
        };
    }
}