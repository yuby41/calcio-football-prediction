<?php

namespace App\Console\Commands;

use App\Models\FootballMatch;
use Illuminate\Console\Command;

class RecalculatePredictionAccuracy extends Command
{
    protected $signature = 'predictions:recalculate-accuracy';
    protected $description = 'Recalculate prediction accuracy for all finished matches';

    public function handle()
    {
        $this->info('Starting prediction accuracy recalculation...');

        $finishedMatches = FootballMatch::with(['prediction'])
            ->where('status', 'finished')
            ->whereHas('prediction')
            ->whereNotNull('home_goals')
            ->whereNotNull('away_goals')
            ->get();

        $this->info("Found {$finishedMatches->count()} finished matches with predictions");

        $updated = 0;
        foreach ($finishedMatches as $match) {
            if ($match->prediction) {
                $match->prediction->checkAccuracy();
                $updated++;
            }
        }

        $this->info("Successfully updated accuracy for {$updated} predictions");
        $this->info('Recalculation complete!');

        return Command::SUCCESS;
    }
}