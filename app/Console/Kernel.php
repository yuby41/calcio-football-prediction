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
        // ==========================================
        // 🔥 CORE ESSENTIAL COMMANDS (OPTIMIZED)
        // ==========================================
        
        // 🏈 FOOTBALL DATA SYNC (Primary - every 30 minutes)
        $schedule->command('football:update-today')
                 ->everyThirtyMinutes()
                 ->withoutOverlapping()
                 ->runInBackground();

        // 📊 STANDINGS & TEAM STATS (Every 6 hours)
        $schedule->command('football:sync-standings --leagues=PL,PD,BL1,SA,FL1 --with-stats')
                 ->everySixHours()
                 ->withoutOverlapping();

        // 🤖 PREDICTIONS MAINTENANCE (Unified - every hour)
        $schedule->command('predictions:maintenance-optimized')
                 ->hourly()
                 ->withoutOverlapping()
                 ->runInBackground();

        // 🔄 ACTIVE PREDICTIONS REFRESH (Daily at 4 AM)
        $schedule->command('predictions:refresh-active')
                 ->dailyAt('04:00')
                 ->withoutOverlapping();

        // 💰 BET RESOLUTION (Every 10 minutes with smart estimation)
        $schedule->command('bets:auto-resolve --estimate-missing')
                 ->everyTenMinutes()
                 ->withoutOverlapping();
        
        // 🔍 BET RESOLUTION MONITORING (Every hour)
        $schedule->command('bets:monitor')
                 ->hourly()
                 ->withoutOverlapping();

        // 💰 EXHAUSTED BUDGETS CHECK (Every 30 minutes with auto-deactivation)
        $schedule->command('budgets:check-exhausted --deactivate')
                 ->everyThirtyMinutes()
                 ->withoutOverlapping();
                 
        // 🎯 TARGET PROFIT CHECK (Every 30 minutes with auto-deactivation)
        $schedule->command('budget:check-target-profit --fix')
                 ->everyThirtyMinutes()
                 ->withoutOverlapping();
                 
        // 🔄 BUDGET AVAILABLE BALANCE SYNC (Every hour to ensure correct available balances)
        $schedule->command('budgets:sync-available-balances')
                 ->hourly()
                 ->withoutOverlapping();

        // ⚽ MATCH RESULTS UPDATE (Every 30 minutes)
        $schedule->command('matches:update-results')
                 ->everyThirtyMinutes()
                 ->withoutOverlapping();

        // 📈 STATISTICS UPDATE (Every 30 minutes for faster betting decisions)
        $schedule->command('statistics:update')
                 ->everyThirtyMinutes()
                 ->withoutOverlapping();

        // 🧠 ML PREDICTIONS (Every hour)
        $schedule->command('ml:predict')
                 ->hourly()
                 ->withoutOverlapping()
                 ->runInBackground();

        // 🔴 LIVE MATCH PREDICTIONS (Every 15 minutes for urgent live matches)
        $schedule->command('ml:predict-live')
                 ->everyFifteenMinutes()
                 ->withoutOverlapping();

        // ==========================================
        // 🌙 NIGHTLY MAINTENANCE (01:00-06:00)
        // ==========================================
        
        // 🏋️ ML MODEL TRAINING (Weekly - Sundays at 2 AM)
        $schedule->command('ml:train-enhanced')
                 ->weekly()
                 ->sundays()
                 ->at('02:00')
                 ->withoutOverlapping();

        // 🔍 DATA VERIFICATION (Daily at 3 AM)
        $schedule->command('data:verify-integrity --fix')
                 ->dailyAt('03:00')
                 ->withoutOverlapping();

        // 🏆 TEAM STATISTICS (Daily at 5 AM)
        $schedule->command('teams:calculate-statistics')
                 ->dailyAt('05:00')
                 ->withoutOverlapping();

        // ==========================================
        // 🛠️ VERIFICATION & MONITORING
        // ==========================================
        
        // ✅ BET CALCULATIONS VERIFICATION (Every 6 hours)
        $schedule->command('bets:verify-calculations --fix')
                 ->everySixHours()
                 ->withoutOverlapping();

        // 🔎 MATCH RESULTS VERIFICATION (Twice daily)
        $schedule->command('matches:verify-results --fix')
                 ->twiceDaily(6, 18)
                 ->withoutOverlapping();

        // 📊 PREDICTIONS VERIFICATION (Daily at 6 AM)
        $schedule->command('predictions:verify --fix')
                 ->dailyAt('06:00')
                 ->withoutOverlapping();

        // 🚨 API MONITORING (Every hour)
        $schedule->command('api:monitor')
                 ->hourly()
                 ->withoutOverlapping();

        // 🧹 LOG CLEANUP (Twice daily)
        $schedule->command('logs:clean --days=7')
                 ->twiceDaily(2, 14)
                 ->withoutOverlapping();

        // ⚽ FIRST HALF DATA UPDATE (Every 2 hours for betting accuracy)
        $schedule->command('matches:update-first-half')
                 ->everyTwoHours()
                 ->withoutOverlapping();
                 
        // 🔍 REAL FIRST HALF DATA VERIFICATION (Daily at 7 AM to fix synthetic data)
        $schedule->command('matches:verify-real-first-half --fix --limit=100')
                 ->dailyAt('07:00')
                 ->withoutOverlapping();
                 
        // 🤖 ML TRAINING DATA AUDIT (Weekly on Mondays at 6 AM)
        $schedule->command('ml:audit-training-data --fix')
                 ->weekly()
                 ->mondays()
                 ->at('06:00')
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