<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SetupAlertSchedule extends Command
{
    protected $signature = 'alerts:setup-schedule';
    protected $description = 'Setup automated alert scheduling for value betting opportunities';

    public function handle(): int
    {
        $this->info('🤖 SETTING UP AUTOMATED ALERT SCHEDULING');
        $this->newLine();

        // Check if Laravel scheduler is configured
        $this->info('📋 SCHEDULER CONFIGURATION CHECKLIST:');
        $this->newLine();

        // 1. Crontab check
        $this->info('1. 📅 CRONTAB ENTRY');
        $this->line('   Add this line to your crontab (run: crontab -e):');
        $this->line('   * * * * * cd /home/yualbe/Homestead/code/Calcio && php artisan schedule:run >> /dev/null 2>&1');
        $this->newLine();

        // 2. Schedule configuration
        $this->info('2. ⚙️ LARAVEL SCHEDULE (app/Console/Kernel.php)');
        $this->line('   Add these scheduled tasks:');
        $this->line('   $schedule->command("alerts:scan-opportunities")->everyFifteenMinutes();');
        $this->line('   $schedule->command("alerts:cleanup-old")->daily();');
        $this->newLine();

        // 3. Environment variables
        $this->info('3. 🔧 ENVIRONMENT CONFIGURATION (.env)');
        $this->table(
            ['Setting', 'Current Value', 'Recommended'],
            [
                ['ALERTS_EMAIL_ENABLED', env('ALERTS_EMAIL_ENABLED', 'false'), 'true'],
                ['ALERTS_EMAIL_TO', env('ALERTS_EMAIL_TO', 'not set'), 'your-email@example.com'],
                ['ALERTS_WEBHOOK_URL', env('ALERTS_WEBHOOK_URL', 'not set'), 'https://your-webhook.com/alerts'],
                ['QUEUE_CONNECTION', env('QUEUE_CONNECTION', 'sync'), 'database (for production)']
            ]
        );
        $this->newLine();

        // 4. Test the system
        $this->info('4. 🧪 TESTING');
        $this->line('   Run test command: php artisan alerts:test-intelligent');
        $this->line('   Check scheduler: php artisan schedule:list');
        $this->line('   Monitor logs: tail -f storage/logs/laravel.log');
        $this->newLine();

        // Create the scheduled command
        $this->info('📝 CREATING SCHEDULED ALERT COMMAND...');
        $this->createScheduledCommand();

        // Summary
        $this->info('✅ SETUP COMPLETE!');
        $this->newLine();
        
        $this->info('🎯 WHAT HAPPENS NOW:');
        $this->info('• System scans for value opportunities every 15 minutes');
        $this->info('• High-value bets (5%+ value) trigger alerts');
        $this->info('• Alerts sent via email/webhook for priority 3+ opportunities');
        $this->info('• Rate limiting: Max 10 alerts per hour');
        $this->info('• Automatic cleanup of old alerts');
        $this->newLine();

        $this->info('🚀 NEXT STEPS:');
        $this->info('1. Configure your .env settings above');
        $this->info('2. Add crontab entry for schedule:run');
        $this->info('3. Update app/Console/Kernel.php with schedule');
        $this->info('4. Test: php artisan alerts:scan-opportunities');
        $this->info('5. Start scheduler: php artisan schedule:work (or use cron)');

        return 0;
    }

    private function createScheduledCommand(): void
    {
        $commandPath = app_path('Console/Commands/ScanForAlertOpportunities.php');
        
        if (file_exists($commandPath)) {
            $this->line('⚠️ ScanForAlertOpportunities command already exists');
            return;
        }

        $commandContent = '<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\IntelligentAlertSystem;

class ScanForAlertOpportunities extends Command
{
    protected $signature = "alerts:scan-opportunities 
                            {--min-value=5 : Minimum value percentage}
                            {--max-alerts=10 : Maximum alerts per scan}";
    
    protected $description = "Scan for value betting opportunities and send alerts";

    public function handle(): int
    {
        $this->info("🔍 Scanning for value betting opportunities...");
        
        $alertSystem = app(IntelligentAlertSystem::class);
        
        $options = [
            "min_value_percentage" => (float) $this->option("min-value"),
            "min_confidence" => 0.7,
            "max_alerts_per_hour" => (int) $this->option("max-alerts"),
            "leagues" => ["PL", "PD", "BL1", "SA", "FL1"],
        ];

        $results = $alertSystem->scanForValueOpportunities($options);
        
        $this->info("✅ Scan completed:");
        $this->line("   • Opportunities scanned: {$results[\'opportunities_scanned\']}");
        $this->line("   • Alerts sent: {$results[\'alerts_sent\']}");
        
        if ($results["alerts_sent"] > 0) {
            $this->info("📢 {$results[\'alerts_sent\']} value betting alerts sent!");
        }

        return 0;
    }
}';

        file_put_contents($commandPath, $commandContent);
        $this->info('✅ Created ScanForAlertOpportunities command');
    }
}