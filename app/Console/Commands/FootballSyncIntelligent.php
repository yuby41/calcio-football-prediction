<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Carbon\Carbon;

class FootballSyncIntelligent extends Command
{
    protected $signature = 'football:sync-intelligent 
                            {--type=auto : Type of sync (auto, live, today, week)}
                            {--force : Force sync regardless of time}';

    protected $description = 'Intelligent football data sync based on time and match activity';

    public function handle()
    {
        $type = $this->option('type');
        $force = $this->option('force');
        
        if ($type === 'auto' && !$force) {
            $type = $this->determineOptimalSyncType();
        }

        $this->info("🤖 Running intelligent sync: {$type}");
        $startTime = microtime(true);

        try {
            switch ($type) {
                case 'live':
                    $this->syncLive();
                    break;
                case 'today':
                    $this->syncToday();
                    break;
                case 'week':
                    $this->syncWeek();
                    break;
                case 'minimal':
                    $this->syncMinimal();
                    break;
                default:
                    $this->syncToday();
                    break;
            }

            $executionTime = round(microtime(true) - $startTime, 2);
            $this->info("✅ Intelligent sync completed in {$executionTime}s");

        } catch (\Exception $e) {
            $this->error('❌ Error during intelligent sync: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }

    private function determineOptimalSyncType(): string
    {
        $now = Carbon::now();
        $hour = $now->hour;
        $dayOfWeek = $now->dayOfWeek;

        // Weekend match days (Saturday = 6, Sunday = 0)
        $isMatchDay = in_array($dayOfWeek, [0, 6]);
        
        // Peak match hours (14:00-22:00)
        $isMatchTime = $hour >= 14 && $hour <= 22;

        if ($isMatchDay && $isMatchTime) {
            return 'live'; // High frequency during matches
        } elseif ($isMatchDay || $isMatchTime) {
            return 'today'; // Medium frequency
        } elseif ($hour >= 2 && $hour <= 6) {
            return 'minimal'; // Low activity hours
        } else {
            return 'today'; // Default
        }
    }

    private function syncLive(): void
    {
        $this->info('📡 High-frequency live sync');
        Artisan::call('football:sync-fixtures-optimized', [
            '--type' => 'live',
            '--with-events' => true,
            '--with-stats' => true,
            '--quiet' => true
        ]);
    }

    private function syncToday(): void
    {
        $this->info('📅 Today matches sync');
        Artisan::call('football:sync-fixtures-optimized', [
            '--type' => 'today',
            '--with-stats' => true,
            '--quiet' => true
        ]);
    }

    private function syncWeek(): void
    {
        $this->info('📊 Weekly data sync');
        Artisan::call('football:sync-fixtures-optimized', [
            '--type' => 'week',
            '--leagues' => 'PL,PD,BL1,SA,FL1',
            '--quiet' => true
        ]);
    }

    private function syncMinimal(): void
    {
        $this->info('🌙 Minimal sync (low activity period)');
        Artisan::call('football:sync-fixtures-optimized', [
            '--type' => 'today',
            '--quiet' => true
        ]);
    }
}