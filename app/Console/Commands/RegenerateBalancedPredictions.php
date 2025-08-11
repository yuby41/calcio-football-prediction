<?php

namespace App\Console\Commands;

use App\Models\FootballMatch;
use App\Models\MatchPrediction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class RegenerateBalancedPredictions extends Command
{
    protected $signature = 'predictions:regenerate-balanced
                           {--limit=50 : Limit number of matches to regenerate}
                           {--dry-run : Show what would be regenerated}
                           {--force : Force regeneration even for recent predictions}';
    
    protected $description = 'Regenerate predictions with improved balanced ML model';

    public function handle()
    {
        $limit = $this->option('limit');
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');
        
        $this->info($dryRun ? '🔍 DRY RUN: Regenerating balanced predictions...' : '🤖 Regenerating balanced predictions...');
        
        // Get matches for regeneration
        $query = FootballMatch::whereHas('homeTeam')
            ->whereHas('awayTeam')
            ->where('status', 'finished');
            
        if (!$force) {
            $query->where('match_date', '>=', now()->subDays(30)); // Only recent matches
        }
        
        $matches = $query->orderBy('match_date', 'desc')
            ->limit($limit)
            ->get();
        
        if ($matches->isEmpty()) {
            $this->info('No matches found for regeneration');
            return 0;
        }
        
        $this->info("Found {$matches->count()} matches for regeneration");
        
        $stats = [
            'processed' => 0,
            'outcomes' => ['home_win' => 0, 'away_win' => 0, 'draw' => 0],
            'errors' => 0
        ];
        
        $progressBar = $this->output->createProgressBar($matches->count());
        $progressBar->start();
        
        foreach ($matches as $match) {
            try {
                // Call ML predictor directly
                $process = new Process([
                    'python', 
                    base_path('ml/enhanced_football_predictor.py'), 
                    'predict', 
                    $match->home_team_id, 
                    $match->away_team_id
                ]);
                
                $process->setWorkingDirectory(base_path());
                $process->setEnv(['PATH' => base_path('ml_env/bin:') . env('PATH')]);
                $process->run();
                
                if (!$process->isSuccessful()) {
                    $this->line("\n❌ ML Error for match {$match->id}: " . $process->getErrorOutput());
                    $stats['errors']++;
                    continue;
                }
                
                $mlResult = json_decode($process->getOutput(), true);
                
                if (!$mlResult || !isset($mlResult['predicted_outcome'])) {
                    $this->line("\n❌ Invalid ML output for match {$match->id}");
                    $stats['errors']++;
                    continue;
                }
                
                if (!$dryRun) {
                    // Update or create prediction
                    $prediction = MatchPrediction::where('match_id', $match->id)->first();
                    if (!$prediction) {
                        $prediction = new MatchPrediction();
                        $prediction->match_id = $match->id;
                    }
                    
                    // Store new prediction data
                    $prediction->predicted_outcome = $mlResult['predicted_outcome'];
                    $prediction->home_win_probability = $mlResult['home_win_probability'];
                    $prediction->draw_probability = $mlResult['draw_probability'];
                    $prediction->away_win_probability = $mlResult['away_win_probability'];
                    $prediction->confidence_score = $mlResult['confidence_score'];
                    $prediction->model_version = $mlResult['model_version'] ?? '3.0.1-balanced';
                    $prediction->over_2_5_probability = $mlResult['over_2_5_probability'] ?? null;
                    $prediction->first_half_over_0_5_probability = $mlResult['first_half_over_0_5_probability'] ?? null;
                    
                    // Calculate accuracy for finished matches
                    if ($match->status === 'finished') {
                        $actualOutcome = $this->getActualOutcome($match);
                        $prediction->is_correct = ($prediction->predicted_outcome === $actualOutcome);
                    }
                    
                    $prediction->save();
                }
                
                $stats['outcomes'][$mlResult['predicted_outcome']]++;
                $stats['processed']++;
                
                if ($stats['processed'] % 10 === 0) {
                    $this->line(sprintf(
                        "\n📊 Progress: H:%d D:%d A:%d",
                        $stats['outcomes']['home_win'],
                        $stats['outcomes']['draw'], 
                        $stats['outcomes']['away_win']
                    ));
                }
                
            } catch (\Exception $e) {
                $this->line("\n❌ Error processing match {$match->id}: " . $e->getMessage());
                $stats['errors']++;
                Log::error("Regenerate prediction error", [
                    'match_id' => $match->id,
                    'error' => $e->getMessage()
                ]);
            }
            
            $progressBar->advance();
        }
        
        $progressBar->finish();
        
        // Show final statistics
        $this->line("\n");
        $this->info("🎯 REGENERATION COMPLETE");
        $this->info("Processed: {$stats['processed']} matches");
        $this->info("Errors: {$stats['errors']}");
        
        $total = array_sum($stats['outcomes']);
        if ($total > 0) {
            $this->info("\n📊 NEW PREDICTION DISTRIBUTION:");
            foreach ($stats['outcomes'] as $outcome => $count) {
                $percentage = round(($count / $total) * 100, 1);
                $this->line(sprintf("  %s: %d (%.1f%%)", 
                    ucfirst(str_replace('_', ' ', $outcome)), 
                    $count, 
                    $percentage
                ));
            }
        }
        
        return 0;
    }
    
    private function getActualOutcome($match)
    {
        if ($match->home_goals > $match->away_goals) {
            return 'home_win';
        } elseif ($match->away_goals > $match->home_goals) {
            return 'away_win';
        } else {
            return 'draw';
        }
    }
}