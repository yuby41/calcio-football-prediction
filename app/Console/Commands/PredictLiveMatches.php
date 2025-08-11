<?php

namespace App\Console\Commands;

use App\Models\FootballMatch;
use App\Services\PredictionService;
use Illuminate\Console\Command;

class PredictLiveMatches extends Command
{
    protected $signature = 'ml:predict-live';
    protected $description = 'Generate predictions specifically for live matches without predictions';

    public function __construct(private PredictionService $predictionService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('🔴 Checking live matches for predictions...');

        // Get all live matches without predictions
        $liveMatches = FootballMatch::where('status', 'live')
            ->whereDoesntHave('prediction')
            ->with(['homeTeam', 'awayTeam'])
            ->orderBy('match_date', 'desc')
            ->get();

        if ($liveMatches->isEmpty()) {
            $this->info('✅ All live matches have predictions');
            return Command::SUCCESS;
        }

        $this->warn("Found {$liveMatches->count()} live matches WITHOUT predictions:");

        foreach ($liveMatches as $match) {
            $this->line("  - {$match->homeTeam->name} vs {$match->awayTeam->name} (Started: {$match->match_date->format('H:i')})");
        }

        $this->newLine();
        $this->info('🚀 Generating predictions for live matches...');

        $successful = 0;
        $failed = 0;

        foreach ($liveMatches as $match) {
            try {
                $this->line("Predicting: {$match->homeTeam->name} vs {$match->awayTeam->name}");
                
                if ($this->predictionService->predictMatch($match)) {
                    $this->info("  ✅ Success");
                    $successful++;
                } else {
                    $this->warn("  ❌ Failed");
                    $failed++;
                }
            } catch (\Exception $e) {
                $this->error("  ❌ Error: " . $e->getMessage());
                $failed++;
            }
        }

        $this->newLine();
        $this->info("📊 Results for live matches:");
        $this->info("✅ Successful: {$successful}");
        
        if ($failed > 0) {
            $this->warn("❌ Failed: {$failed}");
        }

        if ($successful === $liveMatches->count()) {
            $this->info("🎉 All live matches now have predictions!");
        }

        return Command::SUCCESS;
    }
}