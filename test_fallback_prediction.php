<?php

// Simulate the fallback prediction logic
function createFallbackPrediction($homeTeamId, $awayTeamId) {
    // Simulate different team stats (this is what should vary)
    $homeWinRate = 0.4 + (($homeTeamId % 10) / 20); // 0.4 to 0.85
    $awayWinRate = 0.3 + (($awayTeamId % 10) / 25); // 0.3 to 0.7
    
    $homeGoalsAvg = 1.2 + (($homeTeamId % 5) / 10); // 1.2 to 1.7
    $awayGoalsAvg = 1.0 + (($awayTeamId % 5) / 10); // 1.0 to 1.5
    
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
    $homeGoals = max(0.5, $homeGoalsAvg + (rand(-5, 5) / 10));
    $awayGoals = max(0.5, $awayGoalsAvg + (rand(-5, 5) / 10));
    
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
        'model_version' => 'fallback_test',
    ];
}

echo "Testing fallback prediction logic:\n\n";

// Test with different team IDs to see variation
$testCases = [
    [1, 2],   // Man United vs Newcastle
    [19, 2],  // Aston Villa vs Newcastle  
    [5, 10],  // Different teams
    [15, 25], // Different teams
    [30, 40], // Different teams
];

foreach ($testCases as $i => [$homeId, $awayId]) {
    echo "Test " . ($i + 1) . " - Team {$homeId} vs Team {$awayId}:\n";
    
    $prediction = createFallbackPrediction($homeId, $awayId);
    
    echo "  Outcome: {$prediction['predicted_outcome']}\n";
    echo "  Probabilities: H:" . ($prediction['home_win_probability'] * 100) . "% ";
    echo "D:" . ($prediction['draw_probability'] * 100) . "% ";
    echo "A:" . ($prediction['away_win_probability'] * 100) . "%\n";
    echo "  Goals: {$prediction['home_goals_prediction']} - {$prediction['away_goals_prediction']}\n";
    echo "  Confidence: " . ($prediction['confidence_score'] * 100) . "%\n";
    echo "  Model: {$prediction['model_version']}\n\n";
}