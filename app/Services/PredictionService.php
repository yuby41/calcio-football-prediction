<?php

namespace App\Services;

use App\Models\FootballMatch;
use App\Models\MatchPrediction;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Carbon\Carbon;

class PredictionService
{
    public function generatePredictionsForMatches($matches): int
    {
        if (empty($matches)) {
            return 0;
        }
        
        // Check if Python and libraries are available
        if (!$this->checkPythonDependencies()) {
            Log::warning('Python dependencies not available. Skipping predictions.');
            return 0;
        }
        
        $pythonScript = base_path('ml/football_predictor.py');
        
        if (!file_exists($pythonScript)) {
            Log::warning('Python script not found at: ' . $pythonScript);
            return 0;
        }
        
        // Check if models exist
        $modelsPath = base_path('ml/models');
        if (!is_dir($modelsPath) || !file_exists($modelsPath . '/metadata.json')) {
            Log::warning('ML models not found. Skipping predictions.');
            return 0;
        }
        
        $predicted = 0;
        
        foreach ($matches as $match) {
            if ($this->predictMatch($match)) {
                $predicted++;
            }
        }
        
        return $predicted;
    }
    
    public function predictMatch(FootballMatch $match): bool
    {
        try {
            // Skip if prediction already exists (regardless of match status)
            if ($match->prediction) {
                return true;
            }
            
            $pythonScript = base_path('ml/football_predictor.py');
            
            // Try multiple Python configurations for Homestead VM
            $mlPath = base_path('ml');
            $commands = [
                "cd {$mlPath} && source venv/bin/activate && python football_predictor.py predict {$match->home_team_id} {$match->away_team_id}",
                "cd {$mlPath} && python3 football_predictor.py predict {$match->home_team_id} {$match->away_team_id}",
                "python3 {$pythonScript} predict {$match->home_team_id} {$match->away_team_id}",
                "/usr/bin/python3 {$pythonScript} predict {$match->home_team_id} {$match->away_team_id}"
            ];
            
            $predictionData = null;
            
            foreach ($commands as $command) {
                $process = Process::fromShellCommandline($command);
                $process->setTimeout(30);
                $process->run();
                
                if ($process->isSuccessful()) {
                    $output = $process->getOutput();
                    $predictionData = json_decode($output, true);
                    
                    if ($predictionData && is_array($predictionData)) {
                        break; // Success!
                    }
                } else {
                    Log::warning("Command failed: {$command}. Error: " . $process->getErrorOutput());
                }
            }
            
            // If ML prediction failed, create a basic prediction based on team stats
            if (!$predictionData) {
                Log::warning("ML prediction failed for match {$match->id}, using fallback prediction");
                $predictionData = $this->createFallbackPrediction($match);
            }
            
            if ($predictionData && is_array($predictionData)) {
                MatchPrediction::updateOrCreate(
                    ['match_id' => $match->id],
                    array_merge($predictionData, [
                        'predicted_at' => Carbon::now(),
                    ])
                );
                
                return true;
            }
            
        } catch (\Exception $e) {
            Log::error("Exception predicting match {$match->id}: " . $e->getMessage());
        }
        
        return false;
    }
    
    public function generatePredictionsForTodayMatches(): int
    {
        $todayMatches = FootballMatch::where('status', 'scheduled')
            ->whereDate('match_date', Carbon::today())
            ->get();
            
        return $this->generatePredictionsForMatches($todayMatches);
    }
    
    public function generatePredictionsForUpcomingMatches(int $days = 7): int
    {
        $upcomingMatches = FootballMatch::where('status', 'scheduled')
            ->whereBetween('match_date', [
                Carbon::now(),
                Carbon::now()->addDays($days)
            ])
            ->get();
            
        return $this->generatePredictionsForMatches($upcomingMatches);
    }
    
    public function generatePredictionsForFinishedMatches(int $limit = 100): int
    {
        $finishedMatches = FootballMatch::where('status', 'finished')
            ->whereDoesntHave('prediction')
            ->whereNotNull('home_goals')
            ->whereNotNull('away_goals')
            ->orderBy('match_date', 'desc')
            ->limit($limit)
            ->get();
            
        return $this->generatePredictionsForMatches($finishedMatches);
    }
    
    private function checkPythonDependencies(): bool
    {
        try {
            $commands = [
                'cd ' . base_path('ml') . ' && source venv/bin/activate && python -c "import pandas, numpy, sklearn, xgboost; print(\'OK\')"',
                'python3 -c "import pandas, numpy, sklearn, xgboost; print(\'OK\')"',
                '/usr/bin/python3 -c "import pandas, numpy, sklearn, xgboost; print(\'OK\')"'
            ];
            
            foreach ($commands as $command) {
                $process = Process::fromShellCommandline($command);
                $process->setTimeout(10);
                $process->run();
                
                if ($process->isSuccessful() && trim($process->getOutput()) === 'OK') {
                    return true;
                }
            }
            
            return false;
        } catch (\Exception $e) {
            Log::warning('Failed to check Python dependencies: ' . $e->getMessage());
            return false;
        }
    }
    
    private function createFallbackPrediction(FootballMatch $match): array
    {
        // Get team statistics
        $homeStats = $match->homeTeam->statistics()->where('season', date('Y'))->first();
        $awayStats = $match->awayTeam->statistics()->where('season', date('Y'))->first();
        
        // Default stats if none exist
        $homeWinRate = $homeStats ? ($homeStats->wins / max(1, $homeStats->matches_played)) : 0.33;
        $awayWinRate = $awayStats ? ($awayStats->wins / max(1, $awayStats->matches_played)) : 0.33;
        
        $homeGoalsAvg = $homeStats ? $homeStats->avg_goals_for : 1.5;
        $awayGoalsAvg = $awayStats ? $awayStats->avg_goals_for : 1.5;
        
        // Simple prediction logic with home advantage
        $homeAdvantage = 0.1; // 10% home advantage
        $homeWinProb = min(0.6, max(0.2, $homeWinRate + $homeAdvantage + 0.1));
        $awayWinProb = min(0.6, max(0.2, $awayWinRate - 0.1));
        $drawProb = max(0.15, 1.0 - $homeWinProb - $awayWinProb);
        
        // Normalize probabilities
        $total = $homeWinProb + $drawProb + $awayWinProb;
        $homeWinProb /= $total;
        $drawProb /= $total;
        $awayWinProb /= $total;
        
        // Determine predicted outcome
        $outcomes = ['home_win' => $homeWinProb, 'draw' => $drawProb, 'away_win' => $awayWinProb];
        $predictedOutcome = array_keys($outcomes, max($outcomes))[0];
        
        // Goal predictions with some variance
        $homeGoals = max(0.5, $homeGoalsAvg + rand(-5, 5) / 10);
        $awayGoals = max(0.5, $awayGoalsAvg + rand(-5, 5) / 10);
        
        $totalGoals = $homeGoals + $awayGoals;
        $bothTeamsScoreProb = min(0.8, max(0.3, ($homeGoals > 0.8 && $awayGoals > 0.8) ? 0.7 : 0.4));
        $over25Prob = min(0.8, max(0.2, $totalGoals > 2.5 ? 0.65 : 0.35));
        
        return [
            'home_goals_prediction' => round($homeGoals, 2),
            'away_goals_prediction' => round($awayGoals, 2),
            'home_win_probability' => round($homeWinProb, 4),
            'draw_probability' => round($drawProb, 4),
            'away_win_probability' => round($awayWinProb, 4),
            'both_teams_score_probability' => round($bothTeamsScoreProb, 4),
            'over_2_5_probability' => round($over25Prob, 4),
            'under_2_5_probability' => round(1 - $over25Prob, 4),
            'predicted_outcome' => $predictedOutcome,
            'confidence_score' => round(max($outcomes), 4),
            'model_version' => 'fallback_1.0',
            'features_used' => ['basic_stats', 'home_advantage']
        ];
    }
}