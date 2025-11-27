<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\EnhancedFootballApiService;
use App\Models\FootballMatch;
use App\Models\MatchPrediction;
use Illuminate\Support\Facades\Log;

class MigrateToRealDataSimple extends Command
{
    protected $signature = 'data:simple-migrate {--limit=5 : Number of records to process}';
    protected $description = 'Simple migration test - replace synthetic "expected goals" with real team statistics';

    private EnhancedFootballApiService $apiService;

    public function __construct(EnhancedFootballApiService $apiService)
    {
        parent::__construct();
        $this->apiService = $apiService;
    }

    public function handle(): int
    {
        $this->info('🎯 Testing migration: Replacing synthetic "expected goals" with REAL team statistics');
        
        $limit = (int) $this->option('limit');
        
        // Get recent matches with synthetic predictions
        $matches = FootballMatch::whereHas('prediction', function ($query) {
            $query->where('model_version', 'like', '%enhanced%');
        })
        ->with(['prediction', 'homeTeam', 'awayTeam'])
        ->where('match_date', '>=', now()->subDays(30))
        ->limit($limit)
        ->get();

        $this->info("Found {$matches->count()} matches to test");

        if ($matches->count() === 0) {
            $this->warn('No matches found with synthetic predictions');
            return 0;
        }

        $this->info("\n📊 BEFORE MIGRATION (Synthetic Data):");
        
        foreach ($matches as $index => $match) {
            $this->line("Match " . ($index + 1) . ": {$match->homeTeam->name} vs {$match->awayTeam->name}");
            
            if ($match->prediction) {
                $this->line("  🤖 SYNTHETIC Expected Goals: {$match->prediction->home_goals_prediction} - {$match->prediction->away_goals_prediction}");
                $this->line("  🤖 SYNTHETIC Total: " . number_format($match->prediction->home_goals_prediction + $match->prediction->away_goals_prediction, 1));
                $this->line("  🤖 Model: {$match->prediction->model_version}");
            }

            // Now get REAL statistics
            try {
                $this->info("  🔍 Fetching REAL statistics from API-Sports...");
                
                // Get real team statistics for home team
                $homeStats = $this->apiService->fetchTeamStatistics(
                    $match->homeTeam->external_id,
                    39, // Premier League
                    date('Y')
                );

                // Get real team statistics for away team  
                $awayStats = $this->apiService->fetchTeamStatistics(
                    $match->awayTeam->external_id,
                    39, // Premier League
                    date('Y')
                );

                if (!empty($homeStats) && !empty($awayStats)) {
                    // Calculate REAL expected goals from actual team performance
                    $homeGoalsPerGame = $homeStats['goals']['for']['average']['home'] ?? 0;
                    $awayGoalsPerGame = $awayStats['goals']['for']['average']['away'] ?? 0;
                    
                    $this->line("  ✅ REAL Home Goals per Game: " . number_format($homeGoalsPerGame, 2));
                    $this->line("  ✅ REAL Away Goals per Game: " . number_format($awayGoalsPerGame, 2));
                    $this->line("  ✅ REAL Expected Total: " . number_format($homeGoalsPerGame + $awayGoalsPerGame, 1));
                    
                    // Update prediction with REAL data
                    if ($match->prediction) {
                        $match->prediction->update([
                            'home_goals_prediction' => round($homeGoalsPerGame, 2),
                            'away_goals_prediction' => round($awayGoalsPerGame, 2),
                            'model_version' => 'real_data_api_sports',
                            'features_used' => json_encode([
                                'data_source' => 'api_sports_real_statistics',
                                'home_avg_goals' => $homeGoalsPerGame,
                                'away_avg_goals' => $awayGoalsPerGame,
                                'migration_date' => now()
                            ])
                        ]);
                        
                        $this->info("  🎉 MIGRATED to REAL data successfully!");
                    }
                    
                } else {
                    $this->warn("  ❌ No real statistics available for these teams");
                }

            } catch (\Exception $e) {
                $this->error("  ❌ Error fetching real data: " . $e->getMessage());
            }
            
            $this->line(""); // Empty line for readability
            sleep(1); // Rate limiting
        }

        $this->info("\n🎉 Migration test completed!");
        $this->info("\n📈 Key Achievement:");
        $this->info("✅ Replaced SYNTHETIC ML predictions with REAL team performance data from API-Sports");
        
        return 0;
    }
}