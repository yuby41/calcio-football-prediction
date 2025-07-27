<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckEnvironment extends Command
{
    protected $signature = 'env:check';
    protected $description = 'Check if the environment is properly configured for Homestead VM';

    public function handle()
    {
        $this->info('Checking environment configuration...');
        
        // Check if we're in the right directory structure
        $currentPath = base_path();
        $this->info("Current path: {$currentPath}");
        
        // Check database connection
        try {
            DB::connection()->getPdo();
            $this->info('✓ Database connection: OK');
            
            // Get some basic stats
            $teamsCount = DB::table('teams')->count();
            $matchesCount = DB::table('matches')->count();
            $predictionsCount = DB::table('match_predictions')->count();
            
            $this->info("✓ Teams in database: {$teamsCount}");
            $this->info("✓ Matches in database: {$matchesCount}");
            $this->info("✓ Predictions in database: {$predictionsCount}");
            
        } catch (\Exception $e) {
            $this->error('✗ Database connection failed: ' . $e->getMessage());
            $this->warn('Make sure you are running this from within the Homestead VM');
            $this->warn('Commands to access VM:');
            $this->warn('  vagrant ssh');
            $this->warn('  cd /home/vagrant/code/Calcio');
            return Command::FAILURE;
        }
        
        // Check Python environment
        $this->info('Checking Python environment...');
        
        $pythonCheck = shell_exec('python3 --version 2>&1');
        if ($pythonCheck) {
            $this->info("✓ Python: " . trim($pythonCheck));
        } else {
            $this->warn('✗ Python not found');
        }
        
        // Check ML models
        $modelsPath = base_path('ml/models');
        if (is_dir($modelsPath) && file_exists($modelsPath . '/metadata.json')) {
            $this->info('✓ ML models: Found');
        } else {
            $this->warn('✗ ML models: Not found - run php artisan ml:train');
        }
        
        // Check if we're likely in Homestead VM
        $hostname = gethostname();
        $user = get_current_user();
        
        if ($hostname === 'homestead' || $user === 'vagrant') {
            $this->info('✓ Environment: Likely running in Homestead VM');
        } else {
            $this->warn("? Environment: hostname={$hostname}, user={$user}");
            $this->warn('  Make sure you are in the Homestead VM for database access');
        }
        
        $this->info('Environment check completed!');
        
        return Command::SUCCESS;
    }
}