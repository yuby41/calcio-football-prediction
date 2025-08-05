<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class PredictionsMaintenanceOptimized extends Command
{
    protected $signature = 'predictions:maintenance-optimized 
                            {--comprehensive : Run comprehensive maintenance including accuracy fixes}
                            {--light : Run light maintenance only}
                            {--limit=50 : Limit number of records to process}';

    protected $description = 'Unified optimized predictions maintenance command';

    public function handle()
    {
        $this->info('🚀 Starting Optimized Predictions Maintenance...');
        $startTime = microtime(true);

        $limit = $this->option('limit');
        $comprehensive = $this->option('comprehensive');
        $light = $this->option('light');

        try {
            // 1. Update prediction accuracy (always run)
            $this->info('📊 Updating prediction accuracy...');
            Artisan::call('predictions:update-accuracy', [
                '--limit' => $limit,
                '--quiet' => true
            ]);

            if (!$light) {
                // 2. Fix accuracy markings
                $this->info('🔧 Fixing accuracy markings...');
                Artisan::call('predictions:fix-accuracy', ['--quiet' => true]);

                // 3. Verify predictions
                $this->info('✅ Verifying predictions...');
                Artisan::call('predictions:verify', [
                    '--fix' => true,
                    '--limit' => $limit,
                    '--quiet' => true
                ]);
            }

            if ($comprehensive) {
                // 4. Verify match predictions consistency
                $this->info('🔍 Verifying match predictions...');
                Artisan::call('matches:verify-predictions', [
                    '--limit' => $limit,
                    '--fix' => true,
                    '--quiet' => true
                ]);

                // 5. Refresh active prediction types
                $this->info('🔄 Refreshing active predictions...');
                Artisan::call('predictions:refresh-active', ['--quiet' => true]);
            }

            $executionTime = round(microtime(true) - $startTime, 2);
            $this->info("✅ Predictions maintenance completed in {$executionTime}s");

        } catch (\Exception $e) {
            $this->error('❌ Error during predictions maintenance: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }
}