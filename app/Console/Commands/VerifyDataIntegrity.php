<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\FootballMatch;
use App\Models\Team;
use App\Models\TeamStatistic;
use App\Models\MatchPrediction;
use App\Services\PredictionService;

class VerifyDataIntegrity extends Command
{
    protected $signature = 'data:verify-integrity 
                           {--fix : Automatically fix detected issues}
                           {--silent : Run in silent mode with minimal output}';
    
    protected $description = 'Verify data integrity and optionally fix issues automatically';
    
    protected PredictionService $predictionService;
    
    public function __construct(PredictionService $predictionService)
    {
        parent::__construct();
        $this->predictionService = $predictionService;
    }
    
    public function handle(): int
    {
        $silent = $this->option('silent');
        $fix = $this->option('fix');
        
        if (!$silent) {
            $this->info('🔍 Starting data integrity verification...');
        }
        
        $issues = [];
        
        // Check team statistics
        $teamStatsIssues = $this->checkTeamStatistics($fix, $silent);
        if (!empty($teamStatsIssues)) {
            $issues['team_statistics'] = $teamStatsIssues;
        }
        
        // Check missing predictions
        $predictionIssues = $this->checkMissingPredictions($fix, $silent);
        if (!empty($predictionIssues)) {
            $issues['missing_predictions'] = $predictionIssues;
        }
        
        // Check orphaned data
        $orphanedIssues = $this->checkOrphanedData($fix, $silent);
        if (!empty($orphanedIssues)) {
            $issues['orphaned_data'] = $orphanedIssues;
        }
        
        // Check data consistency
        $consistencyIssues = $this->checkDataConsistency($fix, $silent);
        if (!empty($consistencyIssues)) {
            $issues['data_consistency'] = $consistencyIssues;
        }
        
        // Summary
        $totalIssues = array_sum(array_map('count', $issues));
        
        if (!$silent) {
            if ($totalIssues === 0) {
                $this->info('✅ No data integrity issues found!');
            } else {
                $this->warn("⚠️ Found {$totalIssues} data integrity issues:");
                foreach ($issues as $category => $categoryIssues) {
                    $this->line("  {$category}: " . count($categoryIssues) . " issues");
                }
                
                if ($fix) {
                    $this->info('🔧 Issues were automatically fixed where possible.');
                } else {
                    $this->info('💡 Run with --fix to automatically resolve issues.');
                }
            }
        }
        
        // Log results
        $this->logIntegrityCheck($issues, $fix);
        
        return $totalIssues === 0 ? Command::SUCCESS : Command::FAILURE;
    }
    
    private function checkTeamStatistics(bool $fix, bool $silent): array
    {
        $issues = [];
        
        // MEMORY FIX: Use count instead of get() for large datasets
        $teamsWithoutStatsCount = Team::whereDoesntHave('statistics')->count();
        
        if ($teamsWithoutStatsCount > 0) {
            $issues[] = "Teams without statistics: {$teamsWithoutStatsCount}";
            
            if (!$silent) {
                $this->warn("⚠️ Found {$teamsWithoutStatsCount} teams without statistics");
            }
            
            if ($fix) {
                if (!$silent) {
                    $this->info('🔧 Calculating team statistics...');
                }
                
                $this->call('teams:calculate-statistics', ['--force' => true]);
                
                if (!$silent) {
                    $this->info('✅ Team statistics calculated');
                }
            }
        }
        
        // Find teams with outdated statistics (older than 7 days)
        $outdatedStats = TeamStatistic::where('updated_at', '<', now()->subDays(7))
            ->whereHas('team.homeMatches', function($query) {
                $query->where('match_date', '>', now()->subDays(7))
                      ->where('status', 'finished');
            })
            ->orWhereHas('team.awayMatches', function($query) {
                $query->where('match_date', '>', now()->subDays(7))
                      ->where('status', 'finished');
            })
            ->count();
            
        if ($outdatedStats > 0) {
            $issues[] = "Teams with outdated statistics: {$outdatedStats}";
            
            if (!$silent) {
                $this->warn("⚠️ Found {$outdatedStats} teams with outdated statistics");
            }
            
            if ($fix) {
                if (!$silent) {
                    $this->info('🔧 Updating outdated team statistics...');
                }
                
                $this->call('teams:calculate-statistics', ['--force' => true]);
            }
        }
        
        return $issues;
    }
    
    private function checkMissingPredictions(bool $fix, bool $silent): array
    {
        $issues = [];
        
        // Find scheduled matches without predictions
        $matchesWithoutPredictions = FootballMatch::where('status', 'scheduled')
            ->where('match_date', '>=', now())
            ->where('match_date', '<=', now()->addDays(7))
            ->whereDoesntHave('prediction')
            ->count();
            
        if ($matchesWithoutPredictions > 0) {
            $issues[] = "Scheduled matches without predictions: {$matchesWithoutPredictions}";
            
            if (!$silent) {
                $this->warn("⚠️ Found {$matchesWithoutPredictions} scheduled matches without predictions");
            }
            
            if ($fix) {
                if (!$silent) {
                    $this->info('🔧 Generating missing predictions...');
                }
                
                $this->call('ml:predict');
                
                if (!$silent) {
                    $this->info('✅ Missing predictions generated');
                }
            }
        }
        
        // Find finished matches without predictions (for historical analysis)
        $finishedWithoutPredictions = FootballMatch::where('status', 'finished')
            ->where('match_date', '>=', now()->subDays(30))
            ->whereDoesntHave('prediction')
            ->count();
            
        if ($finishedWithoutPredictions > 0) {
            $issues[] = "Recent finished matches without predictions: {$finishedWithoutPredictions}";
            
            if (!$silent) {
                $this->warn("⚠️ Found {$finishedWithoutPredictions} recent finished matches without predictions");
            }
            
            if ($fix) {
                if (!$silent) {
                    $this->info('🔧 Generating predictions for finished matches...');
                }
                
                $this->call('predictions:generate-finished', ['--limit' => 100]);
                
                if (!$silent) {
                    $this->info('✅ Historical predictions generated');
                }
            }
        }
        
        return $issues;
    }
    
    private function checkOrphanedData(bool $fix, bool $silent): array
    {
        $issues = [];
        
        // Find predictions without matches
        $orphanedPredictions = MatchPrediction::whereDoesntHave('match')->count();
        
        if ($orphanedPredictions > 0) {
            $issues[] = "Orphaned predictions: {$orphanedPredictions}";
            
            if (!$silent) {
                $this->warn("⚠️ Found {$orphanedPredictions} orphaned predictions");
            }
            
            if ($fix) {
                if (!$silent) {
                    $this->info('🔧 Removing orphaned predictions...');
                }
                
                MatchPrediction::whereDoesntHave('match')->delete();
                
                if (!$silent) {
                    $this->info('✅ Orphaned predictions removed');
                }
            }
        }
        
        // Find team statistics without teams
        $orphanedStats = TeamStatistic::whereDoesntHave('team')->count();
        
        if ($orphanedStats > 0) {
            $issues[] = "Orphaned team statistics: {$orphanedStats}";
            
            if (!$silent) {
                $this->warn("⚠️ Found {$orphanedStats} orphaned team statistics");
            }
            
            if ($fix) {
                if (!$silent) {
                    $this->info('🔧 Removing orphaned team statistics...');
                }
                
                TeamStatistic::whereDoesntHave('team')->delete();
                
                if (!$silent) {
                    $this->info('✅ Orphaned team statistics removed');
                }
            }
        }
        
        return $issues;
    }
    
    private function checkDataConsistency(bool $fix, bool $silent): array
    {
        $issues = [];
        
        // Check for matches with invalid scores
        $invalidScores = FootballMatch::where('status', 'finished')
            ->where(function($query) {
                $query->whereNull('home_goals')
                      ->orWhereNull('away_goals')
                      ->orWhere('home_goals', '<', 0)
                      ->orWhere('away_goals', '<', 0);
            })
            ->count();
            
        if ($invalidScores > 0) {
            $issues[] = "Matches with invalid scores: {$invalidScores}";
            
            if (!$silent) {
                $this->warn("⚠️ Found {$invalidScores} finished matches with invalid scores");
            }
            
            // Note: This usually requires manual intervention as we can't guess scores
            if ($fix && !$silent) {
                $this->warn("⚠️ Invalid scores require manual intervention - cannot auto-fix");
            }
        }
        
        // Check for future matches marked as finished
        $futureFinished = FootballMatch::where('status', 'finished')
            ->where('match_date', '>', now())
            ->count();
            
        if ($futureFinished > 0) {
            $issues[] = "Future matches marked as finished: {$futureFinished}";
            
            if (!$silent) {
                $this->warn("⚠️ Found {$futureFinished} future matches marked as finished");
            }
            
            if ($fix) {
                if (!$silent) {
                    $this->info('🔧 Fixing future matches status...');
                }
                
                FootballMatch::where('status', 'finished')
                    ->where('match_date', '>', now())
                    ->update(['status' => 'scheduled']);
                
                if (!$silent) {
                    $this->info('✅ Future matches status corrected');
                }
            }
        }
        
        // Check for very old live matches that should be finished
        $staleLiveMatches = FootballMatch::where('status', 'live')
            ->where('match_date', '<', now()->subHours(3))
            ->count();
            
        if ($staleLiveMatches > 0) {
            $issues[] = "Stale live matches: {$staleLiveMatches}";
            
            if (!$silent) {
                $this->warn("⚠️ Found {$staleLiveMatches} stale live matches");
            }
            
            if ($fix) {
                if (!$silent) {
                    $this->info('🔧 Auto-finishing stale live matches...');
                }
                
                $this->call('matches:auto-finish');
                
                if (!$silent) {
                    $this->info('✅ Stale live matches processed');
                }
            }
        }
        
        return $issues;
    }
    
    private function logIntegrityCheck(array $issues, bool $fixed): void
    {
        $totalIssues = array_sum(array_map('count', $issues));
        
        $logData = [
            'timestamp' => now()->toISOString(),
            'total_issues' => $totalIssues,
            'issues_by_category' => array_map('count', $issues),
            'auto_fixed' => $fixed,
            'details' => $issues
        ];
        
        \Log::info('Data Integrity Check Completed', $logData);
        
        // Also save to dedicated integrity log
        $integrityLogFile = storage_path('logs/data_integrity.json');
        
        $existingLogs = [];
        if (file_exists($integrityLogFile)) {
            $existingLogs = json_decode(file_get_contents($integrityLogFile), true) ?: [];
        }
        
        $existingLogs[] = $logData;
        
        // Keep only last 50 entries
        if (count($existingLogs) > 50) {
            $existingLogs = array_slice($existingLogs, -50);
        }
        
        file_put_contents($integrityLogFile, json_encode($existingLogs, JSON_PRETTY_PRINT));
    }
}