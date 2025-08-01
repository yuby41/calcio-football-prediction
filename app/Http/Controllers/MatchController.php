<?php

namespace App\Http\Controllers;

use App\Models\FootballMatch;
use App\Models\Team;
use Carbon\Carbon;
use Illuminate\Http\Request;

class MatchController extends Controller
{
    public function index(Request $request)
    {
        $query = FootballMatch::with(['homeTeam', 'awayTeam', 'prediction']);

        // Default to today's matches if no date filters are applied
        $hasDateFilters = $request->has('date_from') || $request->has('date_to');
        if (!$hasDateFilters) {
            // Show matches from today and nearby dates (yesterday to tomorrow)
            $query->whereDate('match_date', '>=', Carbon::today()->subDay())
                  ->whereDate('match_date', '<=', Carbon::today()->addDay());
        }

        // Filter by status
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Filter by date range
        if ($request->has('date_from') && $request->date_from) {
            $query->whereDate('match_date', '>=', $request->date_from);
        }

        if ($request->has('date_to') && $request->date_to) {
            $query->whereDate('match_date', '<=', $request->date_to);
        }

        // Filter by team
        if ($request->has('team') && $request->team) {
            $query->where(function ($q) use ($request) {
                $q->where('home_team_id', $request->team)
                  ->orWhere('away_team_id', $request->team);
            });
        }

        $matches = $query->orderBy('match_date', 'asc')->paginate(20);
        $teams = Team::where('is_active', true)->orderBy('name')->get();

        return view('matches.index', compact('matches', 'teams'));
    }

    public function show(FootballMatch $footballMatch)
    {
        $match = $footballMatch;
        $match->load(['homeTeam', 'awayTeam', 'prediction']);

        // Get recent head-to-head matches
        $headToHead = FootballMatch::where('status', 'finished')
            ->where(function ($query) use ($match) {
                $query->where([
                    'home_team_id' => $match->home_team_id,
                    'away_team_id' => $match->away_team_id
                ])->orWhere([
                    'home_team_id' => $match->away_team_id,
                    'away_team_id' => $match->home_team_id
                ]);
            })
            ->where('id', '!=', $match->id)
            ->with(['homeTeam', 'awayTeam'])
            ->orderBy('match_date', 'desc')
            ->limit(5)
            ->get();

        // Get recent matches for each team
        $homeTeamMatches = FootballMatch::where('status', 'finished')
            ->where(function ($query) use ($match) {
                $query->where('home_team_id', $match->home_team_id)
                      ->orWhere('away_team_id', $match->home_team_id);
            })
            ->where('id', '!=', $match->id)
            ->with(['homeTeam', 'awayTeam'])
            ->orderBy('match_date', 'desc')
            ->limit(5)
            ->get();

        $awayTeamMatches = FootballMatch::where('status', 'finished')
            ->where(function ($query) use ($match) {
                $query->where('home_team_id', $match->away_team_id)
                      ->orWhere('away_team_id', $match->away_team_id);
            })
            ->where('id', '!=', $match->id)
            ->with(['homeTeam', 'awayTeam'])
            ->orderBy('match_date', 'desc')
            ->limit(5)
            ->get();

        return view('matches.show', compact(
            'match', 
            'headToHead', 
            'homeTeamMatches', 
            'awayTeamMatches'
        ));
    }

    public function getFilteredMatches(Request $request)
    {
        // Update match statuses based on time before retrieving data
        $this->updateMatchStatuses();

        $query = FootballMatch::with(['homeTeam', 'awayTeam', 'prediction']);

        // Default to today's matches if no date filters are applied
        $hasDateFilters = $request->has('date_from') || $request->has('date_to');
        if (!$hasDateFilters) {
            // Show matches from today and nearby dates (yesterday to tomorrow)
            $query->whereDate('match_date', '>=', Carbon::today()->subDay())
                  ->whereDate('match_date', '<=', Carbon::today()->addDay());
        }

        // Filter by status
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Filter by date range
        if ($request->has('date_from') && $request->date_from) {
            $query->whereDate('match_date', '>=', $request->date_from);
        }

        if ($request->has('date_to') && $request->date_to) {
            $query->whereDate('match_date', '<=', $request->date_to);
        }

        // Filter by team
        if ($request->has('team') && $request->team) {
            $query->where(function ($q) use ($request) {
                $q->where('home_team_id', $request->team)
                  ->orWhere('away_team_id', $request->team);
            });
        }

        $matches = $query->orderBy('match_date', 'asc')->take(100)->get();

        return response()->json([
            'matches' => $matches,
            'total' => $matches->count()
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