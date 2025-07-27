<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;

class CleanOldLogs extends Command
{
    protected $signature = 'logs:clean {--days=7 : Number of days to keep logs}';
    
    protected $description = 'Clean old log files older than specified days';

    public function handle()
    {
        $days = $this->option('days');
        $logPath = storage_path('logs');
        
        if (!File::exists($logPath)) {
            $this->error('Log directory does not exist');
            return Command::FAILURE;
        }
        
        $cutoffDate = Carbon::now()->subDays($days);
        $files = File::glob($logPath . '/laravel-*.log');
        $deletedCount = 0;
        $totalSize = 0;
        
        foreach ($files as $file) {
            $fileDate = File::lastModified($file);
            
            if ($fileDate < $cutoffDate->timestamp) {
                $size = File::size($file);
                $totalSize += $size;
                
                File::delete($file);
                $deletedCount++;
                
                $this->line("Deleted: " . basename($file) . " (" . $this->formatBytes($size) . ")");
            }
        }
        
        if ($deletedCount > 0) {
            $this->info("✓ Cleaned {$deletedCount} old log files, freed " . $this->formatBytes($totalSize));
        } else {
            $this->info("No old log files to clean");
        }
        
        return Command::SUCCESS;
    }
    
    private function formatBytes($size, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }
        
        return round($size, $precision) . ' ' . $units[$i];
    }
}