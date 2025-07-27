<?php

namespace App\Jobs;

use App\Models\FootballMatch;
use App\Models\MatchPrediction;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class UpdatePredictionAccuracy implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
        //
    }

    public function handle(): void
    {
        try {
            // Update matches that should be finished
            $this->updateFinishedMatches();
            
            // Update prediction accuracy for finished matches
            $this->updatePredictionAccuracy();
            
            Log::info('Prediction accuracy updated successfully');
            
        } catch (\Exception $e) {
            Log::error('Failed to update prediction accuracy: ' . $e->getMessage());
        }
    }
    
    private function updateFinishedMatches(): void
    {
        // Get matches that should be finished (older than 2 hours) but aren't marked as finished
        $matchesToUpdate = FootballMatch::where('status', '!=', 'finished')
            ->where('match_date', '<', Carbon::now()->subHours(2))
            ->whereNotNull('home_goals')
            ->whereNotNull('away_goals')
            ->get();

        foreach ($matchesToUpdate as $match) {
            $match->status = 'finished';
            $match->save();
        }
    }
    
    private function updatePredictionAccuracy(): void
    {
        // Get finished matches with predictions that haven't been evaluated
        $finishedMatches = FootballMatch::with(['prediction'])
            ->where('status', 'finished')
            ->whereHas('prediction', function($query) {
                $query->whereNull('is_correct');
            })
            ->whereNotNull('home_goals')
            ->whereNotNull('away_goals')
            ->get();

        foreach ($finishedMatches as $match) {
            $prediction = $match->prediction;
            
            // Determine actual result
            $actualResult = $this->determineMatchResult($match);
            
            // Update prediction accuracy
            $prediction->is_correct = $prediction->predicted_outcome === $actualResult;
            $prediction->save();
        }
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