<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;

class SwitchMLModel extends Command
{
    protected $signature = 'ml:switch-model 
                           {model? : The ML model to use (enhanced|simple|ensemble)}
                           {--list : List available models}
                           {--show : Show current active model}
                           {--config : Show model configuration}';

    protected $description = 'Switch between different ML prediction models';

    private array $availableModels = [
        'enhanced' => [
            'name' => 'Enhanced ML Ensemble',
            'script' => 'enhanced_football_predictor.py',
            'description' => 'XGBoost + LightGBM + Neural Network + Random Forest ensemble',
            'accuracy' => '~58%',
            'features' => 35,
            'training_time' => 'High (5-10 minutes)',
            'prediction_time' => 'Medium (1-2 seconds)'
        ],
        'simple' => [
            'name' => 'Simple Statistical Predictor',
            'script' => 'simple_effective_predictor.py',
            'description' => 'Fast statistical model with Poisson distribution',
            'accuracy' => '~52%',
            'features' => 12,
            'training_time' => 'None (statistical)',
            'prediction_time' => 'Fast (<0.5 seconds)'
        ],
        'ensemble' => [
            'name' => 'Hybrid Ensemble',
            'script' => 'hybrid_predictor.py',
            'description' => 'Combines enhanced ML with statistical methods',
            'accuracy' => '~55%',
            'features' => 25,
            'training_time' => 'Medium (3-5 minutes)',
            'prediction_time' => 'Medium (1 second)'
        ]
    ];

    public function handle()
    {
        // Handle options first
        if ($this->option('list')) {
            return $this->listModels();
        }

        if ($this->option('show')) {
            return $this->showCurrentModel();
        }

        if ($this->option('config')) {
            return $this->showModelConfig();
        }

        $modelName = $this->argument('model');

        if (!$modelName) {
            $this->error("Please specify a model to switch to.");
            $this->info("Available models: " . implode(', ', array_keys($this->availableModels)));
            $this->info("Use --list to see detailed information about each model.");
            return 1;
        }

        if (!isset($this->availableModels[$modelName])) {
            $this->error("Invalid model: {$modelName}");
            $this->info("Available models: " . implode(', ', array_keys($this->availableModels)));
            return 1;
        }

        return $this->switchModel($modelName);
    }

    private function listModels(): int
    {
        $this->info("Available ML Models:");
        $this->line("");

        $currentModel = $this->getCurrentModel();

        foreach ($this->availableModels as $key => $model) {
            $status = $key === $currentModel ? '✓ ACTIVE' : '  ';
            $this->line("  {$status} <comment>{$key}</comment> - {$model['name']}");
            $this->line("        {$model['description']}");
            $this->line("        Accuracy: {$model['accuracy']} | Features: {$model['features']} | Training: {$model['training_time']}");
            $this->line("");
        }

        return 0;
    }

    private function showCurrentModel(): int
    {
        $currentModel = $this->getCurrentModel();
        
        if (!$currentModel) {
            $this->error("No model is currently configured");
            return 1;
        }

        $model = $this->availableModels[$currentModel] ?? null;
        
        if (!$model) {
            $this->error("Current model '{$currentModel}' is not recognized");
            return 1;
        }

        $this->info("Current Active Model:");
        $this->line("");
        $this->line("  <comment>Name:</comment> {$model['name']} ({$currentModel})");
        $this->line("  <comment>Script:</comment> {$model['script']}");
        $this->line("  <comment>Description:</comment> {$model['description']}");
        $this->line("  <comment>Expected Accuracy:</comment> {$model['accuracy']}");
        $this->line("  <comment>Features:</comment> {$model['features']}");
        $this->line("  <comment>Training Time:</comment> {$model['training_time']}");
        $this->line("  <comment>Prediction Time:</comment> {$model['prediction_time']}");

        return 0;
    }

    private function showModelConfig(): int
    {
        $configPath = config_path('ml_models.php');
        
        if (!File::exists($configPath)) {
            $this->error("ML model configuration file not found: {$configPath}");
            return 1;
        }

        $config = include $configPath;
        
        $this->info("ML Model Configuration:");
        $this->line("");
        $this->line(json_encode($config, JSON_PRETTY_PRINT));

        return 0;
    }

    private function switchModel(string $modelName): int
    {
        $model = $this->availableModels[$modelName];

        $this->info("Switching to: {$model['name']}");
        $this->line("");

        // Verify the script exists
        $scriptPath = base_path("ml/{$model['script']}");
        if (!File::exists($scriptPath)) {
            $this->error("Model script not found: {$scriptPath}");
            $this->line("Please ensure the model files are properly installed.");
            return 1;
        }

        // Create or update configuration
        $this->createModelConfig($modelName, $model);

        // Verify ML environment for enhanced models
        if ($modelName === 'enhanced' || $modelName === 'ensemble') {
            $this->info("Verifying ML environment...");
            
            // Check if models are trained
            $modelsPath = base_path('ml/models');
            $requiredFiles = [
                'enhanced_outcome_model.pkl',
                'enhanced_scaler.pkl', 
                'enhanced_metadata.json'
            ];

            $missingFiles = [];
            foreach ($requiredFiles as $file) {
                if (!File::exists("{$modelsPath}/{$file}")) {
                    $missingFiles[] = $file;
                }
            }

            if (!empty($missingFiles)) {
                $this->warn("Some model files are missing:");
                foreach ($missingFiles as $file) {
                    $this->line("  - {$file}");
                }
                $this->line("");
                $this->info("Run 'php artisan ml:train-enhanced' to train the models first.");
            }

            // Verify ML environment
            $this->call('ml:verify-env');
        }

        $this->info("✅ Successfully switched to {$model['name']}");
        $this->line("");
        $this->line("Next steps:");
        
        if ($modelName === 'enhanced') {
            $this->line("  • Run: php artisan ml:train-enhanced (if not already trained)");
            $this->line("  • Test: php artisan ml:predict 3430 4775");
        } elseif ($modelName === 'simple') {
            $this->line("  • Test: php artisan ml:predict 3430 4775");
            $this->line("  • No training required - uses statistical methods");
        } elseif ($modelName === 'ensemble') {
            $this->line("  • Run: php artisan ml:train-enhanced (for ML component)");
            $this->line("  • Test: php artisan ml:predict 3430 4775");
        }

        return 0;
    }

    private function createModelConfig(string $modelName, array $modelData): void
    {
        $configPath = config_path('ml_models.php');
        
        $config = [
            'active_model' => $modelName,
            'models' => $this->availableModels,
            'paths' => [
                'ml_directory' => base_path('ml'),
                'models_directory' => base_path('ml/models'),
                'python_env' => base_path('ml_env/bin/python'),
            ],
            'settings' => [
                'timeout' => 30, // seconds for predictions
                'max_retries' => 3,
                'cache_predictions' => true,
                'cache_ttl' => 300, // 5 minutes
            ]
        ];

        $configContent = "<?php\n\nreturn " . var_export($config, true) . ";\n";
        
        File::put($configPath, $configContent);
        
        $this->line("Configuration saved to: {$configPath}");
    }

    private function getCurrentModel(): ?string
    {
        $configPath = config_path('ml_models.php');
        
        if (!File::exists($configPath)) {
            return null;
        }

        $config = include $configPath;
        return $config['active_model'] ?? null;
    }
}