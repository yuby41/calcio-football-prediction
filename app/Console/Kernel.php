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

        // Update prediction accuracy every 30 minutes
        $schedule->command('accuracy:update --force')
                 ->everyThirtyMinutes()
                 ->withoutOverlapping();

        // Update statistics every hour (using SQL to avoid mbstring dependency)
        $schedule->command('statistics:update-sql')
                 ->hourly()
                 ->withoutOverlapping();

        // Sync today's matches every 2 hours
        $schedule->command('football:sync-today')
                 ->everyTwoHours()
                 ->withoutOverlapping();

        // ==========================================
        // 🚀 OPTIMIZED API USAGE (7500 daily requests)
        // ==========================================
        
        // LIVE MATCHES: Ultra-frequent updates (every 2 minutes during match hours)
        $schedule->command('football:sync-fixtures-optimized --type=live --with-events --with-stats')
                 ->everyTwoMinutes()
                 ->between('08:00', '23:00') // European match hours
                 ->withoutOverlapping();

        // TODAY'S MATCHES: Frequent updates (every 10 minutes)
        $schedule->command('football:sync-fixtures-optimized --type=today --with-stats')
                 ->everyTenMinutes()
                 ->withoutOverlapping();

        // WEEKLY MATCHES: Regular updates (every 30 minutes)
        $schedule->command('football:sync-fixtures-optimized --type=week --leagues=PL,PD,BL1,SA,FL1')
                 ->everyThirtyMinutes()
                 ->withoutOverlapping();

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

        // Generate first half predictions for finished matches every 2 hours
        $schedule->command('predictions:generate-first-half')
                 ->everyTwoHours()
                 ->withoutOverlapping();

        // Update team statistics every 2 hours
        $schedule->command('team:generate-statistics')
                 ->everyTwoHours()
                 ->withoutOverlapping();

        // Refresh active prediction types every 4 hours
        $schedule->command('predictions:refresh-active')
                 ->everyFourHours()
                 ->withoutOverlapping();

        // ==========================================
        // 📊 API MONITORING & OPTIMIZATION
        // ==========================================
        
        // Monitor API usage every hour
        $schedule->command('api:monitor --detailed')
                 ->hourly()
                 ->withoutOverlapping();

        // Clean old logs weekly
        $schedule->command('logs:clean --days=7')
                 ->weekly()
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