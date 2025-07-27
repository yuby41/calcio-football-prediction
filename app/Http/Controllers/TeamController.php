<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\FootballMatch;
use App\Models\TeamStatistic;
use Illuminate\Http\Request;

class TeamController extends Controller
{
    public function index()
    {
        $teams = Team::with(['statistics' => function ($query) {
            $query->where('season', '2023')->orWhere('season', date('Y'));
        }])
        ->where('is_active', true)
        ->orderBy('name')
        ->get();

        // Sort teams by points if statistics available
        $teams = $teams->sortByDesc(function ($team) {
            return $team->statistics->first()->points ?? 0;
        });

        return view('teams.index', compact('teams'));
    }

    public function show(Team $team, Request $request)
    {
        $season = $request->get('season', date('Y'));
        
        $team->load(['statistics' => function ($query) use ($season) {
            $query->where('season', $season);
        }]);

        $statistics = $team->statistics->first();

        // Get recent matches
        $recentMatches = FootballMatch::where(function ($query) use ($team) {
            $query->where('home_team_id', $team->id)
                  ->orWhere('away_team_id', $team->id);
        })
        ->where('status', 'finished')
        ->with(['homeTeam', 'awayTeam'])
        ->orderBy('match_date', 'desc')
        ->limit(10)
        ->get();

        // Get upcoming matches
        $upcomingMatches = FootballMatch::where(function ($query) use ($team) {
            $query->where('home_team_id', $team->id)
                  ->orWhere('away_team_id', $team->id);
        })
        ->where('status', 'scheduled')
        ->with(['homeTeam', 'awayTeam', 'prediction'])
        ->orderBy('match_date')
        ->limit(5)
        ->get();

        // Calculate performance metrics
        $homeMatches = $recentMatches->where('home_team_id', $team->id);
        $awayMatches = $recentMatches->where('away_team_id', $team->id);

        $homeStats = $this->calculateLocationStats($homeMatches, $team->id, true);
        $awayStats = $this->calculateLocationStats($awayMatches, $team->id, false);

        return view('teams.show', compact(
            'team', 
            'statistics', 
            'recentMatches', 
            'upcomingMatches',
            'homeStats',
            'awayStats',
            'season'
        ));
    }

    private function calculateLocationStats($matches, $teamId, $isHome)
    {
        $stats = [
            'played' => $matches->count(),
            'wins' => 0,
            'draws' => 0,
            'losses' => 0,
            'goals_for' => 0,
            'goals_against' => 0,
        ];

        foreach ($matches as $match) {
            if ($isHome) {
                $stats['goals_for'] += $match->home_goals;
                $stats['goals_against'] += $match->away_goals;
                
                if ($match->home_goals > $match->away_goals) {
                    $stats['wins']++;
                } elseif ($match->home_goals < $match->away_goals) {
                    $stats['losses']++;
                } else {
                    $stats['draws']++;
                }
            } else {
                $stats['goals_for'] += $match->away_goals;
                $stats['goals_against'] += $match->home_goals;
                
                if ($match->away_goals > $match->home_goals) {
                    $stats['wins']++;
                } elseif ($match->away_goals < $match->home_goals) {
                    $stats['losses']++;
                } else {
                    $stats['draws']++;
                }
            }
        }

        $stats['win_percentage'] = $stats['played'] > 0 ? 
            round(($stats['wins'] / $stats['played']) * 100, 1) : 0;
        $stats['avg_goals_for'] = $stats['played'] > 0 ? 
            round($stats['goals_for'] / $stats['played'], 2) : 0;
        $stats['avg_goals_against'] = $stats['played'] > 0 ? 
            round($stats['goals_against'] / $stats['played'], 2) : 0;

        return $stats;
    }
}