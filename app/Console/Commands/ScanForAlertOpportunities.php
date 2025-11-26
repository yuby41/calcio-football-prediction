<?php

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
        $this->line("   • Opportunities scanned: {$results['opportunities_scanned']}");
        $this->line("   • Alerts sent: {$results['alerts_sent']}");
        
        if ($results["alerts_sent"] > 0) {
            $this->info("📢 {$results['alerts_sent']} value betting alerts sent!");
        }

        return 0;
    }
}