<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FootballMatch;
use App\Services\SimpleAccuracyService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class LiveUpdatesController extends Controller
{
    /**
     * Get live match updates
     */
    public function matches(Request $request): JsonResponse
    {
        $lastUpdate = $request->get('last_update');
        $lastUpdateTime = $lastUpdate ? Carbon::parse($lastUpdate) : Carbon::now()->subMinutes(5);

        // Get matches that have been updated since last check
        $matches = FootballMatch::with(['homeTeam', 'awayTeam', 'prediction'])
            ->where(function($query) use ($lastUpdateTime) {
                $query->where('updated_at', '>', $lastUpdateTime)
                      ->orWhere('status', 'live')
                      ->orWhere(function($q) use ($lastUpdateTime) {
                          $q->where('status', 'scheduled')
                            ->where('match_date', '>=', Carbon::now())
                            ->where('match_date', '<=', Carbon::now()->addHours(2));
                      });
            })
            ->orderBy('match_date')
            ->get()
            ->map(function($match) {
                return [
                    'id' => $match->id,
                    'home_team' => [
                        'id' => $match->homeTeam->id,
                        'name' => $match->homeTeam->name,
                        'logo' => $match->homeTeam->logo_url,
                    ],
                    'away_team' => [
                        'id' => $match->awayTeam->id,
                        'name' => $match->awayTeam->name,
                        'logo' => $match->awayTeam->logo_url,
                    ],
                    'home_goals' => $match->home_goals,
                    'away_goals' => $match->away_goals,
                    'status' => $match->status,
                    'minute' => $match->minute,
                    'match_date' => $match->match_date->toISOString(),
                    'league' => $match->league,
                    'prediction' => $match->prediction ? [
                        'predicted_outcome' => $match->prediction->predicted_outcome,
                        'confidence' => $match->prediction->confidence,
                        'home_win_probability' => $match->prediction->home_win_probability,
                        'draw_probability' => $match->prediction->draw_probability,
                        'away_win_probability' => $match->prediction->away_win_probability,
                        'is_correct' => $match->prediction->is_correct,
                    ] : null,
                    'updated_at' => $match->updated_at->toISOString(),
                ];
            });

        return response()->json([
            'matches' => $matches,
            'timestamp' => Carbon::now()->toISOString(),
            'next_update' => Carbon::now()->addSeconds(30)->toISOString(), // Suggest next check in 30s
        ]);
    }

    /**
     * Get current statistics
     */
    public function statistics(): JsonResponse
    {
        $accuracy = SimpleAccuracyService::getCurrentAccuracy();
        
        // Get additional stats
        $totalMatches = FootballMatch::whereNotNull('home_goals')
            ->whereNotNull('away_goals')
            ->where('status', 'finished')
            ->count();

        $liveMatches = FootballMatch::where('status', 'live')->count();
        $scheduledToday = FootballMatch::whereDate('match_date', Carbon::today())
            ->where('status', 'scheduled')
            ->count();

        return response()->json([
            'accuracy' => $accuracy,
            'total_finished_matches' => $totalMatches,
            'live_matches' => $liveMatches,
            'scheduled_today' => $scheduledToday,
            'timestamp' => Carbon::now()->toISOString(),
        ]);
    }

    /**
     * Get live matches only
     */
    public function liveMatches(): JsonResponse
    {
        $liveMatches = FootballMatch::with(['homeTeam', 'awayTeam', 'prediction'])
            ->where('status', 'live')
            ->orderBy('match_date')
            ->get()
            ->map(function($match) {
                return [
                    'id' => $match->id,
                    'home_team' => $match->homeTeam->name,
                    'away_team' => $match->awayTeam->name,
                    'home_goals' => $match->home_goals,
                    'away_goals' => $match->away_goals,
                    'minute' => $match->minute,
                    'prediction' => $match->prediction ? [
                        'predicted_outcome' => $match->prediction->predicted_outcome,
                        'confidence' => $match->prediction->confidence,
                    ] : null,
                ];
            });

        return response()->json([
            'live_matches' => $liveMatches,
            'count' => $liveMatches->count(),
            'timestamp' => Carbon::now()->toISOString(),
        ]);
    }

    /**
     * Health check endpoint
     */
    public function health(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'timestamp' => Carbon::now()->toISOString(),
            'services' => [
                'database' => $this->checkDatabase(),
                'api' => $this->checkApi(),
            ]
        ]);
    }

    private function checkDatabase(): string
    {
        try {
            FootballMatch::count();
            return 'connected';
        } catch (\Exception $e) {
            return 'disconnected';
        }
    }

    private function checkApi(): string
    {
        $lastSync = FootballMatch::latest('updated_at')->first();
        if ($lastSync && $lastSync->updated_at->gt(Carbon::now()->subHours(3))) {
            return 'recent_data';
        }
        return 'stale_data';
    }
}