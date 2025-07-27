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

}