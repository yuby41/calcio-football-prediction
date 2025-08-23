<?php

namespace App\Services;

use App\Models\FootballMatch;
use App\Models\MatchPrediction;
use App\Constants\PredictionConstants;
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
            
            // Get active model from configuration
            $activeModel = $this->getActiveModel();
            Log::info("Using {$activeModel} model for match {$match->id}");
            
            $predictionData = null;
            
            // Route to appropriate prediction method based on active model
            switch ($activeModel) {
                case 'enhanced':
                    $predictionData = $this->predictWithEnhancedModel($match);
                    break;
                case 'simple':
                    $predictionData = $this->predictWithSimpleModel($match);
                    break;
                case 'ensemble':
                    $predictionData = $this->predictWithEnsembleModel($match);
                    break;
                default:
                    Log::warning("Unknown model '{$activeModel}', using fallback");
                    $predictionData = $this->createFallbackPrediction($match);
                    break;
            }
            
            // Fallback to statistical prediction if needed
            if (!$predictionData) {
                Log::warning("{$activeModel} predictor failed for match {$match->id}, using statistical fallback");
                $predictionData = $this->createFallbackPrediction($match);
            } else {
                Log::info("{$activeModel} predictor succeeded for match {$match->id}");
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
    
    private function hasEnhancedModels(): bool
    {
        $modelsPath = base_path('ml/models');
        $requiredFiles = [
            'enhanced_outcome_model.pkl',
            'enhanced_scaler.pkl',
            'enhanced_metadata.json'
        ];
        
        foreach ($requiredFiles as $file) {
            if (!file_exists($modelsPath . '/' . $file)) {
                return false;
            }
        }
        
        return true;
    }
    
    private function predictWithEnhancedModels(FootballMatch $match, string $mlPath): ?array
    {
        Log::info("Attempting Enhanced ML prediction for match {$match->id} (home: {$match->home_team_id}, away: {$match->away_team_id})");
        
        $pythonPath = "/home/yualbe/.local/lib/python3.12/site-packages:/usr/lib/python3/dist-packages:/usr/local/lib/python3.12/dist-packages";
        
        // Usar únicamente el entorno virtual configurado definitivamente
        $basePath = escapeshellarg(base_path());
        $mlPathEscaped = escapeshellarg($mlPath);
        $homeTeamId = (int) $match->home_team_id; // Sanitize to integer
        $awayTeamId = (int) $match->away_team_id; // Sanitize to integer
        
        $commands = [
            "/bin/bash -c " . escapeshellarg("cd {$basePath} && source ml_env/bin/activate && python {$mlPathEscaped}/enhanced_football_predictor.py predict {$homeTeamId} {$awayTeamId}")
        ];
        
        foreach ($commands as $command) {
            try {
                $process = Process::fromShellCommandline($command);
                $process->setTimeout(PredictionConstants::ML_PREDICTION_TIMEOUT);
                $process->run();
                
                if ($process->isSuccessful()) {
                    $output = $process->getOutput();
                    $predictionData = json_decode($output, true);
                    
                    if ($predictionData && is_array($predictionData) && isset($predictionData['model_version'])) {
                        Log::info("Enhanced ML prediction successful for match {$match->id}", [
                            'model_version' => $predictionData['model_version'],
                            'confidence' => $predictionData['confidence_score'] ?? 'unknown'
                        ]);
                        return $predictionData;
                    }
                } else {
                    Log::warning("Enhanced command failed: {$command}. Error: " . $process->getErrorOutput());
                }
            } catch (\Exception $e) {
                Log::warning("Enhanced prediction exception: " . $e->getMessage());
            }
        }
        
        Log::warning("All Enhanced ML commands failed for match {$match->id}");
        return null;
    }
    
    private function predictWithStandardModels(FootballMatch $match, string $mlPath): ?array
    {
        $pythonScript = base_path('ml/football_predictor.py');
        
        // Usar únicamente el entorno virtual configurado definitivamente
        $basePath = base_path();
        $commands = [
            "/bin/bash -c 'cd {$basePath} && source ml_env/bin/activate && python {$pythonScript} predict {$match->home_team_id} {$match->away_team_id}'"
        ];
        
        foreach ($commands as $command) {
            try {
                $process = Process::fromShellCommandline($command);
                $process->setTimeout(30);
                $process->run();
                
                if ($process->isSuccessful()) {
                    $output = $process->getOutput();
                    $predictionData = json_decode($output, true);
                    
                    if ($predictionData && is_array($predictionData)) {
                        Log::info("Standard ML prediction successful for match {$match->id}");
                        return $predictionData;
                    }
                } else {
                    Log::debug("Standard command failed: {$command}. Error: " . $process->getErrorOutput());
                }
            } catch (\Exception $e) {
                Log::debug("Standard prediction exception: " . $e->getMessage());
            }
        }
        
        return null;
    }

    private function checkPythonDependencies(): bool
    {
        try {
            // Usar únicamente el entorno virtual configurado definitivamente
            $basePath = base_path();
            $commands = [
                "/bin/bash -c 'cd {$basePath} && source ml_env/bin/activate && python -c \"import pandas, numpy, sklearn, xgboost, lightgbm; print(\\\"OK\\\")\"'"
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
        // Get team statistics using prioritized seasons
        $homeStats = $this->getTeamStatistics($match->homeTeam);
        $awayStats = $this->getTeamStatistics($match->awayTeam);
        
        // Calculate team strength indicators
        $homeStrength = $this->calculateTeamStrength($homeStats, $match->homeTeam);
        $awayStrength = $this->calculateTeamStrength($awayStats, $match->awayTeam);
        
        // Calculate home advantage based on team quality difference
        $qualityDiff = abs($homeStrength - $awayStrength);
        $homeAdvantage = $awayStrength > $homeStrength ? 
            max(0.03, PredictionConstants::HOME_ADVANTAGE_WEIGHT - ($qualityDiff * 0.15)) : 
            PredictionConstants::HOME_ADVANTAGE_WEIGHT;
        
        // Get outcome probabilities using extracted method
        $outcomeProbabilities = $this->calculateMatchOutcomeProbabilities($homeStrength, $awayStrength, $homeAdvantage);
        $homeWinProb = $outcomeProbabilities['home_win'];
        $awayWinProb = $outcomeProbabilities['away_win'];
        $drawProb = $outcomeProbabilities['draw'];
        
        // Goal predictions based on team attacking/defensive stats with league normalization
        $homeGoalsAvg = $homeStats ? $homeStats->avg_goals_for : $this->getDefaultGoalsFor($match->homeTeam);
        $awayGoalsAvg = $awayStats ? $awayStats->avg_goals_for : $this->getDefaultGoalsFor($match->awayTeam);
        $homeConcedeAvg = $homeStats ? $homeStats->avg_goals_against : $this->getDefaultGoalsAgainst($match->homeTeam);
        $awayConcedeAvg = $awayStats ? $awayStats->avg_goals_against : $this->getDefaultGoalsAgainst($match->awayTeam);
        
        // FIXED: More sophisticated expected goals calculation using correct formula
        $homeAttackStrength = $homeGoalsAvg;
        $awayAttackStrength = $awayGoalsAvg;
        $homeDefenseStrength = $homeConcedeAvg;
        $awayDefenseStrength = $awayConcedeAvg;
        
        // League average goals per game (realistic baseline)
        $leagueAvgGoals = 1.35; // Unified with ML scripts
        
        // FIXED: Expected goals using correct attack vs defense formula
        $homeExpectedGoals = ($homeAttackStrength / max(0.5, $awayDefenseStrength)) * $leagueAvgGoals + 0.35; // Home advantage
        $awayExpectedGoals = ($awayAttackStrength / max(0.5, $homeDefenseStrength)) * $leagueAvgGoals;
        
        // Apply realistic bounds and reduce extreme predictions
        $homeGoals = max(0.5, min(3.5, $homeExpectedGoals));
        $awayGoals = max(0.5, min(3.5, $awayExpectedGoals));
        
        // Determine predicted outcome using extracted method
        $outcomes = ['home_win' => $homeWinProb, 'draw' => $drawProb, 'away_win' => $awayWinProb];
        $predictedOutcome = $this->determinePredictedOutcome($outcomes);
        
        // Both teams to score calculation
        $homeScoreProb = 1 - exp(-$homeGoals * 0.8);
        $awayScoreProb = 1 - exp(-$awayGoals * 0.8);
        $bothTeamsScoreProb = $homeScoreProb * $awayScoreProb;
        
        // Over/Under 2.5 goals
        $totalGoals = $homeGoals + $awayGoals;
        $over25Prob = 1 / (1 + exp(-(($totalGoals - 2.5) * 2)));
        
        // First half goals prediction (typically 40-45% of total match goals)
        $firstHalfMultiplier = 0.42 + (rand(-3, 3) / 100); // 39%-45% variance
        $homeGoalsFirstHalf = $homeGoals * $firstHalfMultiplier;
        $awayGoalsFirstHalf = $awayGoals * $firstHalfMultiplier;
        $totalFirstHalfGoals = $homeGoalsFirstHalf + $awayGoalsFirstHalf;
        
        // Over 0.5 First Half probability calculation
        $over05FirstHalfProb = 1 - exp(-$totalFirstHalfGoals * 1.2); // Poisson-based
        
        // Confidence based on prediction certainty and data quality
        $maxProbability = max($homeWinProb, $drawProb, $awayWinProb);
        $dataQuality = ($homeStats && $awayStats) ? 0.8 : 0.5;
        
        // Calculate confidence using extracted method
        $confidence = $this->calculateConfidenceScore($outcomes, $homeStrength, $awayStrength);
        $confidence = $confidence / 100; // Convert to decimal for compatibility
        
        return [
            'home_goals_prediction' => round($homeGoals, 2),
            'away_goals_prediction' => round($awayGoals, 2),
            'home_win_probability' => round($homeWinProb, 4),
            'draw_probability' => round($drawProb, 4),
            'away_win_probability' => round($awayWinProb, 4),
            'both_teams_score_probability' => round($bothTeamsScoreProb, 4),
            'over_2_5_probability' => round($over25Prob, 4),
            'under_2_5_probability' => round(1 - $over25Prob, 4),
            'first_half_over_0_5_probability' => round($over05FirstHalfProb, 4),
            'home_goals_first_half_prediction' => round($homeGoalsFirstHalf, 2),
            'away_goals_first_half_prediction' => round($awayGoalsFirstHalf, 2),
            'predicted_outcome' => $predictedOutcome,
            'confidence_score' => round($confidence, 4),
            'model_version' => '3.0.0-enhanced-fallback-integration',
            'features_used' => ['team_strength', 'home_advantage', 'expected_goals', 'first_half_analysis', 'historical_data']
        ];
    }
    
    private function calculateTeamStrength($stats, $team): float
    {
        if (!$stats) {
            // CRITICAL FIX: Create more varied strength values based on team characteristics
            $nameHash = crc32($team->name) % 1000;
            $idHash = crc32((string)$team->id) % 1000;
            
            // Combine different hash sources for better distribution
            $combinedHash = ($nameHash + $idHash * 7) % 1000; // Use prime multiplier for better spread
            
            // Wider range with normal distribution simulation
            $baseStrength = 0.35 + ($combinedHash / 1000 * 0.4); // Range: 0.35 to 0.75
            
            // Add some additional variation based on team ID
            $variation = (($team->id * 13) % 100) / 1000; // Small variation -0.05 to +0.05
            
            return max(0.25, min(0.85, $baseStrength + $variation));
        }
        
        $matchesPlayed = max(1, $stats->matches_played);
        $winRate = $stats->wins / $matchesPlayed;
        $pointsPerGame = $stats->points / ($matchesPlayed * 3); // Already normalized 0-1
        $goalDiffPerGame = ($stats->goals_for - $stats->goals_against) / $matchesPlayed;
        
        // Normalize goal difference to 0-1 scale (assuming range -3 to +3 per game)
        $normalizedGoalDiff = max(0, min(1, ($goalDiffPerGame + 3) / 6));
        
        // Enhanced strength calculation with better balance
        $strength = ($pointsPerGame * 0.5) + ($winRate * 0.3) + ($normalizedGoalDiff * 0.2);
        
        // More realistic range: 0.2 to 0.85 instead of 0.1 to 0.9
        return max(0.2, min(0.85, $strength));
    }
    
    private function getDefaultGoalsFor($team): float
    {
        // Use team characteristics to generate consistent default values
        $teamHash = crc32($team->name . 'goals_for') % 100;
        return 1.0 + ($teamHash / 100 * 1.0); // Range: 1.0 to 2.0
    }
    
    private function getDefaultGoalsAgainst($team): float
    {
        // Use team characteristics to generate consistent default values
        $teamHash = crc32($team->name . 'goals_against') % 100;
        return 1.0 + ($teamHash / 100 * 1.0); // Range: 1.0 to 2.0
    }
    
    /**
     * Get the active ML model from configuration
     */
    private function getActiveModel(): string
    {
        $configPath = config_path('ml_models.php');
        
        if (!file_exists($configPath)) {
            // Default to enhanced if no config exists
            return 'enhanced';
        }
        
        $config = include $configPath;
        return $config['active_model'] ?? 'enhanced';
    }
    
    /**
     * Get model configuration
     */
    private function getModelConfig(): array
    {
        $configPath = config_path('ml_models.php');
        
        if (!file_exists($configPath)) {
            return [];
        }
        
        return include $configPath;
    }
    
    /**
     * Predict with Enhanced ML Model (XGBoost + LightGBM + NN + RF ensemble)
     */
    private function predictWithEnhancedModel(FootballMatch $match): ?array
    {
        try {
            $basePath = base_path();
            $pythonScript = $basePath . '/ml/enhanced_football_predictor.py';
            
            if (!file_exists($pythonScript)) {
                Log::warning("Enhanced predictor script not found at: {$pythonScript}");
                return null;
            }
            
            // Check if models are trained
            if (!$this->hasEnhancedModels()) {
                Log::warning("Enhanced models not trained for match {$match->id}");
                return null;
            }
            
            $command = "/bin/bash -c 'cd {$basePath} && source ml_env/bin/activate && python {$pythonScript} predict {$match->home_team_id} {$match->away_team_id}'";
            
            $process = Process::fromShellCommandline($command);
            $process->setTimeout(45);
            $process->run();
            
            if (!$process->isSuccessful()) {
                Log::warning("Enhanced predictor failed for match {$match->id}. Error: " . $process->getErrorOutput());
                return null;
            }
            
            $output = $process->getOutput();
            $result = json_decode($output, true);
            
            if (!$result || !isset($result['home_goals_prediction'])) {
                Log::warning("Invalid response from enhanced predictor for match {$match->id}. Output: " . $output);
                return null;
            }
            
            Log::info("Enhanced predictor result for match {$match->id}: " . json_encode($result));
            return $this->formatPredictionResult($result, 'enhanced');
            
        } catch (\Exception $e) {
            Log::error("Exception in Enhanced Predictor for match {$match->id}: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Predict with Simple Statistical Model
     */
    private function predictWithSimpleModel(FootballMatch $match): ?array
    {
        try {
            $basePath = base_path();
            $pythonScript = $basePath . '/ml/simple_effective_predictor.py';
            
            if (!file_exists($pythonScript)) {
                Log::warning("Simple predictor script not found at: {$pythonScript}");
                return null;
            }
            
            $command = "/bin/bash -c 'cd {$basePath} && source ml_env/bin/activate && python {$pythonScript} {$match->home_team_id} {$match->away_team_id}'";
            
            $process = Process::fromShellCommandline($command);
            $process->setTimeout(30);
            $process->run();
            
            if (!$process->isSuccessful()) {
                Log::warning("Simple predictor failed for match {$match->id}. Error: " . $process->getErrorOutput());
                return null;
            }
            
            $output = $process->getOutput();
            $result = json_decode($output, true);
            
            if (!$result || !isset($result['success']) || !$result['success']) {
                Log::warning("Invalid response from simple predictor for match {$match->id}");
                return null;
            }
            
            return $this->formatSimplePredictionResult($result['predictions'], 'simple');
            
        } catch (\Exception $e) {
            Log::error("Exception in Simple Predictor for match {$match->id}: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Predict with Ensemble Model (combines Enhanced + Simple)
     */
    private function predictWithEnsembleModel(FootballMatch $match): ?array
    {
        try {
            // Get predictions from both models
            $enhancedResult = $this->predictWithEnhancedModel($match);
            $simpleResult = $this->predictWithSimpleModel($match);
            
            // If only one works, use that one
            if ($enhancedResult && !$simpleResult) {
                $enhancedResult['model_version'] = 'ensemble_enhanced_only';
                return $enhancedResult;
            }
            
            if ($simpleResult && !$enhancedResult) {
                $simpleResult['model_version'] = 'ensemble_simple_only';
                return $simpleResult;
            }
            
            // If both failed, return null
            if (!$enhancedResult || !$simpleResult) {
                Log::warning("Both models failed for ensemble prediction of match {$match->id}");
                return null;
            }
            
            // Combine both results with weighting (Enhanced: 70%, Simple: 30%)
            return $this->combineModelResults($enhancedResult, $simpleResult, 0.7, 0.3);
            
        } catch (\Exception $e) {
            Log::error("Exception in Ensemble Predictor for match {$match->id}: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Format prediction result for database storage
     */
    private function formatPredictionResult(array $result, string $modelType): array
    {
        return [
            'predicted_outcome' => $result['predicted_outcome'],
            'confidence_score' => $result['confidence_score'] ?? 0.5,
            'model_version' => $result['model_version'] ?? $modelType,
            
            // Goals predictions
            'home_goals_prediction' => $result['home_goals_prediction'] ?? 1.5,
            'away_goals_prediction' => $result['away_goals_prediction'] ?? 1.2,
            
            // Probabilities
            'home_win_probability' => $result['home_win_probability'] ?? 0.33,
            'draw_probability' => $result['draw_probability'] ?? 0.33,
            'away_win_probability' => $result['away_win_probability'] ?? 0.33,
            'both_teams_score_probability' => $result['both_teams_score_probability'] ?? 0.5,
            'over_2_5_probability' => $result['over_2_5_probability'] ?? 0.5,
            'under_2_5_probability' => $result['under_2_5_probability'] ?? 0.5,
            'first_half_over_0_5_probability' => $result['first_half_over_0_5_probability'] ?? 0.6,
            
            // Derived predictions
            'over_2_5_prediction' => ($result['over_2_5_probability'] ?? 0.5) > 0.5,
            'over_2_5_confidence' => $result['over_2_5_probability'] ?? 0.5,
            'over_0_5_first_half_prediction' => ($result['first_half_over_0_5_probability'] ?? 0.6) > 0.5,
            'first_half_confidence' => $result['first_half_over_0_5_probability'] ?? 0.6,
            'both_teams_score_prediction' => ($result['both_teams_score_probability'] ?? 0.5) > 0.5,
            'both_teams_score_confidence' => $result['both_teams_score_probability'] ?? 0.5,
            
            // Metadata
            'prediction_type' => 'main_outcome',
            'created_at' => now(),
            'updated_at' => now()
        ];
    }
    
    /**
     * Format simple prediction result for database storage
     */
    private function formatSimplePredictionResult(array $predictions, string $modelType): array
    {
        $matchOutcome = $predictions['match_outcome'];
        $overUnder = $predictions['over_under_2_5'];
        $firstHalf = $predictions['first_half_over_0_5'];
        
        return [
            'predicted_outcome' => $matchOutcome['predicted_outcome'],
            'confidence_score' => $matchOutcome['confidence'] / 100.0,
            'model_version' => $matchOutcome['model_version'] ?? $modelType,
            
            // Goals predictions
            'home_goals_prediction' => $matchOutcome['home_expected_goals'] ?? 1.5,
            'away_goals_prediction' => $matchOutcome['away_expected_goals'] ?? 1.2,
            
            // Calculate probabilities from confidence and outcome
            'home_win_probability' => $this->calculateProbabilityFromOutcome($matchOutcome, 'home_win'),
            'draw_probability' => $this->calculateProbabilityFromOutcome($matchOutcome, 'draw'),
            'away_win_probability' => $this->calculateProbabilityFromOutcome($matchOutcome, 'away_win'),
            'both_teams_score_probability' => $this->calculateBothTeamsScoreProbability($matchOutcome['home_expected_goals'] ?? 1.5, $matchOutcome['away_expected_goals'] ?? 1.2),
            'over_2_5_probability' => $overUnder['confidence'] / 100.0 * ($overUnder['prediction'] === 'over' ? 1 : 0),
            'under_2_5_probability' => $overUnder['confidence'] / 100.0 * ($overUnder['prediction'] === 'under' ? 1 : 0),
            'first_half_over_0_5_probability' => $firstHalf['confidence'] / 100.0,
            
            // Predictions
            'over_2_5_prediction' => $overUnder['prediction'] === 'over',
            'over_2_5_confidence' => $overUnder['confidence'] / 100.0,
            'over_0_5_first_half_prediction' => str_contains($firstHalf['prediction'], 'over'),
            'first_half_confidence' => $firstHalf['confidence'] / 100.0,
            'both_teams_score_prediction' => $this->calculateBothTeamsScoreProbability($matchOutcome['home_expected_goals'] ?? 1.5, $matchOutcome['away_expected_goals'] ?? 1.2) > 0.5,
            'both_teams_score_confidence' => $this->calculateBothTeamsScoreProbability($matchOutcome['home_expected_goals'] ?? 1.5, $matchOutcome['away_expected_goals'] ?? 1.2),
            
            // Metadata
            'prediction_type' => 'main_outcome',
            'created_at' => now(),
            'updated_at' => now()
        ];
    }
    
    /**
     * Calculate probability from simple model outcome and confidence
     */
    private function calculateProbabilityFromOutcome(array $outcome, string $targetOutcome): float
    {
        $confidence = $outcome['confidence'] / 100.0;
        $predictedOutcome = $outcome['predicted_outcome'];
        
        if ($predictedOutcome === $targetOutcome) {
            // This is the predicted outcome, assign high probability based on confidence
            return max(0.33, $confidence);
        } else {
            // This is not the predicted outcome, assign remaining probability
            return (1.0 - $confidence) / 2.0; // Split remaining probability between other two outcomes
        }
    }
    
    /**
     * Combine results from two models with weighting
     */
    private function combineModelResults(array $enhanced, array $simple, float $enhancedWeight, float $simpleWeight): array
    {
        $combined = $enhanced; // Start with enhanced as base
        
        // Combine probabilities with weighting
        $combined['home_win_probability'] = ($enhanced['home_win_probability'] * $enhancedWeight) + 
                                           ($simple['home_win_probability'] * $simpleWeight);
        $combined['draw_probability'] = ($enhanced['draw_probability'] * $enhancedWeight) + 
                                       ($simple['draw_probability'] * $simpleWeight);
        $combined['away_win_probability'] = ($enhanced['away_win_probability'] * $enhancedWeight) + 
                                           ($simple['away_win_probability'] * $simpleWeight);
        
        // Combine goal predictions
        $combined['home_goals_prediction'] = ($enhanced['home_goals_prediction'] * $enhancedWeight) + 
                                           ($simple['home_goals_prediction'] * $simpleWeight);
        $combined['away_goals_prediction'] = ($enhanced['away_goals_prediction'] * $enhancedWeight) + 
                                           ($simple['away_goals_prediction'] * $simpleWeight);
        
        // Combine other probabilities
        $combined['over_2_5_probability'] = ($enhanced['over_2_5_probability'] * $enhancedWeight) + 
                                           ($simple['over_2_5_probability'] * $simpleWeight);
        $combined['both_teams_score_probability'] = ($enhanced['both_teams_score_probability'] * $enhancedWeight) + 
                                                   ($simple['both_teams_score_probability'] * $simpleWeight);
        
        // Determine final outcome based on combined probabilities
        $outcomes = [
            'home_win' => $combined['home_win_probability'],
            'draw' => $combined['draw_probability'], 
            'away_win' => $combined['away_win_probability']
        ];
        
        $combined['predicted_outcome'] = array_keys($outcomes, max($outcomes))[0];
        $combined['confidence_score'] = max($outcomes);
        $combined['model_version'] = 'ensemble_v1.0';
        
        // Update derived predictions
        $combined['over_2_5_prediction'] = $combined['over_2_5_probability'] > 0.5;
        $combined['both_teams_score_prediction'] = $combined['both_teams_score_probability'] > 0.5;
        
        return $combined;
    }
    
    /**
     * NEW: Simple Effective Predictor - Target >52% accuracy
     * Replaces complex ML models with proven statistical approach
     * @deprecated Use predictWithEnhancedModel instead
     */
    private function predictWithSimpleEffectiveModel(FootballMatch $match): ?array
    {
        try {
            $basePath = base_path();
            $pythonScript = $basePath . '/ml/enhanced_football_predictor.py';
            
            if (!file_exists($pythonScript)) {
                Log::warning("Simple effective predictor script not found at: {$pythonScript}");
                return null;
            }
            
            // Use the enhanced predictor with predict command
            $command = "/bin/bash -c 'cd {$basePath} && python3 {$pythonScript} predict {$match->home_team_id} {$match->away_team_id}'";
            
            $process = Process::fromShellCommandline($command);
            $process->setTimeout(30);
            $process->run();
            
            if (!$process->isSuccessful()) {
                Log::warning("Enhanced predictor failed for match {$match->id}. Error: " . $process->getErrorOutput());
                return null;
            }
            
            $output = $process->getOutput();
            $result = json_decode($output, true);
            
            if (!$result || !isset($result['home_goals_prediction'])) {
                Log::warning("Invalid response from enhanced predictor for match {$match->id}");
                return null;
            }
            
            // Map enhanced predictor response to database format
            return [
                'predicted_outcome' => $result['predicted_outcome'],
                'confidence_score' => $result['confidence_score'],
                'model_version' => $result['model_version'],
                
                // Goals predictions - FIXED: Now using realistic values
                'home_goals_prediction' => $result['home_goals_prediction'],
                'away_goals_prediction' => $result['away_goals_prediction'],
                
                // Probabilities - Direct from enhanced model
                'home_win_probability' => $result['home_win_probability'],
                'draw_probability' => $result['draw_probability'],
                'away_win_probability' => $result['away_win_probability'],
                'both_teams_score_probability' => $result['both_teams_score_probability'],
                'over_2_5_probability' => $result['over_2_5_probability'],
                'under_2_5_probability' => $result['under_2_5_probability'],
                'first_half_over_0_5_probability' => $result['first_half_over_0_5_probability'],
                
                // Over/Under predictions
                'over_2_5_prediction' => $result['over_2_5_probability'] > 0.5,
                'over_2_5_confidence' => $result['over_2_5_probability'],
                
                // First half predictions  
                'over_0_5_first_half_prediction' => $result['first_half_over_0_5_probability'] > 0.5,
                'first_half_confidence' => $result['first_half_over_0_5_probability'],
                
                // Both teams score
                'both_teams_score_prediction' => $result['both_teams_score_probability'] > 0.5,
                'both_teams_score_confidence' => $result['both_teams_score_probability'],
                
                // Additional metadata
                'prediction_type' => 'main_outcome',
                'created_at' => now(),
                'updated_at' => now()
            ];
            
        } catch (\Exception $e) {
            Log::error("Exception in Simple Effective Predictor for match {$match->id}: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Improved Both Teams Score prediction logic
     */
    private function predictBothTeamsScore(float $homeExpected, float $awayExpected): bool
    {
        // Both teams score more likely when:
        // 1. Both teams have decent attacking capability (>0.7 goals expected)
        // 2. Neither team is extremely defensive
        // 3. Match is expected to have goals
        
        $totalExpected = $homeExpected + $awayExpected;
        
        // Conservative approach: Both need reasonable goal expectation
        if ($homeExpected >= 0.9 && $awayExpected >= 0.9) {
            return true; // Both teams strong - very likely both score
        }
        
        if ($homeExpected >= 0.7 && $awayExpected >= 0.7 && $totalExpected >= 2.2) {
            return true; // Decent attacks + enough total goals
        }
        
        if ($totalExpected >= 3.0 && min($homeExpected, $awayExpected) >= 0.6) {
            return true; // High-scoring match, even weaker team likely to score
        }
        
        return false; // Conservative: predict NO if not confident
    }
    
    /**
     * Calculate confidence for Both Teams Score prediction
     */
    private function getBothTeamsScoreConfidence(float $homeExpected, float $awayExpected): float
    {
        $totalExpected = $homeExpected + $awayExpected;
        $minExpected = min($homeExpected, $awayExpected);
        $maxExpected = max($homeExpected, $awayExpected);
        
        // Base confidence on the weaker team's scoring ability
        if ($minExpected >= 1.2) {
            $confidence = 0.85; // Very confident both will score
        } elseif ($minExpected >= 1.0) {
            $confidence = 0.75; // Quite confident
        } elseif ($minExpected >= 0.8) {
            $confidence = 0.65; // Moderately confident
        } else {
            $confidence = 0.55; // Low confidence
        }
        
        // Adjust based on total goals expected
        if ($totalExpected >= 3.5) {
            $confidence += 0.1; // High-scoring games favor BTS
        } elseif ($totalExpected <= 2.0) {
            $confidence -= 0.1; // Low-scoring games less likely BTS
        }
        
        // Ensure balance between teams (avoid one-sided games)
        $balance = $minExpected / $maxExpected;
        if ($balance < 0.4) {
            $confidence -= 0.15; // Very unbalanced teams
        } elseif ($balance > 0.7) {
            $confidence += 0.05; // Well-balanced teams
        }
        
        return max(0.50, min(0.90, $confidence));
    }
    
    /**
     * Calculate outcome probabilities from simple effective predictor data
     */
    private function calculateOutcomeProbabilities(array $outcomeData): array
    {
        $predictedOutcome = $outcomeData['predicted_outcome'];
        $confidence = $outcomeData['confidence'] / 100.0;
        $homeGoals = $outcomeData['home_expected_goals'];
        $awayGoals = $outcomeData['away_expected_goals'];
        
        // Calculate probabilities based on expected goals using Poisson distribution logic
        $goalDiff = $homeGoals - $awayGoals;
        
        // Base probabilities using logistic function
        $homeWinProb = 1 / (1 + exp(-$goalDiff * 1.2));
        $awayWinProb = 1 / (1 + exp($goalDiff * 1.2));
        
        // Draw probability increases for closer matches
        $competitiveness = max(0, 1 - abs($goalDiff) * 0.8);
        $drawProb = 0.25 + ($competitiveness * 0.15);
        
        // Ensure minimum probabilities
        $homeWinProb = max(0.05, $homeWinProb);
        $awayWinProb = max(0.05, $awayWinProb);
        $drawProb = max(0.15, $drawProb);
        
        // Normalize probabilities
        $total = $homeWinProb + $drawProb + $awayWinProb;
        
        return [
            'home_win' => round($homeWinProb / $total, 4),
            'draw' => round($drawProb / $total, 4),
            'away_win' => round($awayWinProb / $total, 4)
        ];
    }
    
    /**
     * Calculate both teams score probability based on expected goals
     */
    private function calculateBothTeamsScoreProbability(float $homeExpected, float $awayExpected): float
    {
        // Use Poisson distribution probability that both teams score at least 1 goal
        // P(both score) = P(home >= 1) * P(away >= 1)
        // P(team >= 1) = 1 - P(team = 0) = 1 - e^(-λ)
        
        $homeNoGoalProb = exp(-$homeExpected);
        $awayNoGoalProb = exp(-$awayExpected);
        
        $homeScoreProb = 1 - $homeNoGoalProb;
        $awayScoreProb = 1 - $awayNoGoalProb;
        
        $bothScoreProb = $homeScoreProb * $awayScoreProb;
        
        // Ensure realistic bounds
        return max(0.15, min(0.90, $bothScoreProb));
    }

    /**
     * Get team statistics with prioritized season fallback
     */
    private function getTeamStatistics($team)
    {
        // Priority: 2023 > 2024 > current year
        $prioritySeasons = ['2023', '2024', date('Y')];
        
        foreach ($prioritySeasons as $season) {
            $stats = $team->statistics()->where('season', $season)->first();
            if ($stats) {
                return $stats;
            }
        }
        
        return null; // No statistics found
    }

    /**
     * Calculate match outcome probabilities based on team strengths
     */
    private function calculateMatchOutcomeProbabilities(float $homeStrength, float $awayStrength, float $homeAdvantage): array
    {
        // Apply home advantage
        $adjustedHomeStrength = $homeStrength * (1 + $homeAdvantage);
        
        // Calculate raw probabilities based on strength difference
        $strengthDiff = $adjustedHomeStrength - $awayStrength;
        
        // Use logistic function for probability calculation
        $homeWinProb = PredictionConstants::FALLBACK_HOME_WIN_PROBABILITY + ($strengthDiff * 0.1);
        $awayWinProb = PredictionConstants::FALLBACK_AWAY_WIN_PROBABILITY - ($strengthDiff * 0.08);
        $drawProb = PredictionConstants::FALLBACK_DRAW_PROBABILITY - abs($strengthDiff) * 0.05;
        
        // Normalize probabilities to sum to 1
        $total = $homeWinProb + $awayWinProb + $drawProb;
        
        return [
            'home_win' => max(0.15, min(0.75, $homeWinProb / $total)),
            'away_win' => max(0.15, min(0.75, $awayWinProb / $total)),
            'draw' => max(0.10, min(0.50, $drawProb / $total))
        ];
    }

    /**
     * Determine predicted outcome based on probabilities
     */
    private function determinePredictedOutcome(array $probabilities): string
    {
        $maxProb = max($probabilities);
        
        foreach ($probabilities as $outcome => $probability) {
            if ($probability === $maxProb) {
                return $outcome;
            }
        }
        
        return 'draw'; // Fallback
    }

    /**
     * Calculate confidence score based on prediction certainty
     */
    private function calculateConfidenceScore(array $probabilities, float $homeStrength, float $awayStrength): int
    {
        // Base confidence on probability margin
        $maxProb = max($probabilities);
        $secondMaxProb = max(array_diff($probabilities, [$maxProb]));
        $margin = $maxProb - $secondMaxProb;
        
        // Base confidence from margin (0.1-0.6 margin -> 40-95 confidence)
        $baseConfidence = 40 + ($margin * 100);
        
        // Adjust based on team strength difference (more confidence when teams clearly different)
        $strengthDiff = abs($homeStrength - $awayStrength);
        $strengthBonus = min(10, $strengthDiff * 5);
        
        $confidence = $baseConfidence + $strengthBonus;
        
        return (int) max(PredictionConstants::LOW_CONFIDENCE_THRESHOLD, 
                        min(PredictionConstants::MAX_CONFIDENCE_SCORE, $confidence));
    }
}