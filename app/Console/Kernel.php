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

        // Update live scores every 5 minutes (faster for live matches)
        $schedule->command('football:update-today --silent')
                 ->everyFiveMinutes()
                 ->withoutOverlapping();

        // Generate predictions for new matches every hour
        $schedule->command('ml:predict')
                 ->hourly()
                 ->withoutOverlapping();

        // Update team statistics daily at 2 AM
        $schedule->command('team:generate-statistics')
                 ->dailyAt('02:00')
                 ->withoutOverlapping();

        // Clean old logs weekly
        $schedule->command('logs:clean --days=7')
                 ->weekly()
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