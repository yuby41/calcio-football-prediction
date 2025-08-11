<?php

namespace App\Console\Commands;

use App\Models\MatchPrediction;
use Illuminate\Console\Command;

class EvaluateModelAccuracy extends Command
{
    protected $signature = 'predictions:evaluate-accuracy {--model=* : Specific model versions to evaluate}';
    protected $description = 'Evaluate prediction accuracy for different models';

    public function handle()
    {
        $this->info('📊 EVALUATING PREDICTION MODEL ACCURACY');
        $this->info('=====================================');

        $models = $this->option('model');
        
        if (empty($models)) {
            // Get all model versions
            $models = MatchPrediction::selectRaw('DISTINCT model_version')
                ->whereNotNull('model_version')
                ->whereHas('match', function($q) {
                    $q->where('status', 'finished')
                      ->whereNotNull('home_goals')
                      ->whereNotNull('away_goals');
                })
                ->pluck('model_version')
                ->toArray();
        }

        $results = [];
        
        foreach ($models as $modelVersion) {
            $this->line('');
            $this->info("🤖 Model: {$modelVersion}");
            $this->line('-----------------------------------');
            
            $predictions = MatchPrediction::where('model_version', $modelVersion)
                ->whereHas('match', function($q) {
                    $q->where('status', 'finished')
                      ->whereNotNull('home_goals')
                      ->whereNotNull('away_goals');
                })
                ->with('match')
                ->get();
                
            if ($predictions->count() === 0) {
                $this->warn('No finished matches found for this model');
                continue;
            }
            
            $correct = 0;
            $total = 0;
            $outcomes = ['home_win' => 0, 'away_win' => 0, 'draw' => 0];
            $correctByOutcome = ['home_win' => 0, 'away_win' => 0, 'draw' => 0];
            
            foreach ($predictions as $pred) {
                $match = $pred->match;
                
                $actualOutcome = $match->home_goals > $match->away_goals ? 'home_win' : 
                               ($match->away_goals > $match->home_goals ? 'away_win' : 'draw');
                
                $outcomes[$actualOutcome]++;
                
                if ($pred->predicted_outcome === $actualOutcome) {
                    $correct++;
                    $correctByOutcome[$actualOutcome]++;
                }
                
                $total++;
            }
            
            $accuracy = round(($correct / $total) * 100, 2);
            
            $this->line("Total predictions: {$total}");
            $this->line("Correct predictions: {$correct}");
            
            if ($accuracy >= 52) {
                $this->info("✅ Accuracy: {$accuracy}% (GOOD)");
            } elseif ($accuracy >= 45) {
                $this->comment("⚠️  Accuracy: {$accuracy}% (ACCEPTABLE)");
            } else {
                $this->error("❌ Accuracy: {$accuracy}% (POOR)");
            }
            
            // Breakdown by outcome
            $this->line('');
            $this->line('Accuracy by outcome:');
            foreach (['home_win', 'away_win', 'draw'] as $outcome) {
                if ($outcomes[$outcome] > 0) {
                    $outcomeAccuracy = round(($correctByOutcome[$outcome] / $outcomes[$outcome]) * 100, 1);
                    $this->line("  {$outcome}: {$outcomeAccuracy}% ({$correctByOutcome[$outcome]}/{$outcomes[$outcome]})");
                }
            }
            
            // Confidence analysis
            $avgConfidence = round($predictions->avg('confidence_score') * 100, 1);
            $this->line("Average confidence: {$avgConfidence}%");
            
            $results[$modelVersion] = [
                'accuracy' => $accuracy,
                'total' => $total,
                'correct' => $correct,
                'avg_confidence' => $avgConfidence
            ];
        }
        
        // Summary comparison
        if (count($results) > 1) {
            $this->line('');
            $this->info('📈 MODEL COMPARISON SUMMARY');
            $this->info('===========================');
            
            $bestModel = '';
            $bestAccuracy = 0;
            
            foreach ($results as $model => $data) {
                $status = $data['accuracy'] >= 52 ? '✅' : ($data['accuracy'] >= 45 ? '⚠️' : '❌');
                $this->line("{$status} {$model}: {$data['accuracy']}% ({$data['correct']}/{$data['total']})");
                
                if ($data['accuracy'] > $bestAccuracy) {
                    $bestAccuracy = $data['accuracy'];
                    $bestModel = $model;
                }
            }
            
            $this->line('');
            $this->info("🏆 Best performing model: {$bestModel} ({$bestAccuracy}%)");
        }
        
        return 0;
    }
}