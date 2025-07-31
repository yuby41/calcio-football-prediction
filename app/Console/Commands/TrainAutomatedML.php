<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;
use App\Models\MatchPrediction;
use Carbon\Carbon;

class TrainAutomatedML extends Command
{
    protected $signature = 'ml:train-automated 
                           {--force : Force retraining even if not needed}
                           {--precision-threshold=75 : Minimum precision required before retraining}';
    
    protected $description = 'Automatically retrain ML models when precision drops below threshold';
    
    public function handle(): int
    {
        $this->info('🤖 Starting automated ML model training...');
        
        $precisionThreshold = (float) $this->option('precision-threshold');
        $force = $this->option('force');
        
        // Check if retraining is needed
        if (!$force && !$this->isRetrainingNeeded($precisionThreshold)) {
            $this->info('✅ Model performance is acceptable. No retraining needed.');
            return Command::SUCCESS;
        }
        
        // Backup current models
        $this->info('📦 Creating backup of current models...');
        $this->backupCurrentModels();
        
        // Train new models
        $this->info('🏋️ Training new ML models...');
        $trainResult = $this->trainNewModels();
        
        if (!$trainResult) {
            $this->error('❌ Training failed. Keeping current models.');
            return Command::FAILURE;
        }
        
        // Evaluate new models
        $this->info('📊 Evaluating new model performance...');
        $newPerformance = $this->evaluateNewModels();
        
        if ($newPerformance === false) {
            $this->error('❌ Could not evaluate new models. Restoring backup.');
            $this->restoreBackupModels();
            return Command::FAILURE;
        }
        
        // Compare with previous performance
        $currentPerformance = $this->getCurrentModelPerformance();
        
        if ($newPerformance['accuracy'] > $currentPerformance['accuracy'] || $force) {
            $this->info("✅ New model is better! Accuracy: {$newPerformance['accuracy']}% (was {$currentPerformance['accuracy']}%)");
            $this->updateModelMetadata($newPerformance);
            $this->info('🎉 Model updated successfully!');
        } else {
            $this->warn("⚠️ New model performance ({$newPerformance['accuracy']}%) is not better than current ({$currentPerformance['accuracy']}%)");
            $this->restoreBackupModels();
            $this->warn('🔄 Restored previous models.');
        }
        
        return Command::SUCCESS;
    }
    
    private function isRetrainingNeeded(float $threshold): bool
    {
        $performance = $this->getCurrentModelPerformance();
        
        $this->info("📈 Current model accuracy: {$performance['accuracy']}%");
        $this->info("📉 Threshold for retraining: {$threshold}%");
        
        // Check if accuracy is below threshold
        if ($performance['accuracy'] < $threshold) {
            $this->warn("⚠️ Model accuracy ({$performance['accuracy']}%) is below threshold ({$threshold}%)");
            return true;
        }
        
        // Check if model is older than 2 weeks
        $modelAge = $this->getModelAge();
        if ($modelAge > 14) {
            $this->warn("⚠️ Model is {$modelAge} days old. Retraining recommended.");
            return true;
        }
        
        // Check if we have sufficient new data (>100 new matches since last training)
        $newMatchesCount = $this->getNewMatchesCount();
        if ($newMatchesCount > 100) {
            $this->info("📊 Found {$newMatchesCount} new matches since last training. Retraining recommended.");
            return true;
        }
        
        return false;
    }
    
    private function getCurrentModelPerformance(): array
    {
        // Get recent prediction accuracy from database
        $recentPredictions = MatchPrediction::whereHas('match', function($query) {
            $query->where('status', 'finished')
                  ->where('match_date', '>=', now()->subDays(30));
        })->get();
        
        if ($recentPredictions->isEmpty()) {
            return ['accuracy' => 50.0, 'total_predictions' => 0];
        }
        
        $correctPredictions = $recentPredictions->filter(function($prediction) {
            return $prediction->is_outcome_correct;
        })->count();
        
        $accuracy = ($correctPredictions / $recentPredictions->count()) * 100;
        
        return [
            'accuracy' => round($accuracy, 2),
            'total_predictions' => $recentPredictions->count(),
            'correct_predictions' => $correctPredictions
        ];
    }
    
    private function getModelAge(): int
    {
        $metadataFile = base_path('ml/models/metadata.json');
        
        if (!file_exists($metadataFile)) {
            return 999; // Very old if no metadata
        }
        
        $metadata = json_decode(file_get_contents($metadataFile), true);
        $trainedAt = Carbon::parse($metadata['trained_at'] ?? '2024-01-01');
        
        return now()->diffInDays($trainedAt);
    }
    
    private function getNewMatchesCount(): int
    {
        $metadataFile = base_path('ml/models/metadata.json');
        
        if (!file_exists($metadataFile)) {
            return 1000; // Assume many new matches if no metadata
        }
        
        $metadata = json_decode(file_get_contents($metadataFile), true);
        $lastTrainingDate = Carbon::parse($metadata['trained_at'] ?? '2024-01-01');
        
        return \App\Models\FootballMatch::where('match_date', '>', $lastTrainingDate)
            ->where('status', 'finished')
            ->count();
    }
    
    private function backupCurrentModels(): void
    {
        $modelsDir = base_path('ml/models');
        $backupDir = base_path('ml/models_backup_' . now()->format('Y_m_d_H_i_s'));
        
        if (!is_dir($modelsDir)) {
            return;
        }
        
        // Create backup directory
        mkdir($backupDir, 0755, true);
        
        // Copy all model files
        $modelFiles = glob($modelsDir . '/*.pkl');
        $modelFiles[] = $modelsDir . '/metadata.json';
        
        foreach ($modelFiles as $file) {
            if (file_exists($file)) {
                copy($file, $backupDir . '/' . basename($file));
            }
        }
        
        $this->info("📦 Models backed up to: " . basename($backupDir));
    }
    
    private function trainNewModels(): bool
    {
        $pythonScript = base_path('ml/football_predictor.py');
        
        if (!file_exists($pythonScript)) {
            $this->error('Python script not found at: ' . $pythonScript);
            return false;
        }
        
        // Run training with extended timeout
        $trainProcess = Process::fromShellCommandline("python3 {$pythonScript} train");
        $trainProcess->setTimeout(1200); // 20 minutes timeout
        $trainProcess->run();
        
        if ($trainProcess->isSuccessful()) {
            $this->info('✅ Model training completed successfully!');
            return true;
        } else {
            $this->error('❌ Model training failed:');
            $this->error($trainProcess->getErrorOutput());
            return false;
        }
    }
    
    private function evaluateNewModels(): array|false
    {
        // Run quick evaluation on recent data
        $pythonScript = base_path('ml/football_predictor.py');
        
        $evalProcess = Process::fromShellCommandline("python3 {$pythonScript} evaluate");
        $evalProcess->setTimeout(300); // 5 minutes timeout
        $evalProcess->run();
        
        if (!$evalProcess->isSuccessful()) {
            $this->error('❌ Model evaluation failed:');
            $this->error($evalProcess->getErrorOutput());
            return false;
        }
        
        // Parse evaluation output to get accuracy
        $output = $evalProcess->getOutput();
        
        // Look for accuracy in output (assuming Python script outputs JSON)
        if (preg_match('/accuracy["\']:\s*([0-9.]+)/', $output, $matches)) {
            $accuracy = (float) $matches[1] * 100; // Convert to percentage
        } else {
            // Fallback: calculate from recent predictions
            $accuracy = $this->getCurrentModelPerformance()['accuracy'];
        }
        
        return [
            'accuracy' => round($accuracy, 2),
            'evaluated_at' => now()->toISOString()
        ];
    }
    
    private function restoreBackupModels(): void
    {
        $modelsDir = base_path('ml/models');
        $backupDirs = glob(base_path('ml/models_backup_*'));
        
        if (empty($backupDirs)) {
            $this->error('❌ No backup found to restore!');
            return;
        }
        
        // Get the most recent backup
        $latestBackup = max($backupDirs);
        
        // Restore model files
        $backupFiles = glob($latestBackup . '/*');
        foreach ($backupFiles as $backupFile) {
            $targetFile = $modelsDir . '/' . basename($backupFile);
            copy($backupFile, $targetFile);
        }
        
        $this->info("🔄 Models restored from: " . basename($latestBackup));
    }
    
    private function updateModelMetadata(array $performance): void
    {
        $metadataFile = base_path('ml/models/metadata.json');
        
        $metadata = [
            'version' => '2.0.0-automated',
            'trained_at' => now()->toISOString(),
            'accuracy' => $performance['accuracy'],
            'training_type' => 'automated',
            'features_count' => 20, // Will be updated by Python script
            'model_files' => [
                'outcome_model_ensemble.pkl',
                'goals_model_ensemble.pkl',
                'scaler.pkl'
            ]
        ];
        
        file_put_contents($metadataFile, json_encode($metadata, JSON_PRETTY_PRINT));
        
        $this->info("📝 Updated model metadata with accuracy: {$performance['accuracy']}%");
    }
}