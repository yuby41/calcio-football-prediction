<?php

namespace App\Console\Commands;

use App\Models\MatchPrediction;
use Illuminate\Console\Command;

class EvaluateAllPredictionTypes extends Command
{
    protected $signature = 'predictions:evaluate-all-types {--model=* : Specific model versions to evaluate}';
    protected $description = 'Evaluate accuracy for ALL prediction types: outcome, over/under, both teams score, first half';

    public function handle()
    {
        $this->info('📊 EVALUATING ALL PREDICTION TYPES ACCURACY');
        $this->info('==========================================');

        $models = $this->option('model');
        
        if (empty($models)) {
            // Focus on recent models
            $models = ['simple_effective_v1.0', 'simple_effective_v1.1', '3.0.0-enhanced-outcomes', 'enhanced_fallback_2.4_fixed_seasons'];
        }

        foreach ($models as $modelVersion) {
            $this->line('');
            $this->info("🤖 Model: {$modelVersion}");
            $this->line('==========================================');
            
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
            
            $this->evaluateMainOutcome($predictions);
            $this->evaluateOver25($predictions);
            $this->evaluateBothTeamsScore($predictions);
            $this->evaluateFirstHalfOver05($predictions);
        }
        
        return 0;
    }
    
    private function evaluateMainOutcome($predictions)
    {
        $this->line('🎯 MAIN OUTCOME (Home/Draw/Away)');
        $this->line('--------------------------------');
        
        $correct = 0;
        $total = 0;
        
        foreach ($predictions as $pred) {
            $match = $pred->match;
            $actualOutcome = $match->home_goals > $match->away_goals ? 'home_win' : 
                           ($match->away_goals > $match->home_goals ? 'away_win' : 'draw');
            
            if ($pred->predicted_outcome === $actualOutcome) {
                $correct++;
            }
            $total++;
        }
        
        $accuracy = $total > 0 ? round(($correct / $total) * 100, 2) : 0;
        $this->displayAccuracy('Main Outcome', $accuracy, $correct, $total);
    }
    
    private function evaluateOver25($predictions)
    {
        $this->line('');
        $this->line('⚽ OVER/UNDER 2.5 GOALS');
        $this->line('----------------------');
        
        $correct = 0;
        $total = 0;
        $withPrediction = 0;
        
        foreach ($predictions as $pred) {
            $match = $pred->match;
            $totalGoals = $match->home_goals + $match->away_goals;
            $actualOver25 = $totalGoals > 2.5;
            
            // Check if this prediction type exists
            if ($pred->over_2_5_prediction !== null) {
                $withPrediction++;
                if ($pred->over_2_5_prediction == $actualOver25) {
                    $correct++;
                }
            }
            $total++;
        }
        
        if ($withPrediction > 0) {
            $accuracy = round(($correct / $withPrediction) * 100, 2);
            $this->displayAccuracy('Over 2.5', $accuracy, $correct, $withPrediction);
            
            // Show actual distribution
            $actualOver = $predictions->filter(function($pred) {
                return ($pred->match->home_goals + $pred->match->away_goals) > 2.5;
            })->count();
            $this->line("  Actual Over 2.5 rate: " . round(($actualOver / $total) * 100, 1) . "% ($actualOver/$total)");
        } else {
            $this->warn('  No Over 2.5 predictions found');
        }
    }
    
    private function evaluateBothTeamsScore($predictions)
    {
        $this->line('');
        $this->line('⚽⚽ BOTH TEAMS SCORE');
        $this->line('-------------------');
        
        $correct = 0;
        $total = 0;
        $withPrediction = 0;
        
        foreach ($predictions as $pred) {
            $match = $pred->match;
            $actualBTS = ($match->home_goals > 0) && ($match->away_goals > 0);
            
            // Check if this prediction type exists
            if ($pred->both_teams_score_prediction !== null) {
                $withPrediction++;
                if ($pred->both_teams_score_prediction == $actualBTS) {
                    $correct++;
                }
            }
            $total++;
        }
        
        if ($withPrediction > 0) {
            $accuracy = round(($correct / $withPrediction) * 100, 2);
            $this->displayAccuracy('Both Teams Score', $accuracy, $correct, $withPrediction);
            
            // Show actual distribution  
            $actualBTS = $predictions->filter(function($pred) {
                return ($pred->match->home_goals > 0) && ($pred->match->away_goals > 0);
            })->count();
            $this->line("  Actual BTS rate: " . round(($actualBTS / $total) * 100, 1) . "% ($actualBTS/$total)");
        } else {
            $this->warn('  No Both Teams Score predictions found');
        }
    }
    
    private function evaluateFirstHalfOver05($predictions)
    {
        $this->line('');
        $this->line('🏃 FIRST HALF OVER 0.5');
        $this->line('----------------------');
        
        $correct = 0;
        $total = 0;
        $withPrediction = 0;
        $withFirstHalfData = 0;
        
        foreach ($predictions as $pred) {
            $match = $pred->match;
            
            // Only evaluate if we have first half data
            if ($match->home_goals_first_half !== null && $match->away_goals_first_half !== null) {
                $withFirstHalfData++;
                $totalFirstHalfGoals = $match->home_goals_first_half + $match->away_goals_first_half;
                $actualOver05FH = $totalFirstHalfGoals > 0.5;
                
                // Check if this prediction type exists
                if ($pred->over_0_5_first_half_prediction !== null) {
                    $withPrediction++;
                    if ($pred->over_0_5_first_half_prediction == $actualOver05FH) {
                        $correct++;
                    }
                }
            }
            $total++;
        }
        
        if ($withPrediction > 0) {
            $accuracy = round(($correct / $withPrediction) * 100, 2);
            $this->displayAccuracy('First Half Over 0.5', $accuracy, $correct, $withPrediction);
            
            // Show actual distribution
            if ($withFirstHalfData > 0) {
                $actualOver05FH = $predictions->filter(function($pred) {
                    $match = $pred->match;
                    return $match->home_goals_first_half !== null && 
                           $match->away_goals_first_half !== null &&
                           ($match->home_goals_first_half + $match->away_goals_first_half) > 0.5;
                })->count();
                $this->line("  Actual FH Over 0.5 rate: " . round(($actualOver05FH / $withFirstHalfData) * 100, 1) . "% ($actualOver05FH/$withFirstHalfData)");
            }
        } else {
            $this->warn('  No First Half Over 0.5 predictions found');
        }
        
        $this->line("  Matches with first half data: $withFirstHalfData/$total");
    }
    
    private function displayAccuracy($type, $accuracy, $correct, $total)
    {
        if ($accuracy >= 60) {
            $this->info("  ✅ $type: {$accuracy}% ($correct/$total) - EXCELLENT");
        } elseif ($accuracy >= 52) {
            $this->comment("  ✅ $type: {$accuracy}% ($correct/$total) - GOOD");
        } elseif ($accuracy >= 45) {
            $this->comment("  ⚠️  $type: {$accuracy}% ($correct/$total) - ACCEPTABLE");
        } else {
            $this->error("  ❌ $type: {$accuracy}% ($correct/$total) - POOR");
        }
    }
}