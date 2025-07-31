<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\MatchPrediction;
use App\Models\FootballMatch;
use Carbon\Carbon;

class CheckModelPerformance extends Command
{
    protected $signature = 'ml:check-model-performance 
                           {--alert-threshold=70 : Accuracy threshold for alerts}
                           {--period=30 : Number of days to analyze}
                           {--trigger-retrain : Trigger retraining if performance is low}';
    
    protected $description = 'Check ML model performance and trigger alerts or retraining';
    
    public function handle(): int
    {
        $alertThreshold = (float) $this->option('alert-threshold');
        $period = (int) $this->option('period');
        $triggerRetrain = $this->option('trigger-retrain');
        
        $this->info("📊 Checking ML model performance over last {$period} days...");
        
        // Analyze overall performance
        $overallStats = $this->analyzeOverallPerformance($period);
        $this->displayOverallStats($overallStats);
        
        // Analyze by prediction type
        $detailedStats = $this->analyzeDetailedPerformance($period);
        $this->displayDetailedStats($detailedStats);
        
        // Check for performance degradation
        $degradationAlert = $this->checkPerformanceDegradation($period);
        if ($degradationAlert) {
            $this->displayDegradationAlert($degradationAlert);
        }
        
        // Trigger alerts or retraining if needed
        if ($overallStats['accuracy'] < $alertThreshold) {
            $this->warn("🚨 ALERT: Model accuracy ({$overallStats['accuracy']}%) is below threshold ({$alertThreshold}%)");
            
            if ($triggerRetrain) {
                $this->info("🔄 Triggering automated retraining...");
                $this->call('ml:train-automated', ['--force' => true]);
            } else {
                $this->warn("💡 Consider running: php artisan ml:train-automated");
            }
        } else {
            $this->info("✅ Model performance is acceptable.");
        }
        
        // Log performance metrics
        $this->logPerformanceMetrics($overallStats, $detailedStats);
        
        return Command::SUCCESS;
    }
    
    private function analyzeOverallPerformance(int $days): array
    {
        $startDate = now()->subDays($days);
        
        $predictions = MatchPrediction::whereHas('match', function($query) use ($startDate) {
            $query->where('status', 'finished')
                  ->where('match_date', '>=', $startDate);
        })->with('match')->get();
        
        if ($predictions->isEmpty()) {
            return [
                'total_predictions' => 0,
                'accuracy' => 0,
                'correct_predictions' => 0,
                'period_days' => $days
            ];
        }
        
        $correctOutcomes = $predictions->filter(function($prediction) {
            return $prediction->is_outcome_correct;
        })->count();
        
        $totalPredictions = $predictions->count();
        $accuracy = ($correctOutcomes / $totalPredictions) * 100;
        
        return [
            'total_predictions' => $totalPredictions,
            'accuracy' => round($accuracy, 2),
            'correct_predictions' => $correctOutcomes,
            'period_days' => $days,
            'daily_average' => round($totalPredictions / $days, 1)
        ];
    }
    
    private function analyzeDetailedPerformance(int $days): array
    {
        $startDate = now()->subDays($days);
        
        $predictions = MatchPrediction::whereHas('match', function($query) use ($startDate) {
            $query->where('status', 'finished')
                  ->where('match_date', '>=', $startDate);
        })->with('match')->get();
        
        $stats = [
            'outcome_accuracy' => 0,
            'both_teams_score_accuracy' => 0,
            'over_2_5_accuracy' => 0,
            'goal_prediction_mae' => 0,
            'confidence_correlation' => 0
        ];
        
        if ($predictions->isEmpty()) {
            return $stats;
        }
        
        // Outcome accuracy
        $correctOutcomes = $predictions->filter(function($prediction) {
            return $prediction->is_outcome_correct;
        })->count();
        $stats['outcome_accuracy'] = round(($correctOutcomes / $predictions->count()) * 100, 2);
        
        // Both teams score accuracy
        $btsCorrect = $predictions->filter(function($prediction) {
            $match = $prediction->match;
            $bothScored = $match->home_goals > 0 && $match->away_goals > 0;
            $predictedBts = $prediction->both_teams_score_probability > 0.5;
            return $bothScored === $predictedBts;
        })->count();
        $stats['both_teams_score_accuracy'] = round(($btsCorrect / $predictions->count()) * 100, 2);
        
        // Over 2.5 goals accuracy
        $over25Correct = $predictions->filter(function($prediction) {
            $match = $prediction->match;
            $totalGoals = $match->home_goals + $match->away_goals;
            $over25 = $totalGoals > 2.5;
            $predictedOver25 = $prediction->over_2_5_probability > 0.5;
            return $over25 === $predictedOver25;
        })->count();
        $stats['over_2_5_accuracy'] = round(($over25Correct / $predictions->count()) * 100, 2);
        
        // Goal prediction MAE (Mean Absolute Error)
        $goalErrors = $predictions->map(function($prediction) {
            $match = $prediction->match;
            $predictedTotal = $prediction->home_goals_prediction + $prediction->away_goals_prediction;
            $actualTotal = $match->home_goals + $match->away_goals;
            return abs($predictedTotal - $actualTotal);
        });
        $stats['goal_prediction_mae'] = round($goalErrors->average(), 2);
        
        return $stats;
    }
    
    private function checkPerformanceDegradation(int $days): array|null
    {
        $recentPeriod = $this->analyzeOverallPerformance(7); // Last 7 days
        $olderPeriod = $this->analyzeOverallPerformance(30); // Last 30 days
        
        if ($recentPeriod['total_predictions'] < 10 || $olderPeriod['total_predictions'] < 50) {
            return null; // Not enough data
        }
        
        $accuracyDrop = $olderPeriod['accuracy'] - $recentPeriod['accuracy'];
        
        if ($accuracyDrop > 5) { // More than 5% drop
            return [
                'recent_accuracy' => $recentPeriod['accuracy'],
                'historical_accuracy' => $olderPeriod['accuracy'],
                'accuracy_drop' => round($accuracyDrop, 2),
                'is_significant' => $accuracyDrop > 10
            ];
        }
        
        return null;
    }
    
    private function displayOverallStats(array $stats): void
    {
        $this->info("📈 Overall Performance ({$stats['period_days']} days):");
        $this->line("  Total Predictions: {$stats['total_predictions']}");
        $this->line("  Correct Predictions: {$stats['correct_predictions']}");
        $this->line("  Overall Accuracy: {$stats['accuracy']}%");
        $this->line("  Daily Average: {$stats['daily_average']} predictions/day");
        $this->newLine();
    }
    
    private function displayDetailedStats(array $stats): void
    {
        $this->info("🔍 Detailed Performance Breakdown:");
        $this->line("  Match Outcome Accuracy: {$stats['outcome_accuracy']}%");
        $this->line("  Both Teams Score Accuracy: {$stats['both_teams_score_accuracy']}%");
        $this->line("  Over 2.5 Goals Accuracy: {$stats['over_2_5_accuracy']}%");
        $this->line("  Goal Prediction MAE: {$stats['goal_prediction_mae']} goals");
        $this->newLine();
    }
    
    private function displayDegradationAlert(array $alert): void
    {
        $severity = $alert['is_significant'] ? '🚨 CRITICAL' : '⚠️ WARNING';
        
        $this->warn("{$severity} Performance Degradation Detected:");
        $this->line("  Recent Accuracy (7 days): {$alert['recent_accuracy']}%");
        $this->line("  Historical Accuracy (30 days): {$alert['historical_accuracy']}%");
        $this->line("  Accuracy Drop: {$alert['accuracy_drop']}%");
        
        if ($alert['is_significant']) {
            $this->error("🚨 URGENT: Model performance has degraded significantly!");
            $this->error("   Immediate retraining recommended!");
        }
        
        $this->newLine();
    }
    
    private function logPerformanceMetrics(array $overall, array $detailed): void
    {
        $logData = [
            'timestamp' => now()->toISOString(),
            'overall_accuracy' => $overall['accuracy'],
            'total_predictions' => $overall['total_predictions'],
            'outcome_accuracy' => $detailed['outcome_accuracy'],
            'both_teams_score_accuracy' => $detailed['both_teams_score_accuracy'],
            'over_2_5_accuracy' => $detailed['over_2_5_accuracy'],
            'goal_prediction_mae' => $detailed['goal_prediction_mae']
        ];
        
        // Log to Laravel log file
        \Log::info('ML Model Performance Check', $logData);
        
        // Also save to dedicated performance log file
        $performanceLogFile = storage_path('logs/ml_performance.json');
        
        $existingLogs = [];
        if (file_exists($performanceLogFile)) {
            $existingLogs = json_decode(file_get_contents($performanceLogFile), true) ?: [];
        }
        
        $existingLogs[] = $logData;
        
        // Keep only last 100 entries
        if (count($existingLogs) > 100) {
            $existingLogs = array_slice($existingLogs, -100);
        }
        
        file_put_contents($performanceLogFile, json_encode($existingLogs, JSON_PRETTY_PRINT));
        
        $this->info("📝 Performance metrics logged to storage/logs/ml_performance.json");
    }
}