<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->boot();

use App\Models\FootballMatch;
use App\Services\PredictionService;

// Get a test match
$match = FootballMatch::with(['homeTeam', 'awayTeam'])->find(2);

if (!$match) {
    echo "Match not found\n";
    exit;
}

echo "Testing prediction for: {$match->homeTeam->name} vs {$match->awayTeam->name}\n";

$predictionService = new PredictionService();

// Delete existing prediction
if ($match->prediction) {
    $match->prediction->delete();
    echo "Deleted existing prediction\n";
}

// Generate new prediction
$success = $predictionService->predictMatch($match);

if ($success) {
    $prediction = $match->fresh()->prediction;
    if ($prediction) {
        echo "✓ Prediction generated successfully!\n";
        echo "  Outcome: {$prediction->predicted_outcome}\n";
        echo "  Home Win: " . number_format($prediction->home_win_probability * 100, 1) . "%\n";
        echo "  Draw: " . number_format($prediction->draw_probability * 100, 1) . "%\n";
        echo "  Away Win: " . number_format($prediction->away_win_probability * 100, 1) . "%\n";
        echo "  Goals: {$prediction->home_goals_prediction} - {$prediction->away_goals_prediction}\n";
        echo "  Confidence: " . number_format($prediction->confidence_score * 100, 1) . "%\n";
        echo "  Model: {$prediction->model_version}\n";
    } else {
        echo "✗ Prediction was not saved\n";
    }
} else {
    echo "✗ Failed to generate prediction\n";
}