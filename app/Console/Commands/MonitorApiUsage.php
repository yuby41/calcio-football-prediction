<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ApiQuotaManager;
use Carbon\Carbon;

class MonitorApiUsage extends Command
{
    protected $signature = 'api:monitor 
                           {--reset : Reset usage counters}
                           {--detailed : Show detailed breakdown}
                           {--json : Output in JSON format}';

    protected $description = 'Monitor Football API usage and quota consumption';

    private ApiQuotaManager $quotaManager;

    public function __construct(ApiQuotaManager $quotaManager)
    {
        parent::__construct();
        $this->quotaManager = $quotaManager;
    }

    public function handle(): int
    {
        if ($this->option('reset')) {
            return $this->resetCounters();
        }

        $stats = $this->quotaManager->getUsageStats();

        if ($this->option('json')) {
            $this->line(json_encode($stats, JSON_PRETTY_PRINT));
            return Command::SUCCESS;
        }

        $this->displayUsageStats($stats);

        if ($this->option('detailed')) {
            $this->displayDetailedAnalysis($stats);
        }

        return Command::SUCCESS;
    }

    private function displayUsageStats(array $stats): void
    {
        $this->info('🔥 FOOTBALL API USAGE MONITOR');
        $this->info('═══════════════════════════════════════════════');

        // Daily Usage
        $dailyBar = $this->createProgressBar($stats['daily']['percentage']);
        $dailyStatus = $this->getStatusEmoji($stats['daily']['percentage']);
        
        $this->line(sprintf(
            '📅 <info>Daily Usage</info>: %s <comment>%d/%d</comment> (%s%%) %s',
            $dailyStatus,
            $stats['daily']['used'],
            $stats['daily']['limit'],
            number_format($stats['daily']['percentage'], 1),
            $dailyBar
        ));

        // Hourly Usage
        $hourlyBar = $this->createProgressBar($stats['hourly']['percentage']);
        $hourlyStatus = $this->getStatusEmoji($stats['hourly']['percentage']);
        
        $this->line(sprintf(
            '⏰ <info>Hourly Usage</info>: %s <comment>%d/%d</comment> (%s%%) %s',
            $hourlyStatus,
            $stats['hourly']['used'],
            $stats['hourly']['limit'],
            number_format($stats['hourly']['percentage'], 1),
            $hourlyBar
        ));

        // Minute Usage
        $minuteBar = $this->createProgressBar($stats['minute']['percentage']);
        $minuteStatus = $this->getStatusEmoji($stats['minute']['percentage']);
        
        $this->line(sprintf(
            '⚡ <info>Minute Usage</info>: %s <comment>%d/%d</comment> (%s%%) %s',
            $minuteStatus,
            $stats['minute']['used'],
            $stats['minute']['limit'],
            number_format($stats['minute']['percentage'], 1),
            $minuteBar
        ));

        $this->info('───────────────────────────────────────────────');

        // Projections and recommendations
        $estimatedDaily = $stats['estimated_daily_usage'];
        $projectionStatus = $estimatedDaily > $stats['daily']['limit'] ? '⚠️' : '✅';
        
        $this->line(sprintf(
            '📊 <info>Estimated Daily Usage</info>: %s <comment>%d</comment> (%.1f%% of limit)',
            $projectionStatus,
            $estimatedDaily,
            ($estimatedDaily / $stats['daily']['limit']) * 100
        ));

        // Remaining quota
        $remainingHours = 24 - Carbon::now()->hour;
        $hourlyBudget = $remainingHours > 0 ? (int)($stats['daily']['remaining'] / $remainingHours) : 0;
        
        $this->line(sprintf(
            '💰 <info>Remaining Quota</info>: <comment>%d requests</comment> (~%d/hour for %d hours)',
            $stats['daily']['remaining'],
            $hourlyBudget,
            $remainingHours
        ));

        // Optimal delay recommendation
        $optimalDelay = $this->quotaManager->getOptimalDelay();
        $delayStatus = $optimalDelay > 3 ? '⚠️' : '✅';
        
        $this->line(sprintf(
            '⏱️  <info>Optimal Delay</info>: %s <comment>%d seconds</comment> between requests',
            $delayStatus,
            $optimalDelay
        ));

        // Last request
        if ($stats['last_request']) {
            $lastRequest = Carbon::createFromTimestamp($stats['last_request']);
            $this->line(sprintf(
                '🕒 <info>Last Request</info>: <comment>%s</comment> (%s ago)',
                $lastRequest->format('H:i:s'),
                $lastRequest->diffForHumans()
            ));
        }
    }

    private function displayDetailedAnalysis(array $stats): void
    {
        $this->info('');
        $this->info('📈 DETAILED ANALYSIS');
        $this->info('═══════════════════════════════════════════════');

        $now = Carbon::now();
        
        // Time-based analysis
        $this->line(sprintf('🕐 Current Time: <comment>%s</comment>', $now->format('Y-m-d H:i:s')));
        $this->line(sprintf('📍 Hour of Day: <comment>%d</comment>/24', $now->hour));
        $this->line(sprintf('📅 Day Progress: <comment>%.1f%%</comment>', ($now->hour / 24) * 100));

        // Usage velocity
        $hoursElapsed = max(1, $now->hour);
        $dailyVelocity = $stats['daily']['used'] / $hoursElapsed;
        
        $this->line(sprintf('🚀 Usage Velocity: <comment>%.1f requests/hour</comment>', $dailyVelocity));

        // Peak vs Off-peak analysis
        $isPeakHour = $now->hour >= 14 && $now->hour <= 22;
        $peakStatus = $isPeakHour ? '🔥 Peak Hours' : '🌙 Off-Peak Hours';
        
        $this->line(sprintf('📊 Time Period: <comment>%s</comment>', $peakStatus));

        // Efficiency metrics
        $efficiency = $stats['daily']['used'] > 0 ? 
            ($stats['daily']['used'] / $stats['daily']['limit']) * 100 : 0;

        if ($efficiency < 50) {
            $this->line('💡 <info>Recommendation</info>: <comment>Usage is low, can increase sync frequencies</comment>');
        } elseif ($efficiency > 85) {
            $this->line('⚠️  <info>Warning</info>: <comment>High usage, consider reducing non-critical syncs</comment>');
        } else {
            $this->line('✅ <info>Status</info>: <comment>Usage is within optimal range</comment>');
        }

        // Weekly/Monthly projections
        $weeklyProjection = $stats['estimated_daily_usage'] * 7;
        $monthlyProjection = $stats['estimated_daily_usage'] * 30;
        
        $this->info('');
        $this->info('📊 PROJECTIONS');
        $this->info('───────────────────────────────────────────────');
        $this->line(sprintf('📅 Weekly Projection: <comment>%s requests</comment>', number_format($weeklyProjection)));
        $this->line(sprintf('📆 Monthly Projection: <comment>%s requests</comment>', number_format($monthlyProjection)));

        // Cost analysis (assuming typical API pricing)
        $this->info('');
        $this->info('💰 COST ANALYSIS');
        $this->info('───────────────────────────────────────────────');
        
        if ($stats['estimated_daily_usage'] <= 1000) {
            $this->line('💚 <info>Plan Recommendation</info>: <comment>Basic Plan (1,000/day) - €9.99/month</comment>');
        } elseif ($stats['estimated_daily_usage'] <= 10000) {
            $this->line('💛 <info>Plan Recommendation</info>: <comment>Pro Plan (10,000/day) - €49.99/month</comment>');
        } else {
            $this->line('🔥 <info>Plan Recommendation</info>: <comment>Ultra+ Plan (100,000+/day) - €199.99+/month</comment>');
        }
    }

    private function resetCounters(): int
    {
        if ($this->confirm('Are you sure you want to reset all API usage counters?')) {
            $this->quotaManager->resetCounters();
            $this->info('✅ API usage counters have been reset');
            return Command::SUCCESS;
        }

        $this->info('Operation cancelled');
        return Command::SUCCESS;
    }

    private function createProgressBar(float $percentage): string
    {
        $barLength = 20;
        $filledLength = (int)(($percentage / 100) * $barLength);
        $emptyLength = $barLength - $filledLength;
        
        $bar = str_repeat('█', $filledLength) . str_repeat('░', $emptyLength);
        
        if ($percentage >= 90) {
            return "<fg=red>[$bar]</>";
        } elseif ($percentage >= 75) {
            return "<fg=yellow>[$bar]</>";
        } else {
            return "<fg=green>[$bar]</>";
        }
    }

    private function getStatusEmoji(float $percentage): string
    {
        if ($percentage >= 90) {
            return '🔴';
        } elseif ($percentage >= 75) {
            return '🟡';
        } elseif ($percentage >= 50) {
            return '🟠';
        } else {
            return '🟢';
        }
    }
}