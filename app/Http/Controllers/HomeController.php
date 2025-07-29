<?php

namespace App\Http\Controllers;

use App\Models\FootballMatch;
use App\Models\Team;
use App\Models\MatchPrediction;
use App\Services\SimpleAccuracyService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class HomeController extends Controller
{
    public function index(Request $request)
    {
        $page = $request->get('page', 1);
        $perPage = 25;

        // Get live matches (in progress) with pagination
        $liveMatches = FootballMatch::with(['homeTeam', 'awayTeam', 'prediction'])
            ->where('status', 'live')
            ->orderBy('match_date')
            ->paginate($perPage, ['*'], 'live_page');

        // Get today's scheduled matches with pagination
        $todayMatches = FootballMatch::with(['homeTeam', 'awayTeam', 'prediction'])
            ->whereDate('match_date', Carbon::today())
            ->where('status', 'scheduled')
            ->orderBy('match_date')
            ->paginate($perPage, ['*'], 'today_page');

        // Get upcoming scheduled matches (next 7 days, excluding today) with pagination
        $upcomingMatches = FootballMatch::with(['homeTeam', 'awayTeam', 'prediction'])
            ->where('status', 'scheduled')
            ->whereBetween('match_date', [
                Carbon::tomorrow(),
                Carbon::now()->addDays(7)
            ])
            ->orderBy('match_date')
            ->paginate($perPage, ['*'], 'upcoming_page');

        // Calculate prediction accuracy from current data
        $accuracy = SimpleAccuracyService::getCurrentAccuracy();

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

        // Get live matches
        $liveMatches = FootballMatch::with(['homeTeam', 'awayTeam', 'prediction'])
            ->where('status', 'live')
            ->orderBy('match_date')
            ->get();

        // Get today's scheduled matches
        $todayMatches = FootballMatch::with(['homeTeam', 'awayTeam', 'prediction'])
            ->whereDate('match_date', Carbon::today())
            ->where('status', 'scheduled')
            ->orderBy('match_date')
            ->get();

        // Get upcoming matches (next 7 days, excluding today)
        $upcomingMatches = FootballMatch::with(['homeTeam', 'awayTeam', 'prediction'])
            ->where('status', 'scheduled')
            ->whereBetween('match_date', [
                Carbon::tomorrow(),
                Carbon::now()->addDays(7)
            ])
            ->orderBy('match_date')
            ->take(25)
            ->get();

        // Calculate current accuracy
        $accuracy = SimpleAccuracyService::getCurrentAccuracy();

        return response()->json([
            'liveMatches' => $liveMatches,
            'todayMatches' => $todayMatches,
            'upcomingMatches' => $upcomingMatches,
            'accuracy' => $accuracy,
            'counters' => [
                'live' => $liveMatches->count(),
                'today' => $todayMatches->count(),
                'upcoming' => $upcomingMatches->count()
            ]
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

}