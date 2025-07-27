<?php

namespace App\Console\Commands;

use App\Services\SimpleAccuracyService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class UpdateAccuracy extends Command
{
    protected $signature = 'accuracy:update {--force : Force update even if cached}';
    
    protected $description = 'Update prediction accuracy calculations';
    
    public function handle(): int
    {
        try {
            if ($this->option('force')) {
                Cache::forget('prediction_accuracy');
                $this->info('Cache cleared, forcing update...');
            }
            
            $this->info('Calculating prediction accuracy...');
            
            $accuracy = SimpleAccuracyService::getCurrentAccuracy();
            
            $this->info("Current prediction accuracy: {$accuracy}%");
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error('Failed to update accuracy: ' . $e->getMessage());
            
            return Command::FAILURE;
        }
    }
}