<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class TrainPredictionModels extends Command
{
    protected $signature = 'ml:train';
    
    protected $description = 'Train machine learning models for match predictions';
    
    public function handle(): int
    {
        $this->info('Starting ML model training...');
        
        $pythonScript = base_path('ml/football_predictor.py');
        
        if (!file_exists($pythonScript)) {
            $this->error('Python script not found at: ' . $pythonScript);
            return Command::FAILURE;
        }
        
        // Check if Python is available
        $checkPython = Process::fromShellCommandline('python3 --version');
        $checkPython->run();
        
        if (!$checkPython->isSuccessful()) {
            $this->error('Python3 is not available. Please install Python 3.8+');
            return Command::FAILURE;
        }
        
        // Install requirements if needed
        $requirementsFile = base_path('ml/requirements.txt');
        if (file_exists($requirementsFile)) {
            $this->info('Installing Python dependencies...');
            $installDeps = Process::fromShellCommandline("pip3 install -r {$requirementsFile}");
            $installDeps->setTimeout(300); // 5 minutes timeout
            $installDeps->run();
            
            if (!$installDeps->isSuccessful()) {
                $this->warn('Failed to install some dependencies. Continuing anyway...');
                $this->warn($installDeps->getErrorOutput());
            }
        }
        
        // Run training
        $this->info('Running model training...');
        $trainProcess = Process::fromShellCommandline("python3 {$pythonScript} train");
        $trainProcess->setTimeout(600); // 10 minutes timeout
        $trainProcess->run();
        
        if ($trainProcess->isSuccessful()) {
            $this->info('Model training completed successfully!');
            $this->line($trainProcess->getOutput());
            return Command::SUCCESS;
        } else {
            $this->error('Model training failed:');
            $this->error($trainProcess->getErrorOutput());
            return Command::FAILURE;
        }
    }
}