<?php

namespace App\Console\Commands;

use App\Models\FootballMatch;
use App\Models\MatchPrediction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixPredictionAccuracy extends Command
{
    protected $signature = 'predictions:fix-accuracy {--dry-run : Show what would be fixed without making changes}';
    protected $description = 'Fix incorrect prediction accuracy markings';

    public function handle()
    {
        $dryRun = $this->option('dry-run');
        
        $this->info('🔍 Analyzing prediction accuracy issues...');
        
        // Get all finished matches with predictions
        $matches = FootballMatch::with('prediction')
            ->where('status', 'finished')
            ->whereHas('prediction')
            ->whereNotNull('home_goals')
            ->whereNotNull('away_goals')
            ->get();

        $errors = 0;
        $fixed = 0;

        foreach ($matches as $match) {
            $prediction = $match->prediction;
            
            // Determine actual result
            $actualResult = $this->determineMatchResult($match);
            
            // Check if prediction accuracy is correct
            $shouldBeCorrect = ($prediction->predicted_outcome === $actualResult);
            $currentlyMarked = $prediction->is_correct;
            
            if ($shouldBeCorrect !== $currentlyMarked) {
                $errors++;
                
                $this->line(sprintf(
                    '❌ Match %d: %s vs %s (%d-%d) | Predicted: %s | Actual: %s | Marked: %s | Should be: %s',
                    $match->id,
                    $match->homeTeam->name ?? 'Unknown',
                    $match->awayTeam->name ?? 'Unknown',
                    $match->home_goals,
                    $match->away_goals,
                    $prediction->predicted_outcome,
                    $actualResult,
                    $currentlyMarked ? 'CORRECT' : 'INCORRECT',
                    $shouldBeCorrect ? 'CORRECT' : 'INCORRECT'
                ));
                
                if (!$dryRun) {
                    $prediction->is_correct = $shouldBeCorrect;
                    
                    // Also fix other prediction types while we're at it
                    $this->fixOtherPredictionTypes($match, $prediction);
                    
                    $prediction->save();
                    $fixed++;
                }
            }
        }
        
        $this->line('');
        
        if ($errors === 0) {
            $this->info('✅ No prediction accuracy errors found!');
        } else {
            if ($dryRun) {
                $this->warn("❌ Found {$errors} prediction accuracy errors.");
                $this->info('Run without --dry-run to fix them.');
            } else {
                $this->info("✅ Fixed {$fixed} prediction accuracy errors!");
                
                // Update statistics after fixing
                $this->info('🔄 Updating statistics...');
                $this->call('statistics:update');
            }
        }
        
        return 0;
    }
    
    private function determineMatchResult($match): string
    {
        if ($match->home_goals > $match->away_goals) {
            return 'home_win';
        } elseif ($match->home_goals < $match->away_goals) {
            return 'away_win';
        } else {
            return 'draw';
        }
    }
    
    private function fixOtherPredictionTypes($match, $prediction): void
    {
        // Fix Both Teams Score
        if (!is_null($prediction->both_teams_score_probability)) {
            $actualBothScored = $match->home_goals > 0 && $match->away_goals > 0;
            $predictedBothScore = $prediction->both_teams_score_probability > 0.5;
            $prediction->both_teams_score_correct = $actualBothScored === $predictedBothScore;
        }
        
        // Fix Over/Under 2.5
        if (!is_null($prediction->over_2_5_probability)) {
            $totalGoals = $match->home_goals + $match->away_goals;
            $actualOver25 = $totalGoals > 2.5;
            $predictedOver25 = $prediction->over_2_5_probability > 0.5;
            $prediction->over_under_correct = $actualOver25 === $predictedOver25;
        }
        
        // Fix First Half Over 0.5 (if we have first half data)
        if (!is_null($prediction->first_half_over_0_5_probability)) {
            if (!is_null($match->home_goals_first_half) && !is_null($match->away_goals_first_half)) {
                $firstHalfGoals = $match->home_goals_first_half + $match->away_goals_first_half;
                $actualOver05FH = $firstHalfGoals > 0.5;
                $predictedOver05FH = $prediction->first_half_over_0_5_probability > 0.5;
                $prediction->first_half_over_0_5_correct = $actualOver05FH === $predictedOver05FH;
            }
        }
    }
}