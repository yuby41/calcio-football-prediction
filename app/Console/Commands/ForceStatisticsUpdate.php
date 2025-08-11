<?php

namespace App\Console\Commands;

use App\Services\StatisticsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ForceStatisticsUpdate extends Command
{
    protected $signature = 'statistics:force-update {--clear-cache : Clear statistics cache first}';
    protected $description = 'Force immediate statistics update for betting decisions';

    public function handle()
    {
        $this->info('🚀 Forcing immediate statistics update...');

        if ($this->option('clear-cache')) {
            $this->info('🧹 Clearing statistics caches...');
            Cache::forget('prediction_accuracy');
            Cache::forget('prediction_accuracy_timestamp');
            Cache::forget('statistics_last_update');
            $this->info('✅ Caches cleared');
        }

        try {
            // Force update all statistics
            $statisticsService = new StatisticsService();
            $statisticsService->updateAllStatistics();
            
            $this->info('✅ Statistics updated successfully');
            
            // Show current accuracy for first half over 0.5
            $this->info('📊 Current First Half Over 0.5 Accuracy:');
            
            $stats = \App\Models\PredictionStatistic::where('prediction_type', 'first_half_over_0_5')
                ->orderBy('created_at', 'desc')
                ->first();
                
            if ($stats) {
                $accuracy = $stats->total_predictions > 0 
                    ? round(($stats->correct_predictions / $stats->total_predictions) * 100, 2)
                    : 0;
                $this->line("   Accuracy: {$accuracy}% ({$stats->correct_predictions}/{$stats->total_predictions})");
                $this->line("   Last Updated: {$stats->created_at}");
            } else {
                $this->warn('   No statistics found for first_half_over_0_5');
            }
            
        } catch (\Exception $e) {
            $this->error('❌ Error updating statistics: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}