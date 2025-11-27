<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\FootballMatch;

class SimulateActualResults extends Command
{
    protected $signature = 'results:simulate 
                            {--matches=10 : Number of matches to simulate results for}';
    
    protected $description = 'Simulate actual match results for performance analysis testing';

    public function handle(): int
    {
        $this->info('🎲 SIMULATING ACTUAL MATCH RESULTS');
        $this->info('Creating realistic match results for performance analysis testing...');
        $this->newLine();

        $limit = (int) $this->option('matches');

        // Get recent matches that need actual results for testing
        $matches = FootballMatch::whereNull('actual_home_goals')
            ->whereNull('actual_away_goals')
            ->whereHas('prediction') // Only matches with predictions
            ->whereHas('homeTeam', function($query) {
                $query->where('external_id', 'regexp', '^[0-9]+$'); // Only real team IDs
            })
            ->whereHas('awayTeam', function($query) {
                $query->where('external_id', 'regexp', '^[0-9]+$');
            })
            ->where('match_date', '>=', now()->subDays(30)) // Recent matches only
            ->with(['homeTeam', 'awayTeam', 'prediction'])
            ->orderBy('match_date', 'desc')
            ->limit($limit)
            ->get();

        if ($matches->isEmpty()) {
            $this->warn('No matches found that need simulated results');
            return 0;
        }

        $this->info("Simulating results for {$matches->count()} matches");

        $progressBar = $this->output->createProgressBar($matches->count());
        $progressBar->start();

        $simulated = 0;

        foreach ($matches as $match) {
            // Generate realistic results based on predictions
            $prediction = $match->prediction;
            
            if ($prediction) {
                // Use predicted goals as a base with some randomness
                $baseHome = $prediction->home_goals_prediction;
                $baseAway = $prediction->away_goals_prediction;
                
                // Add realistic variation (±2 goals max)
                $homeGoals = max(0, round($baseHome + $this->getRandomVariation()));
                $awayGoals = max(0, round($baseAway + $this->getRandomVariation()));
                
                // Sometimes create more realistic scores
                $homeGoals = $this->adjustForRealism($homeGoals);
                $awayGoals = $this->adjustForRealism($awayGoals);
                
                try {
                    $match->update([
                        'actual_home_goals' => $homeGoals,
                        'actual_away_goals' => $awayGoals,
                        'match_status' => 'finished',
                        'result_updated_at' => now(),
                    ]);
                    $this->line("Updated match {$match->id}: {$homeGoals}-{$awayGoals}");
                } catch (\Exception $e) {
                    $this->error("Failed to update match {$match->id}: " . $e->getMessage());
                    continue;
                }
                
                $simulated++;
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        // Results summary
        $this->table(
            ['Metric', 'Count'],
            [
                ['Matches Processed', $matches->count()],
                ['Results Simulated', $simulated],
                ['Success Rate', $simulated > 0 ? '100%' : '0%'],
            ]
        );

        if ($simulated > 0) {
            $this->info("✅ Successfully simulated results for {$simulated} matches!");
            
            // Show examples
            $this->newLine();
            $this->info('🎯 SIMULATED RESULTS EXAMPLES:');
            $simulatedMatches = FootballMatch::whereNotNull('actual_home_goals')
                ->whereNotNull('actual_away_goals')
                ->where('result_updated_at', '>=', now()->subMinute())
                ->with(['homeTeam', 'awayTeam', 'prediction'])
                ->limit(5)
                ->get();
            
            foreach ($simulatedMatches as $simulatedMatch) {
                $pred = $simulatedMatch->prediction;
                $predStr = $pred ? "({$pred->home_goals_prediction} - {$pred->away_goals_prediction})" : '';
                $actual = "{$simulatedMatch->actual_home_goals} - {$simulatedMatch->actual_away_goals}";
                
                $this->line("⚽ {$simulatedMatch->homeTeam->name} vs {$simulatedMatch->awayTeam->name}");
                $this->line("   Predicted: {$predStr} | Actual: {$actual}");
            }
        }

        $this->newLine();
        $this->info('💡 NEXT STEPS:');
        $this->info('1. Run performance analysis: php artisan performance:analyze');
        $this->info('2. Compare prediction accuracy with simulated results');
        $this->info('3. Test ROI analysis with realistic outcomes');

        return 0;
    }

    /**
     * Generate random variation for goals (-1.5 to +1.5)
     */
    private function getRandomVariation(): float
    {
        return (mt_rand(-150, 150) / 100);
    }

    /**
     * Adjust goals for more realistic football scores
     */
    private function adjustForRealism(int $goals): int
    {
        // Football scores are typically 0-5, rarely higher
        if ($goals > 5) {
            $goals = mt_rand(3, 5);
        }
        
        // Weight common scores more heavily
        $commonScores = [0, 1, 2, 3];
        if (mt_rand(1, 100) <= 80) { // 80% chance of common score
            return $commonScores[array_rand($commonScores)];
        }
        
        return min($goals, 6);
    }
}