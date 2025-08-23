<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class OptimizePerformance extends Command
{
    protected $signature = 'app:optimize-performance {--clear-cache : Clear application cache}';
    
    protected $description = 'Optimize application performance and clear slow queries';
    
    public function handle(): int
    {
        $this->info('🚀 Optimizing application performance...');
        
        // Clear query cache if requested
        if ($this->option('clear-cache')) {
            $this->info('🧹 Clearing application cache...');
            Cache::flush();
            $this->call('config:cache');
            $this->call('route:cache');
            $this->call('view:cache');
            $this->info('✅ Cache cleared and rebuilt');
        }
        
        // Optimize database tables
        $this->info('🗄️ Optimizing database tables...');
        $this->optimizeDatabase();
        
        // Clean old data
        $this->info('🧼 Cleaning old data...');
        $this->cleanOldData();
        
        // Update statistics
        $this->info('📊 Refreshing prediction statistics...');
        $this->call('predictions:refresh-active');
        
        $this->info('✅ Performance optimization completed!');
        
        return Command::SUCCESS;
    }
    
    private function optimizeDatabase()
    {
        try {
            // Optimize main tables
            DB::statement('OPTIMIZE TABLE matches');
            DB::statement('OPTIMIZE TABLE match_predictions');
            DB::statement('OPTIMIZE TABLE teams');
            DB::statement('OPTIMIZE TABLE team_statistics');
            
            $this->info('✅ Database tables optimized');
        } catch (\Exception $e) {
            $this->warn('⚠️ Database optimization failed: ' . $e->getMessage());
        }
    }
    
    private function cleanOldData()
    {
        try {
            // Remove old prediction statistics older than 6 months
            $sixMonthsAgo = now()->subMonths(6);
            
            $deletedPredictions = DB::table('match_predictions')
                ->where('predicted_at', '<', $sixMonthsAgo)
                ->where('match_id', 'NOT IN', function($query) {
                    $query->select('id')
                        ->from('matches')
                        ->where('match_date', '>', now()->subMonths(3));
                })
                ->delete();
            
            $this->info("✅ Cleaned {$deletedPredictions} old predictions");
            
            // Clean session data
            DB::table('sessions')->where('last_activity', '<', now()->subDays(7)->timestamp)->delete();
            
            $this->info('✅ Cleaned old session data');
            
        } catch (\Exception $e) {
            $this->warn('⚠️ Data cleanup failed: ' . $e->getMessage());
        }
    }
}