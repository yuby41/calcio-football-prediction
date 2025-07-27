<?php

namespace App\Console\Commands;

use App\Models\FootballMatch;
use App\Services\PredictionService;
use Illuminate\Console\Command;

class TestPredictions extends Command
{
    protected $signature = 'predictions:test';
    protected $description = 'Test prediction generation for upcoming matches';

    public function handle()
    {
        $this->info('Testing prediction generation...');

        // Get a few upcoming matches
        $matches = FootballMatch::with(['homeTeam', 'awayTeam'])
            ->where('status', 'scheduled')
            ->limit(3)
            ->get();

        if ($matches->isEmpty()) {
            $this->error('No scheduled matches found to test predictions.');
            return Command::FAILURE;
        }

        $predictionService = new PredictionService();

        foreach ($matches as $match) {
            $this->info("Testing prediction for: {$match->homeTeam->name} vs {$match->awayTeam->name}");
            
            // Force regenerate prediction
            if ($match->prediction) {
                $match->prediction->delete();
            }
            
            $success = $predictionService->predictMatch($match);
            
            if ($success) {
                $prediction = $match->fresh()->prediction;
                if ($prediction) {
                    $this->info("✓ Prediction generated:");
                    $this->info("  Outcome: {$prediction->predicted_outcome}");
                    $this->info("  Home Win: " . number_format($prediction->home_win_probability * 100, 1) . "%");
                    $this->info("  Draw: " . number_format($prediction->draw_probability * 100, 1) . "%");
                    $this->info("  Away Win: " . number_format($prediction->away_win_probability * 100, 1) . "%");
                    $this->info("  Goals: {$prediction->home_goals_prediction} - {$prediction->away_goals_prediction}");
                    $this->info("  Model: {$prediction->model_version}");
                } else {
                    $this->error("✗ Prediction was not saved properly");
                }
            } else {
                $this->error("✗ Failed to generate prediction");
            }
            
            $this->info('---');
        }

        return Command::SUCCESS;
    }
}