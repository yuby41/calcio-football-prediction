<?php

namespace App\Console\Commands;

use App\Services\StatisticsService;
use Illuminate\Console\Command;

class UpdateStatistics extends Command
{
    protected $signature = 'statistics:update';
    
    protected $description = 'Update prediction statistics and accuracy metrics';
    
    public function __construct(private StatisticsService $statisticsService)
    {
        parent::__construct();
    }
    
    public function handle(): int
    {
        $this->info('Updating prediction statistics...');
        
        try {
            $this->statisticsService->updateAllStatistics();
            $this->info('Statistics updated successfully!');
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error('Failed to update statistics: ' . $e->getMessage());
            
            return Command::FAILURE;
        }
    }
}