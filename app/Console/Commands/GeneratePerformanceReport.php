<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AdvancedPerformanceAnalyzer;

class GeneratePerformanceReport extends Command
{
    protected $signature = 'performance:analyze 
                            {--days=30 : Number of days to analyze}
                            {--confidence=0.0 : Minimum confidence threshold}
                            {--league=* : Specific leagues to analyze}
                            {--model=* : Specific model versions to analyze}
                            {--export : Export report to file}';
    
    protected $description = 'Generate comprehensive performance analysis report';

    public function handle(): int
    {
        $this->info('📊 ADVANCED PERFORMANCE ANALYSIS');
        $this->info('Analyzing betting predictions and ROI performance...');
        $this->newLine();

        $options = [
            'days_back' => (int) $this->option('days'),
            'min_confidence' => (float) $this->option('confidence'),
            'leagues' => $this->option('league'),
            'model_versions' => $this->option('model'),
        ];

        $analyzer = app(AdvancedPerformanceAnalyzer::class);
        $report = $analyzer->generateComprehensiveReport($options);

        $this->displayReport($report);

        if ($this->option('export')) {
            $this->exportReport($report);
        }

        return 0;
    }

    private function displayReport(array $report): void
    {
        // Header
        $this->info("📋 PERFORMANCE REPORT - {$report['analysis_period']}");
        $this->info("Generated: {$report['report_generated']}");
        $this->newLine();

        // Accuracy Analysis
        if (isset($report['accuracy_analysis'])) {
            $this->displayAccuracyAnalysis($report['accuracy_analysis']);
        }

        // ROI Analysis  
        if (isset($report['roi_analysis'])) {
            $this->displayROIAnalysis($report['roi_analysis']);
        }

        // Model Comparison
        if (isset($report['model_comparison'])) {
            $this->displayModelComparison($report['model_comparison']);
        }

        // Recommendations
        if (isset($report['recommendations'])) {
            $this->displayRecommendations($report['recommendations']);
        }
    }

    private function displayAccuracyAnalysis(array $accuracy): void
    {
        if ($accuracy['status'] ?? null === 'insufficient_data') {
            $this->warn("⚠️ ACCURACY ANALYSIS: {$accuracy['message']}");
            $this->newLine();
            return;
        }

        $this->info('🎯 ACCURACY ANALYSIS');
        
        $this->table(
            ['Metric', 'Value', 'Performance'],
            [
                ['Total Predictions', $accuracy['total_predictions'], '📊'],
                ['Outcome Accuracy', $accuracy['outcome_accuracy_percentage'] . '%', $this->getPerformanceEmoji($accuracy['outcome_accuracy_percentage'])],
                ['Exact Score Accuracy', $accuracy['exact_score_accuracy_percentage'] . '%', $this->getPerformanceEmoji($accuracy['exact_score_accuracy_percentage'], 'exact')],
                ['Avg Goal Error (Home)', $accuracy['average_goal_error']['home'], '🏠'],
                ['Avg Goal Error (Away)', $accuracy['average_goal_error']['away'], '✈️'],
                ['Avg Total Goal Error', $accuracy['average_goal_error']['total'], '⚽'],
            ]
        );

        // Market-specific accuracy
        $this->info('📈 MARKET ACCURACY BREAKDOWN:');
        foreach ($accuracy['market_accuracy'] as $market => $data) {
            if ($data['total'] > 0) {
                $percentage = $data['percentage'];
                $emoji = $this->getPerformanceEmoji($percentage);
                $this->line("   {$emoji} " . ucfirst(str_replace('_', ' ', $market)) . ": {$percentage}% ({$data['correct']}/{$data['total']})");
            }
        }
        $this->newLine();
    }

    private function displayROIAnalysis(array $roi): void
    {
        if ($roi['status'] ?? null === 'insufficient_data') {
            $this->warn("⚠️ ROI ANALYSIS: {$roi['message']}");
            $this->newLine();
            return;
        }

        $this->info('💰 ROI PERFORMANCE ANALYSIS');
        
        $profitColor = $roi['net_profit'] >= 0 ? 'info' : 'error';
        $roiColor = $roi['roi_percentage'] >= 0 ? 'info' : 'error';
        
        $this->table(
            ['Metric', 'Value', 'Status'],
            [
                ['Total Bets', $roi['total_bets'], '🎲'],
                ['Total Staked', '€' . number_format($roi['total_staked'], 2), '💸'],
                ['Total Returns', '€' . number_format($roi['total_returns'], 2), '💰'],
                ['Net Profit', '€' . number_format($roi['net_profit'], 2), $roi['net_profit'] >= 0 ? '✅' : '❌'],
                ['ROI Percentage', $roi['roi_percentage'] . '%', $roi['roi_percentage'] >= 0 ? '📈' : '📉'],
                ['Win Rate', $roi['win_rate'] . '%', $this->getPerformanceEmoji($roi['win_rate'])],
            ]
        );

        // Confidence level breakdown
        $this->info('🎯 PERFORMANCE BY CONFIDENCE LEVEL:');
        foreach ($roi['by_confidence_level'] as $level => $data) {
            if ($data['bets'] > 0) {
                $levelROI = $data['roi_percentage'] ?? 0;
                $emoji = $levelROI >= 0 ? '✅' : '❌';
                $this->line("   {$emoji} " . ucfirst($level) . ": {$levelROI}% ROI, {$data['win_rate']}% win rate ({$data['bets']} bets)");
            }
        }
        $this->newLine();
    }

    private function displayModelComparison(array $comparison): void
    {
        if (empty($comparison)) {
            $this->warn('⚠️ No models available for comparison');
            $this->newLine();
            return;
        }

        $this->info('🤖 MODEL PERFORMANCE COMPARISON');
        
        $tableData = [];
        foreach ($comparison as $model => $metrics) {
            $modelName = $this->formatModelName($model);
            $accuracy = $metrics['outcome_accuracy'] . '%';
            $predictions = $metrics['total_predictions'];
            $goalError = is_numeric($metrics['average_goal_error']) 
                ? round($metrics['average_goal_error'], 2) 
                : $metrics['average_goal_error'];
            
            $tableData[] = [
                $modelName,
                $accuracy,
                $predictions,
                $goalError,
                $this->getPerformanceEmoji($metrics['outcome_accuracy'])
            ];
        }
        
        $this->table(
            ['Model', 'Accuracy', 'Predictions', 'Avg Goal Error', 'Rating'],
            $tableData
        );
        $this->newLine();
    }

    private function displayRecommendations(array $recommendations): void
    {
        if (empty($recommendations)) {
            $this->info('✅ No specific recommendations - performance looks good!');
            $this->newLine();
            return;
        }

        $this->info('💡 PERFORMANCE RECOMMENDATIONS');
        
        foreach ($recommendations as $rec) {
            $priorityEmoji = match($rec['priority']) {
                'critical' => '🚨',
                'high' => '⚠️',
                'medium' => '💡',
                'low' => 'ℹ️',
                default => '📝'
            };
            
            $this->newLine();
            $this->line("{$priorityEmoji} **{$rec['type']}** ({$rec['priority']} priority)");
            $this->line("   Problem: {$rec['message']}");
            $this->line("   Action: {$rec['action']}");
        }
        $this->newLine();
    }

    private function exportReport(array $report): void
    {
        $filename = 'performance_report_' . date('Y-m-d_H-i-s') . '.json';
        $filepath = storage_path('reports/' . $filename);
        
        // Ensure directory exists
        if (!is_dir(dirname($filepath))) {
            mkdir(dirname($filepath), 0755, true);
        }
        
        file_put_contents($filepath, json_encode($report, JSON_PRETTY_PRINT));
        $this->info("📄 Report exported to: {$filepath}");
    }

    private function getPerformanceEmoji(float $percentage, string $type = 'standard'): string
    {
        if ($type === 'exact') {
            // Exact score accuracy thresholds
            if ($percentage >= 15) return '🌟';
            if ($percentage >= 10) return '⭐';
            if ($percentage >= 5) return '✅';
            return '⚠️';
        }
        
        // Standard accuracy thresholds
        if ($percentage >= 70) return '🌟';
        if ($percentage >= 60) return '⭐';
        if ($percentage >= 50) return '✅';
        if ($percentage >= 40) return '⚠️';
        return '❌';
    }

    private function formatModelName(string $model): string
    {
        // Clean up model names for display
        $model = str_replace('_', ' ', $model);
        $model = ucwords($model);
        
        // Shorten common long names
        $replacements = [
            'Real Data Api Sports' => 'Real Data',
            'Intelligent Engine V1.0' => 'Intelligent V1',
            'Multi Source Enhanced' => 'Multi-Source',
        ];
        
        return str_replace(array_keys($replacements), array_values($replacements), $model);
    }
}