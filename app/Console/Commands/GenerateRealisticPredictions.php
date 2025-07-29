<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\FootballMatch;
use App\Models\Team;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class GenerateRealisticPredictions extends Command
{
    protected $signature = 'predictions:generate-realistic';
    protected $description = 'Generate realistic predictions based on team statistics';

    public function handle()
    {
        $this->info('Generating realistic predictions...');

        // Get matches without real predictions (using fallback model)
        $matches = FootballMatch::with(['homeTeam', 'awayTeam', 'prediction'])
            ->whereHas('prediction', function($query) {
                $query->where('model_version', 'fallback_1.0');
            })
            ->where('status', '!=', 'finished')
            ->get();

        $this->info("Found {$matches->count()} matches to update with realistic predictions");

        $progressBar = $this->output->createProgressBar($matches->count());

        foreach ($matches as $match) {
            $this->generateRealisticPrediction($match);
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->info("\n✅ Realistic predictions generated successfully!");

        return Command::SUCCESS;
    }

    private function generateRealisticPrediction($match)
    {
        // Get team statistics
        $homeStats = $this->getTeamStats($match->homeTeam);
        $awayStats = $this->getTeamStats($match->awayTeam);

        // Calculate relative strength
        $homeStrength = $homeStats['strength'] + 0.1; // Home advantage
        $awayStrength = $awayStats['strength'];
        $totalStrength = $homeStrength + $awayStrength;

        // Calculate probabilities based on relative strength
        $homeWinProb = $homeStrength / $totalStrength;
        $awayWinProb = $awayStrength / $totalStrength;
        
        // Adjust for draw probability (stronger teams = less draws)
        $strengthDiff = abs($homeStrength - $awayStrength);
        $drawProb = max(0.15, 0.35 - ($strengthDiff * 0.3));
        
        // Normalize probabilities
        $total = $homeWinProb + $awayWinProb + $drawProb;
        $homeWinProb = $homeWinProb / $total;
        $awayWinProb = $awayWinProb / $total;
        $drawProb = $drawProb / $total;

        // Predict goals based on attacking/defensive stats
        $homeGoals = max(0.5, min(4.0, $homeStats['attack'] - $awayStats['defense'] + rand(0, 10) / 10));
        $awayGoals = max(0.5, min(4.0, $awayStats['attack'] - $homeStats['defense'] + rand(0, 10) / 10));

        // Calculate other probabilities
        $totalGoals = $homeGoals + $awayGoals;
        $over25Prob = $totalGoals > 2.5 ? min(0.85, 0.4 + ($totalGoals - 2.5) * 0.2) : max(0.15, 0.4 - (2.5 - $totalGoals) * 0.15);
        $bothTeamsScoreProb = ($homeGoals > 0.8 && $awayGoals > 0.8) ? max(0.6, min(0.9, ($homeGoals + $awayGoals) / 4)) : min(0.4, ($homeGoals + $awayGoals) / 6);

        // Determine predicted outcome
        $maxProb = max($homeWinProb, $drawProb, $awayWinProb);
        if ($maxProb === $homeWinProb) {
            $predictedOutcome = 'home_win';
        } elseif ($maxProb === $awayWinProb) {
            $predictedOutcome = 'away_win';
        } else {
            $predictedOutcome = 'draw';
        }

        // Calculate confidence
        $confidence = max(0.4, min(0.95, $maxProb + rand(0, 20) / 100));

        // Update prediction
        DB::table('match_predictions')
            ->where('match_id', $match->id)
            ->update([
                'home_goals_prediction' => round($homeGoals, 1),
                'away_goals_prediction' => round($awayGoals, 1),
                'home_win_probability' => round($homeWinProb, 4),
                'draw_probability' => round($drawProb, 4),
                'away_win_probability' => round($awayWinProb, 4),
                'both_teams_score_probability' => round($bothTeamsScoreProb, 4),
                'over_2_5_probability' => round($over25Prob, 4),
                'under_2_5_probability' => round(1 - $over25Prob, 4),
                'predicted_outcome' => $predictedOutcome,
                'confidence_score' => round($confidence, 4),
                'model_version' => 'realistic_v1.0',
                'features_used' => json_encode(['team_stats', 'home_advantage', 'attacking_defense']),
                'predicted_at' => now(),
                'updated_at' => now()
            ]);
    }

    private function getTeamStats($team)
    {
        // Get team's recent matches (last 10 matches)
        $recentMatches = FootballMatch::where('status', 'finished')
            ->where(function($query) use ($team) {
                $query->where('home_team_id', $team->id)
                      ->orWhere('away_team_id', $team->id);
            })
            ->whereNotNull('home_goals')
            ->whereNotNull('away_goals')
            ->orderBy('match_date', 'desc')
            ->limit(10)
            ->get();

        if ($recentMatches->isEmpty()) {
            // Return average stats if no data
            return [
                'strength' => 0.5,
                'attack' => 1.3,
                'defense' => 1.3
            ];
        }

        $totalGoalsFor = 0;
        $totalGoalsAgainst = 0;
        $wins = 0;
        $matches = $recentMatches->count();

        foreach ($recentMatches as $match) {
            if ($match->home_team_id == $team->id) {
                // Team was home
                $totalGoalsFor += $match->home_goals;
                $totalGoalsAgainst += $match->away_goals;
                if ($match->home_goals > $match->away_goals) $wins++;
            } else {
                // Team was away
                $totalGoalsFor += $match->away_goals;
                $totalGoalsAgainst += $match->home_goals;
                if ($match->away_goals > $match->home_goals) $wins++;
            }
        }

        $avgGoalsFor = $totalGoalsFor / $matches;
        $avgGoalsAgainst = $totalGoalsAgainst / $matches;
        $winRate = $wins / $matches;

        return [
            'strength' => max(0.1, min(0.9, $winRate + 0.1)),
            'attack' => max(0.5, min(3.5, $avgGoalsFor)),
            'defense' => max(0.5, min(3.0, 2.5 - $avgGoalsAgainst)) // Lower goals against = better defense
        ];
    }
}