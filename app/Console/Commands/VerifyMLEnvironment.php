<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class VerifyMLEnvironment extends Command
{
    protected $signature = 'ml:verify-env {--fix : Automatically fix environment issues}';
    protected $description = 'Verify ML environment is properly configured and fix issues if needed';

    public function handle()
    {
        $this->info("🔍 Verificando entorno ML de Calcio...");
        
        $issues = [];
        $canAutoFix = $this->option('fix');
        
        // Verificar entorno virtual
        if (!$this->checkVirtualEnvironment()) {
            $issues[] = 'virtual_environment';
            if ($canAutoFix) {
                $this->info("🔧 Configurando entorno virtual automáticamente...");
                $this->fixVirtualEnvironment();
            }
        }
        
        // Verificar dependencias
        if (!$this->checkDependencies()) {
            $issues[] = 'dependencies';
            if ($canAutoFix) {
                $this->info("📦 Instalando dependencias automáticamente...");
                $this->fixDependencies();
            }
        }
        
        // Verificar archivos ML
        if (!$this->checkMLFiles()) {
            $issues[] = 'ml_files';
            $this->warn("⚠️  Algunos archivos ML están faltando. Ejecute ml:train-enhanced para generar modelos.");
        }
        
        // Verificar permisos
        if (!$this->checkPermissions()) {
            $issues[] = 'permissions';
            if ($canAutoFix) {
                $this->info("🔐 Corrigiendo permisos automáticamente...");
                $this->fixPermissions();
            }
        }
        
        // Mostrar resultados
        if (empty($issues)) {
            $this->info("✅ Entorno ML completamente funcional");
            $this->testPrediction();
            return 0;
        } else {
            $this->displayIssues($issues, $canAutoFix);
            return $canAutoFix ? $this->recheck() : 1;
        }
    }
    
    private function checkVirtualEnvironment(): bool
    {
        $envPath = base_path('ml_env');
        $activateScript = $envPath . '/bin/activate';
        
        if (!is_dir($envPath) || !file_exists($activateScript)) {
            $this->line("❌ Entorno virtual no encontrado");
            return false;
        }
        
        $this->line("✅ Entorno virtual disponible");
        return true;
    }
    
    private function checkDependencies(): bool
    {
        $basePath = base_path();
        
        $process = Process::fromShellCommandline(
            "/bin/bash -c 'cd {$basePath} && source ml_env/bin/activate && python -c \"import pandas, numpy, sklearn, xgboost, lightgbm, joblib, sqlalchemy, pymysql; print(\\\"OK\\\")\"'"
        );
        $process->setTimeout(15);
        $process->run();
        
        if ($process->isSuccessful() && trim($process->getOutput()) === 'OK') {
            $this->line("✅ Dependencias Python disponibles");
            return true;
        }
        
        $this->line("❌ Dependencias Python faltantes o con errores");
        return false;
    }
    
    private function checkMLFiles(): bool
    {
        $mlPath = base_path('ml');
        $requiredFiles = [
            'enhanced_football_predictor.py',
            'config.py',
            'requirements.txt'
        ];
        
        $missing = [];
        foreach ($requiredFiles as $file) {
            if (!file_exists($mlPath . '/' . $file)) {
                $missing[] = $file;
            }
        }
        
        if (!empty($missing)) {
            $this->line("❌ Archivos ML faltantes: " . implode(', ', $missing));
            return false;
        }
        
        $this->line("✅ Archivos ML principales disponibles");
        return true;
    }
    
    private function checkPermissions(): bool
    {
        $paths = [
            base_path('ml_env'),
            base_path('ml'),
            base_path('ml/models')
        ];
        
        foreach ($paths as $path) {
            if (is_dir($path) && !is_writable($path)) {
                $this->line("❌ Permisos insuficientes en: {$path}");
                return false;
            }
        }
        
        $this->line("✅ Permisos correctos");
        return true;
    }
    
    private function fixVirtualEnvironment(): bool
    {
        $basePath = base_path();
        
        // Ejecutar script de configuración automática
        $setupScript = $basePath . '/setup_ml_env.sh';
        
        if (!file_exists($setupScript)) {
            $this->error("Script de configuración no encontrado");
            return false;
        }
        
        $process = Process::fromShellCommandline("bash {$setupScript}");
        $process->setTimeout(600); // 10 minutes
        $process->run();
        
        if ($process->isSuccessful()) {
            $this->info("✅ Entorno virtual configurado correctamente");
            return true;
        } else {
            $this->error("❌ Error configurando entorno virtual: " . $process->getErrorOutput());
            return false;
        }
    }
    
    private function fixDependencies(): bool
    {
        $basePath = base_path();
        
        $process = Process::fromShellCommandline(
            "/bin/bash -c 'cd {$basePath} && source ml_env/bin/activate && pip install -r requirements.txt'"
        );
        $process->setTimeout(300); // 5 minutes
        $process->run();
        
        if ($process->isSuccessful()) {
            $this->info("✅ Dependencias instaladas correctamente");
            return true;
        } else {
            $this->error("❌ Error instalando dependencias: " . $process->getErrorOutput());
            return false;
        }
    }
    
    private function fixPermissions(): bool
    {
        $basePath = base_path();
        
        $commands = [
            "chmod -R 755 {$basePath}/ml_env",
            "chmod -R 755 {$basePath}/ml",
            "mkdir -p {$basePath}/ml/models && chmod 755 {$basePath}/ml/models"
        ];
        
        foreach ($commands as $command) {
            $process = Process::fromShellCommandline($command);
            $process->run();
            
            if (!$process->isSuccessful()) {
                $this->warn("No se pudo ejecutar: {$command}");
            }
        }
        
        $this->info("✅ Permisos actualizados");
        return true;
    }
    
    private function testPrediction(): void
    {
        $this->info("🧪 Realizando test de predicción...");
        
        $basePath = base_path();
        $mlPath = base_path('ml');
        
        $process = Process::fromShellCommandline(
            "/bin/bash -c 'cd {$basePath} && source ml_env/bin/activate && python {$mlPath}/enhanced_football_predictor.py predict 3430 4775'"
        );
        $process->setTimeout(30);
        $process->run();
        
        if ($process->isSuccessful()) {
            $output = $process->getOutput();
            $prediction = json_decode($output, true);
            
            if ($prediction && isset($prediction['model_version'])) {
                $this->info("✅ Test de predicción exitoso");
                $this->line("   Versión: " . $prediction['model_version']);
                $this->line("   Confianza: " . ($prediction['confidence_score'] ?? 'N/A'));
            } else {
                $this->warn("⚠️  Predicción generada pero formato inválido");
            }
        } else {
            $this->warn("⚠️  Test de predicción falló: " . $process->getErrorOutput());
        }
    }
    
    private function displayIssues(array $issues, bool $canAutoFix): void
    {
        $this->error("❌ Se encontraron problemas en el entorno ML:");
        
        foreach ($issues as $issue) {
            switch ($issue) {
                case 'virtual_environment':
                    $this->line("   • Entorno virtual no configurado");
                    break;
                case 'dependencies':
                    $this->line("   • Dependencias Python faltantes");
                    break;
                case 'ml_files':
                    $this->line("   • Archivos ML faltantes");
                    break;
                case 'permissions':
                    $this->line("   • Problemas de permisos");
                    break;
            }
        }
        
        if (!$canAutoFix) {
            $this->newLine();
            $this->info("💡 Para solucionar automáticamente: php artisan ml:verify-env --fix");
            $this->info("💡 O ejecute manualmente: ./setup_ml_env.sh");
        }
    }
    
    private function recheck(): int
    {
        $this->newLine();
        $this->info("🔄 Re-verificando entorno...");
        
        // Verificar nuevamente después de las correcciones
        if ($this->checkVirtualEnvironment() && $this->checkDependencies()) {
            $this->info("✅ Entorno ML corregido exitosamente");
            $this->testPrediction();
            return 0;
        } else {
            $this->error("❌ Algunos problemas persisten");
            return 1;
        }
    }
}