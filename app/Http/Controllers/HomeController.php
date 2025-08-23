<?php

namespace App\Http\Controllers;

use App\Models\FootballMatch;
use App\Models\Team;
use App\Models\MatchPrediction;
use App\Services\SimpleAccuracyService;
use App\Services\CacheService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class HomeController extends Controller
{
    public function index(Request $request)
    {
        $page = $request->get('page', 1);
        $perPage = 25;

        // Get live matches (in progress) with pagination and optimized fields
        $liveMatches = FootballMatch::select('id', 'match_date', 'home_team_id', 'away_team_id', 'status', 'league', 'home_goals', 'away_goals')
            ->with([
                'homeTeam:id,name,country',
                'awayTeam:id,name,country',
                'prediction:id,match_id,predicted_outcome,confidence_score,both_teams_score_probability,over_2_5_probability,home_win_probability,draw_probability,away_win_probability'
            ])
            ->where('status', 'live')
            ->orderBy('match_date')
            ->paginate($perPage, ['*'], 'live_page');

        // Get today's scheduled matches with pagination and optimized fields
        $todayMatches = FootballMatch::select('id', 'match_date', 'home_team_id', 'away_team_id', 'status', 'league')
            ->with([
                'homeTeam:id,name,country',
                'awayTeam:id,name,country',
                'prediction:id,match_id,predicted_outcome,confidence_score,both_teams_score_probability,over_2_5_probability,home_win_probability,draw_probability,away_win_probability'
            ])
            ->whereDate('match_date', Carbon::today())
            ->whereIn('status', ['scheduled', 'incomplete'])
            ->orderBy('match_date')
            ->paginate($perPage, ['*'], 'today_page');

        // Get upcoming scheduled matches (next 30 days, excluding today) with pagination and optimized fields
        $upcomingMatches = FootballMatch::select('id', 'match_date', 'home_team_id', 'away_team_id', 'status', 'league')
            ->with([
                'homeTeam:id,name,country',
                'awayTeam:id,name,country',
                'prediction:id,match_id,predicted_outcome,confidence_score,both_teams_score_probability,over_2_5_probability,home_win_probability,draw_probability,away_win_probability'
            ])
            ->whereIn('status', ['scheduled', 'incomplete'])
            ->whereBetween('match_date', [
                Carbon::tomorrow(),
                Carbon::now()->addDays(30)
            ])
            ->orderBy('match_date')
            ->paginate($perPage, ['*'], 'upcoming_page');

        // Calculate prediction accuracy with caching
        $cacheService = app(CacheService::class);
        $accuracy = $cacheService->rememberStatistics('current_accuracy', function () {
            return SimpleAccuracyService::getCurrentAccuracy();
        });

        return view('home', compact(
            'liveMatches',
            'todayMatches',
            'upcomingMatches',
            'accuracy'
        ));
    }

    public function manualUpdate()
    {
        try {
            // Run the update command
            Artisan::call('football:update-today', ['--silent' => true]);
            
            return redirect()->route('home')->with('success', 'Actualización manual completada exitosamente.');
        } catch (\Exception $e) {
            return redirect()->route('home')->with('error', 'Error durante la actualización: ' . $e->getMessage());
        }
    }

    public function getLiveData()
    {
        // Update match statuses based on time before retrieving data
        $this->updateMatchStatuses();

        // Get live matches with optimized query and reasonable limits
        $liveMatches = FootballMatch::select('id', 'match_date', 'home_team_id', 'away_team_id', 'status', 'league', 'home_goals', 'away_goals')
            ->with([
                'homeTeam:id,name,country',
                'awayTeam:id,name,country',
                'prediction:id,match_id,predicted_outcome,confidence_score,both_teams_score_probability,over_2_5_probability,home_win_probability,draw_probability,away_win_probability'
            ])
            ->where('status', 'live')
            ->orderBy('match_date')
            ->limit(50) // Reasonable limit for live matches
            ->get();

        // Get today's scheduled matches with optimized query and limits
        $todayMatches = FootballMatch::select('id', 'match_date', 'home_team_id', 'away_team_id', 'status', 'league')
            ->with([
                'homeTeam:id,name,country',
                'awayTeam:id,name,country',
                'prediction:id,match_id,predicted_outcome,confidence_score,both_teams_score_probability,over_2_5_probability,home_win_probability,draw_probability,away_win_probability'
            ])
            ->whereDate('match_date', Carbon::today())
            ->whereIn('status', ['scheduled', 'incomplete'])
            ->orderBy('match_date')
            ->limit(100) // Reasonable limit for today's matches
            ->get();

        // Get upcoming matches (next 30 days, excluding today) with optimized query and limits
        $upcomingMatches = FootballMatch::select('id', 'match_date', 'home_team_id', 'away_team_id', 'status', 'league')
            ->with([
                'homeTeam:id,name,country',
                'awayTeam:id,name,country',
                'prediction:id,match_id,predicted_outcome,confidence_score,both_teams_score_probability,over_2_5_probability,home_win_probability,draw_probability,away_win_probability'
            ])
            ->whereIn('status', ['scheduled', 'incomplete'])
            ->whereBetween('match_date', [
                Carbon::tomorrow(),
                Carbon::now()->addDays(30)
            ])
            ->orderBy('match_date')
            ->limit(50) // Increased limit for upcoming matches
            ->get();

        // Calculate current accuracy
        $accuracy = SimpleAccuracyService::getCurrentAccuracy();

        // Calculate real counters (not limited by pagination/limits)
        $realCounters = [
            'live' => FootballMatch::where('status', 'live')->count(),
            'today' => FootballMatch::whereDate('match_date', Carbon::today())
                ->whereIn('status', ['scheduled', 'incomplete'])->count(),
            'upcoming' => FootballMatch::whereIn('status', ['scheduled', 'incomplete'])
                ->whereBetween('match_date', [Carbon::tomorrow(), Carbon::now()->addDays(30)])->count()
        ];

        return response()->json([
            'liveMatches' => $liveMatches,
            'todayMatches' => $todayMatches,
            'upcomingMatches' => $upcomingMatches,
            'accuracy' => $accuracy,
            'counters' => $realCounters
        ]);
    }

    private function updateMatchStatuses()
    {
        $now = Carbon::now();

        // Update scheduled matches to live if they started (within last 2 hours)
        FootballMatch::where('status', 'scheduled')
            ->where('match_date', '<=', $now)
            ->where('match_date', '>=', $now->copy()->subHours(2))
            ->update(['status' => 'live']);

        // Update live matches to finished if they ended (2 hours after start time)
        FootballMatch::where('status', 'live')
            ->where('match_date', '<=', $now->copy()->subHours(2))
            ->update(['status' => 'finished']);
    }

    public function debugMatch($matchId)
    {
        $match = FootballMatch::with([
            'homeTeam:id,name,country',
            'awayTeam:id,name,country',
            'prediction:id,match_id,predicted_outcome,confidence_score,both_teams_score_probability,over_2_5_probability,home_win_probability,draw_probability,away_win_probability'
        ])->find($matchId);

        if (!$match) {
            return response()->json(['error' => 'Match not found']);
        }

        return response()->json([
            'match_id' => $match->id,
            'teams' => $match->homeTeam->name . ' vs ' . $match->awayTeam->name,
            'status' => $match->status,
            'prediction' => $match->prediction ? $match->prediction->toArray() : null,
            'calculated_percentages' => $match->prediction ? [
                'home_win' => number_format($match->prediction->home_win_probability * 100, 1) . '%',
                'draw' => number_format($match->prediction->draw_probability * 100, 1) . '%',
                'away_win' => number_format($match->prediction->away_win_probability * 100, 1) . '%'
            ] : null
        ]);
    }

}