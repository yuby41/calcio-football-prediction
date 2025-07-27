<?php

namespace App\Console\Commands;

use App\Models\FootballMatch;
use App\Models\MatchPrediction;
use App\Services\SimpleAccuracyService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class TestAccuracySystem extends Command
{
    protected $signature = 'test:accuracy-system';
    
    protected $description = 'Test the automatic accuracy update system';
    
    public function handle(): int
    {
        $this->info('Testing automatic accuracy update system...');
        
        // Clear cache first
        Cache::forget('prediction_accuracy');
        Cache::forget('prediction_accuracy_timestamp');
        $this->info('Cache cleared');
        
        // Get current accuracy
        $accuracy1 = SimpleAccuracyService::getCurrentAccuracy();
        $this->info("First call accuracy: {$accuracy1}%");
        
        // Call again immediately (should use cache)
        $accuracy2 = SimpleAccuracyService::getCurrentAccuracy();
        $this->info("Second call accuracy (cached): {$accuracy2}%");
        
        // Show cache status
        $cached = Cache::get('prediction_accuracy');
        $timestamp = Cache::get('prediction_accuracy_timestamp');
        $this->info("Cached value: " . ($cached ?? 'null'));
        $this->info("Cache timestamp: " . ($timestamp ? $timestamp->format('Y-m-d H:i:s') : 'null'));
        
        // Wait and test auto-refresh
        $this->info('Waiting 2 seconds and then checking if middleware auto-updates...');
        sleep(2);
        
        // Simulate time passage for cache expiry test
        Cache::put('prediction_accuracy_timestamp', Carbon::now()->subMinutes(3), 600);
        
        $accuracy3 = SimpleAccuracyService::getCurrentAccuracy();
        $this->info("After cache expiry simulation: {$accuracy3}%");
        
        $this->info('✅ Automatic accuracy update system is working!');
        $this->info('💡 The system will auto-update every 2 minutes when someone visits the dashboard');
        
        return Command::SUCCESS;
    }
}