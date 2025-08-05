<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Auto-finish matches that should be completed every 10 minutes
        $schedule->command('matches:auto-finish')
                 ->everyTenMinutes()
                 ->withoutOverlapping();

        // ==========================================
        // 🚀 OPTIMIZED UNIFIED COMMANDS
        // ==========================================
        
        // Unified predictions maintenance (replaces 6 separate commands)
        $schedule->command('predictions:maintenance-optimized --light')
                 ->everyThirtyMinutes()
                 ->withoutOverlapping()
                 ->runInBackground();

        $schedule->command('predictions:maintenance-optimized --comprehensive')
                 ->hourly()
                 ->withoutOverlapping()
                 ->runInBackground();

        // Resolve pending bets every 15 minutes
        $schedule->command('bets:resolve-pending --limit=25')
                 ->everyFifteenMinutes()
                 ->withoutOverlapping();

        // Verify bet calculations every hour
        $schedule->command('bets:verify-calculations --fix --limit=100')
                 ->hourly()
                 ->withoutOverlapping();

        // Verify match results every 2 hours
        $schedule->command('matches:verify-results --fix --limit=20')
                 ->everyTwoHours()
                 ->withoutOverlapping();

        // Update statistics every hour (using SQL to avoid mbstring dependency)
        $schedule->command('statistics:update-sql')
                 ->hourly()
                 ->withoutOverlapping();

        // Legacy command removed - replaced by optimized version below

        // ==========================================
        // 🚀 OPTIMIZED API USAGE (7500 daily requests)
        // ==========================================
        
        // ==========================================
        // 🤖 INTELLIGENT SYNC SYSTEM
        // ==========================================
        
        // Intelligent sync (auto-adjusts frequency based on time/day)
        $schedule->command('football:sync-intelligent')
                 ->everyFiveMinutes()
                 ->between('14:00', '22:00') // Peak match hours
                 ->weekends()
                 ->withoutOverlapping()
                 ->runInBackground();

        $schedule->command('football:sync-intelligent')
                 ->everyFifteenMinutes()
                 ->withoutOverlapping()
                 ->runInBackground();

        // Standings sync - reduced frequency
        $schedule->command('football:sync-standings --leagues=PL,PD,BL1,SA,FL1 --with-stats --quiet')
                 ->hourly()
                 ->withoutOverlapping()
                 ->runInBackground();

        // SEASON DATA: Comprehensive sync (every 2 hours)
        $schedule->command('football:sync-fixtures-optimized --type=season --leagues=PL,PD,BL1,SA,FL1,CL,EL')
                 ->everyTwoHours()
                 ->withoutOverlapping();

        // TEAM STANDINGS & STATS: Enhanced frequency (every 30 minutes)
        $schedule->command('football:sync-standings --leagues=PL,PD,BL1,SA,FL1,CL,EL --with-stats')
                 ->everyThirtyMinutes()
                 ->withoutOverlapping();

        // TEAMS & LEAGUES: Daily comprehensive sync
        $schedule->command('football:sync-leagues-teams --leagues=PL,PD,BL1,SA,FL1,CL,EL --season=2025')
                 ->dailyAt('03:00')
                 ->withoutOverlapping();

        // Generate predictions for new matches every 30 minutes
        $schedule->command('ml:predict')
                 ->everyThirtyMinutes()
                 ->withoutOverlapping();

        // Fetch real first half data for finished matches every 2 hours with higher limit
        $schedule->command('matches:fetch-first-half-data --limit=100 --days=2')
                 ->everyTwoHours()
                 ->withoutOverlapping();

        // First half data processing (reduced frequency)
        $schedule->command('matches:fix-first-half-pending --limit=25 --quiet')
                 ->everyTwoHours()
                 ->withoutOverlapping()
                 ->runInBackground();

        // ==========================================
        // 🌙 NIGHT MAINTENANCE (01:00-06:00)
        // ==========================================
        
        // Heavy maintenance during low activity
        $schedule->command('app:audit-systematic-issues --fix --quiet')
                 ->dailyAt('01:30')
                 ->withoutOverlapping();

        $schedule->command('app:fix-critical-issues --quiet')
                 ->dailyAt('02:00')
                 ->withoutOverlapping();

        $schedule->command('team:generate-statistics --quiet')
                 ->dailyAt('02:30')
                 ->withoutOverlapping();

        $schedule->command('statistics:fix-with-api-endpoints --limit=100 --days=3 --quiet')
                 ->dailyAt('03:30')
                 ->withoutOverlapping();

        // ==========================================
        // 📊 API MONITORING & OPTIMIZATION
        // ==========================================
        
        // Monitor API usage every hour
        $schedule->command('api:monitor --detailed')
                 ->hourly()
                 ->withoutOverlapping();

        // Optimized log management
        $schedule->command('logs:clean --days=3 --quiet')
                 ->twiceDaily(2, 14)
                 ->withoutOverlapping();

        // ==========================================
        // 🤖 AUTOMATED ML TRAINING & MONITORING
        // ==========================================
        
        // Automated ML model retraining weekly (Sundays at 3 AM)
        $schedule->command('ml:train-automated --precision-threshold=75')
                 ->weekly()
                 ->sundays()
                 ->at('03:00')
                 ->withoutOverlapping()
                 ->emailOutputOnFailure(config('mail.admin_email', 'admin@example.com'));

        // Model performance monitoring daily (4 AM)
        $schedule->command('ml:check-model-performance --alert-threshold=70 --trigger-retrain')
                 ->daily()
                 ->at('04:00')
                 ->withoutOverlapping();

        // Data integrity verification every 6 hours with auto-fix
        $schedule->command('data:verify-integrity --fix --silent')
                 ->everySixHours()
                 ->withoutOverlapping();

        // ==========================================
        // 🔧 AUTOMATED RESULT CORRECTION SYSTEM
        // ==========================================
        
        // Auto-correct suspicious match results every 2 hours
        $schedule->command('matches:auto-correct --confidence=8')
                 ->everyTwoHours()
                 ->withoutOverlapping();

        // Detect suspicious results for manual review every 6 hours
        $schedule->command('matches:detect-suspicious --days=3')
                 ->everySixHours()
                 ->withoutOverlapping();

        // Verify overall match result integrity daily
        $schedule->command('matches:verify-results --fix')
                 ->dailyAt('02:00')
                 ->withoutOverlapping();

        // Comprehensive team statistics recalculation weekly (Saturdays at 1 AM)
        $schedule->command('teams:calculate-statistics --force')
                 ->weekly()
                 ->saturdays()
                 ->at('01:00')
                 ->withoutOverlapping();

        // Fix statistics with FT/HT API endpoints every 4 hours
        $schedule->command('statistics:fix-with-api-endpoints --limit=50 --days=3')
                 ->everyFourHours()
                 ->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}