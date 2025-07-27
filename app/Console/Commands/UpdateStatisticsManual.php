<?php

namespace App\Console\Commands;

use App\Services\StatisticsService;
use Illuminate\Console\Command;

class UpdateStatisticsManual extends Command
{
    protected $signature = 'statistics:update-manual';
    
    protected $description = 'Manually update prediction statistics';

    public function handle()
    {
        $this->info('Updating prediction statistics...');
        
        try {
            $statisticsService = new StatisticsService();
            $statisticsService->updateAllStatistics();
            
            $this->info('✓ Statistics updated successfully!');
            
            // Show current statistics
            $this->info('Current statistics:');
            $this->showCurrentStats();
            
        } catch (\Exception $e) {
            $this->error('Failed to update statistics: ' . $e->getMessage());
            return Command::FAILURE;
        }
        
        return Command::SUCCESS;
    }
    
    private function showCurrentStats()
    {
        try {
            $pdo = new \PDO('mysql:host=192.168.56.56;dbname=calcio', 'homestead', 'secret');
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            
            $stmt = $pdo->query("
                SELECT 
                    prediction_type,
                    total_predictions,
                    correct_predictions,
                    accuracy_percentage
                FROM prediction_statistics 
                ORDER BY prediction_type
            ");
            
            while ($row = $stmt->fetch()) {
                $this->line("  {$row['prediction_type']}: {$row['correct_predictions']}/{$row['total_predictions']} ({$row['accuracy_percentage']}%)");
            }
            
        } catch (\Exception $e) {
            $this->warn('Could not display current stats: ' . $e->getMessage());
        }
    }
}