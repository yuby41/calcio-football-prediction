<?php

namespace App\Console\Commands;

use App\Models\FootballMatch;
use App\Models\MatchPrediction;
use Illuminate\Console\Command;

class UpdatePredictionAccuracyRetroactive extends Command
{
    protected $signature = 'predictions:update-accuracy-retroactive';
    protected $description = 'Update prediction accuracy for all finished matches retroactively';

    public function handle()
    {
        $this->info('Updating prediction accuracy for finished matches...');

        // Get all finished matches with predictions that haven't been evaluated
        $finishedMatches = FootballMatch::with(['prediction'])
            ->where('status', 'finished')
            ->whereHas('prediction')
            ->whereNotNull('home_goals')
            ->whereNotNull('away_goals')
            ->get();

        $updated = 0;
        $total = $finishedMatches->count();

        $this->info("Found {$total} finished matches to evaluate.");

        foreach ($finishedMatches as $match) {
            $prediction = $match->prediction;
            
            // Determine actual result
            $actualResult = $this->determineMatchResult($match);
            
            // Update prediction accuracy
            $isCorrect = ($prediction->predicted_outcome === $actualResult);
            
            // Update both teams score accuracy
            $bothTeamsScored = ($match->home_goals > 0 && $match->away_goals > 0);
            $predictedBothTeamsScore = ($prediction->both_teams_score_probability ?? 0) > 0.5;
            $bothTeamsScoreCorrect = ($bothTeamsScored === $predictedBothTeamsScore);
            
            // Update over/under accuracy
            $totalGoals = $match->home_goals + $match->away_goals;
            $isOver25 = $totalGoals > 2.5;
            $predictedOver25 = ($prediction->over_2_5_probability ?? 0) > ($prediction->under_2_5_probability ?? 1);
            $overUnderCorrect = ($isOver25 === $predictedOver25);
            
            // Update the prediction
            $prediction->update([
                'is_correct' => $isCorrect,
                'both_teams_score_correct' => $bothTeamsScoreCorrect,
                'over_under_correct' => $overUnderCorrect,
            ]);
            
            $updated++;
            
            if ($updated % 50 === 0) {
                $this->info("Updated {$updated}/{$total} predictions...");
            }
        }

        $this->info("Successfully updated {$updated} predictions!");
        
        return Command::SUCCESS;
    }
    
    private function determineMatchResult(FootballMatch $match): string
    {
        if ($match->home_goals > $match->away_goals) {
            return 'home_win';
        } elseif ($match->home_goals < $match->away_goals) {
            return 'away_win';
        } else {
            return 'draw';
        }
    }
}