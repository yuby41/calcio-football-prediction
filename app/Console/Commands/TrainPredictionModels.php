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

        $python = config(
            'ml_models.paths.python_env',
            base_path('ml_env/bin/python')
        );

        $pythonScript = base_path('ml/football_predictor.py');

        if (!is_file($python) || !is_executable($python)) {
            $this->error("ML Python interpreter not found or not executable: {$python}");
            $this->line('Run ./setup_ml_env.sh first.');

            return Command::FAILURE;
        }

        if (!is_file($pythonScript)) {
            $this->error("Python script not found: {$pythonScript}");

            return Command::FAILURE;
        }

        $this->info("Using Python: {$python}");
        $this->info('Running model training...');

        $trainProcess = new Process(
            [$python, $pythonScript, 'train'],
            base_path()
        );

        $trainProcess->setTimeout(600);
        $trainProcess->run();

        if (!$trainProcess->isSuccessful()) {
            $this->error('Model training failed:');
            $this->error($trainProcess->getErrorOutput());

            return Command::FAILURE;
        }

        $this->info('Model training completed successfully!');
        $this->line($trainProcess->getOutput());

        return Command::SUCCESS;
    }
}