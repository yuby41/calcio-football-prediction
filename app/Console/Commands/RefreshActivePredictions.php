<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\DynamicPredictionService;
use App\Services\StatisticsService;

class RefreshActivePredictions extends Command
{
    protected $signature = 'predictions:refresh-active 
                           {--force : Force refresh even if cache is still valid}
                           {--show-stats : Display current prediction statistics}';

    protected $description = 'Refresh active prediction types based on accuracy statistics (runs daily)';

    private DynamicPredictionService $dynamicPredictionService;
    private StatisticsService $statisticsService;

    public function __construct(
        DynamicPredictionService $dynamicPredictionService,
        StatisticsService $statisticsService
    ) {
        parent::__construct();
        $this->dynamicPredictionService = $dynamicPredictionService;
        $this->statisticsService = $statisticsService;
    }

    public function handle(): int
    {
        $this->info('🔄 Refreshing active prediction types based on accuracy statistics...');

        // Update all statistics first
        $this->info('📊 Updating prediction statistics...');
        $this->statisticsService->updateAllStatistics();

        // Force refresh if requested
        if ($this->option('force')) {
            $this->info('🔧 Force refreshing active predictions cache...');
            $activePredictions = $this->dynamicPredictionService->refreshActivePredictionTypes();
        } else {
            $activePredictions = $this->dynamicPredictionService->getActivePredictionTypes();
        }

        // Update cache timestamp
        $this->dynamicPredictionService->updateCacheTimestamp();

        // Display results
        $this->displayResults($activePredictions);

        // Show detailed stats if requested
        if ($this->option('show-stats')) {
            $this->displayDetailedStatistics();
        }

        $this->info('✅ Active prediction types refreshed successfully!');
        return Command::SUCCESS;
    }

    private function displayResults(array $activePredictions): void
    {
        $this->info('');
        $this->info('📈 ACTIVE PREDICTION TYPES (>50% accuracy):');
        $this->info('=' . str_repeat('=', 50));

        if (empty($activePredictions)) {
            $this->warn('⚠️  No prediction types meet the minimum accuracy threshold of 50%');
            return;
        }

        foreach ($activePredictions as $prediction) {
            $accuracy = number_format($prediction['accuracy'], 2);
            $correct = $prediction['correct_predictions'];
            $total = $prediction['total_predictions'];
            
            $this->line(sprintf(
                '✅ <info>%s</info>: <comment>%s%%</comment> (%d/%d predictions)',
                $prediction['display_name'],
                $accuracy,
                $correct,
                $total
            ));
        }

        $this->info('');
        $this->info('📊 Summary:');
        $this->line(sprintf('• Active prediction types: <info>%d</info>', count($activePredictions)));
        $this->line(sprintf('• Accuracy threshold: <comment>50.0%%</comment>'));
        $this->line(sprintf('• Cache valid until: <comment>%s</comment>', now()->addDay()->format('Y-m-d H:i:s')));
    }

    private function displayDetailedStatistics(): void
    {
        $this->info('');
        $this->info('📋 DETAILED STATISTICS:');
        $this->info('=' . str_repeat('=', 50));

        $summary = $this->dynamicPredictionService->getActivePredictionsSummary();
        
        $this->line(sprintf('• Total prediction types available: <info>%d</info>', $summary['total_count']));
        $this->line(sprintf('• Currently active: <info>%d</info>', $summary['active_count']));
        $this->line(sprintf('• Minimum threshold: <comment>%.1f%%</comment>', $summary['threshold']));
        $this->line(sprintf('• Last updated: <comment>%s</comment>', $summary['last_updated']->format('Y-m-d H:i:s')));

        $this->info('');
        $this->info('📊 All prediction type accuracies:');
        
        // Get all prediction statistics for comparison
        $allStatistics = \App\Models\PredictionStatistic::orderBy('accuracy_percentage', 'desc')->get();
        
        foreach ($allStatistics as $stat) {
            $status = $stat->accuracy_percentage >= 50 ? '✅' : '❌';
            $accuracy = number_format($stat->accuracy_percentage, 2);
            
            $this->line(sprintf(
                '%s <info>%s</info>: <comment>%s%%</comment> (%d/%d)',
                $status,
                $stat->display_name,
                $accuracy,
                $stat->correct_predictions,
                $stat->total_predictions
            ));
        }
    }
}