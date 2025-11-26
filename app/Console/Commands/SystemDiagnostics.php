<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class SystemDiagnostics extends Command
{
    protected $signature = 'system:diagnostics 
                            {--fix : Auto-fix issues found}';
    
    protected $description = 'Comprehensive system diagnostics for Laravel app issues';

    public function handle(): int
    {
        $this->info('🔍 COMPREHENSIVE SYSTEM DIAGNOSTICS');
        $this->newLine();

        $autoFix = $this->option('fix');
        $issues = [];

        // 1. File System Diagnostics
        $issues = array_merge($issues, $this->checkFileSystem());

        // 2. Permission Diagnostics
        $issues = array_merge($issues, $this->checkPermissions());

        // 3. Laravel Configuration Diagnostics
        $issues = array_merge($issues, $this->checkLaravelConfig());

        // 4. Storage Diagnostics
        $issues = array_merge($issues, $this->checkStorageSetup());

        // 5. PHP Environment Diagnostics
        $issues = array_merge($issues, $this->checkPHPEnvironment());

        // Display results and auto-fix if requested
        $this->displayResults($issues, $autoFix);

        return empty($issues['critical']) ? 0 : 1;
    }

    private function checkFileSystem(): array
    {
        $this->info('📂 FILE SYSTEM DIAGNOSTICS');
        $issues = ['critical' => [], 'warning' => [], 'info' => []];

        // Check disk space
        $diskUsage = disk_free_space(base_path()) / disk_total_space(base_path()) * 100;
        if ($diskUsage > 95) {
            $issues['critical'][] = 'Disk space critically low (>' . (100 - $diskUsage) . '% used)';
        } elseif ($diskUsage > 85) {
            $issues['warning'][] = 'Disk space getting low (>' . (100 - $diskUsage) . '% used)';
        }

        // Check critical directories exist
        $requiredDirs = [
            'storage/framework/views',
            'storage/framework/cache', 
            'storage/framework/sessions',
            'storage/app',
            'storage/logs',
            'bootstrap/cache'
        ];

        foreach ($requiredDirs as $dir) {
            $fullPath = base_path($dir);
            if (!is_dir($fullPath)) {
                $issues['critical'][] = "Missing required directory: {$dir}";
            }
        }

        // Check if directories are writable
        $writableDirs = [
            'storage/framework/views',
            'storage/framework/cache',
            'storage/logs',
            'bootstrap/cache'
        ];

        foreach ($writableDirs as $dir) {
            $fullPath = base_path($dir);
            if (is_dir($fullPath) && !is_writable($fullPath)) {
                $issues['critical'][] = "Directory not writable: {$dir}";
            }
        }

        $this->line("✅ File system check completed");
        return $issues;
    }

    private function checkPermissions(): array
    {
        $this->info('🔐 PERMISSION DIAGNOSTICS');
        $issues = ['critical' => [], 'warning' => [], 'info' => []];

        $criticalPaths = [
            'storage/framework/views' => '0775',
            'storage/framework/cache' => '0775', 
            'storage/logs' => '0775',
            'bootstrap/cache' => '0775'
        ];

        foreach ($criticalPaths as $path => $expectedPerm) {
            $fullPath = base_path($path);
            if (is_dir($fullPath)) {
                $actualPerm = substr(sprintf('%o', fileperms($fullPath)), -4);
                if ($actualPerm < $expectedPerm) {
                    $issues['warning'][] = "Permissions too restrictive for {$path}: {$actualPerm} (expected: {$expectedPerm}+)";
                }
            }
        }

        $this->line("✅ Permissions check completed");
        return $issues;
    }

    private function checkLaravelConfig(): array
    {
        $this->info('⚙️ LARAVEL CONFIGURATION DIAGNOSTICS');
        $issues = ['critical' => [], 'warning' => [], 'info' => []];

        // Check APP_KEY
        if (empty(config('app.key'))) {
            $issues['critical'][] = 'APP_KEY not set in .env file';
        }

        // Check debug mode in production
        if (config('app.env') === 'production' && config('app.debug') === true) {
            $issues['warning'][] = 'Debug mode is enabled in production environment';
        }

        // Check session configuration
        if (config('session.driver') === 'file') {
            $sessionPath = config('session.files');
            if (!is_dir($sessionPath) || !is_writable($sessionPath)) {
                $issues['critical'][] = 'Session directory not writable: ' . $sessionPath;
            }
        }

        // Check cache configuration
        if (config('cache.default') === 'file') {
            $cachePath = config('cache.stores.file.path');
            if (!is_dir($cachePath) || !is_writable($cachePath)) {
                $issues['critical'][] = 'Cache directory not writable: ' . $cachePath;
            }
        }

        $this->line("✅ Laravel configuration check completed");
        return $issues;
    }

    private function checkStorageSetup(): array
    {
        $this->info('💾 STORAGE SETUP DIAGNOSTICS');
        $issues = ['critical' => [], 'warning' => [], 'info' => []];

        // Check storage link
        $publicStoragePath = public_path('storage');
        $storageAppPublic = storage_path('app/public');
        
        if (!is_link($publicStoragePath)) {
            $issues['warning'][] = 'Storage link not created (run: php artisan storage:link)';
        } elseif (!is_dir($storageAppPublic)) {
            $issues['warning'][] = 'Storage app/public directory missing';
        }

        // Check log rotation
        $logFiles = glob(storage_path('logs/*.log'));
        $totalLogSize = array_sum(array_map('filesize', $logFiles));
        if ($totalLogSize > 100 * 1024 * 1024) { // 100MB
            $issues['info'][] = 'Log files are large (' . round($totalLogSize / 1024 / 1024, 2) . 'MB) - consider rotation';
        }

        $this->line("✅ Storage setup check completed");
        return $issues;
    }

    private function checkPHPEnvironment(): array
    {
        $this->info('🐘 PHP ENVIRONMENT DIAGNOSTICS');
        $issues = ['critical' => [], 'warning' => [], 'info' => []];

        // Check PHP version
        if (version_compare(PHP_VERSION, '8.1.0', '<')) {
            $issues['warning'][] = 'PHP version ' . PHP_VERSION . ' is below recommended 8.1+';
        }

        // Check required extensions
        $requiredExtensions = ['openssl', 'pdo', 'mbstring', 'tokenizer', 'xml', 'ctype', 'json'];
        foreach ($requiredExtensions as $ext) {
            if (!extension_loaded($ext)) {
                $issues['critical'][] = "Required PHP extension missing: {$ext}";
            }
        }

        // Check memory limit
        $memoryLimit = ini_get('memory_limit');
        $memoryLimitBytes = $this->convertToBytes($memoryLimit);
        if ($memoryLimitBytes < 128 * 1024 * 1024) { // 128MB
            $issues['warning'][] = "PHP memory limit is low: {$memoryLimit} (recommended: 128M+)";
        }

        // Check max execution time
        $maxExecTime = ini_get('max_execution_time');
        if ($maxExecTime < 60 && $maxExecTime != 0) {
            $issues['info'][] = "Max execution time is short: {$maxExecTime}s (consider increasing for CLI commands)";
        }

        $this->line("✅ PHP environment check completed");
        return $issues;
    }

    private function convertToBytes(string $value): int
    {
        $unit = strtolower(substr($value, -1));
        $number = (int) substr($value, 0, -1);
        
        switch ($unit) {
            case 'g': return $number * 1024 * 1024 * 1024;
            case 'm': return $number * 1024 * 1024;
            case 'k': return $number * 1024;
            default: return (int) $value;
        }
    }

    private function displayResults(array $allIssues, bool $autoFix): void
    {
        $this->newLine();
        
        $criticalCount = count(array_merge(...array_column($allIssues, 'critical')));
        $warningCount = count(array_merge(...array_column($allIssues, 'warning')));
        $infoCount = count(array_merge(...array_column($allIssues, 'info')));

        // Summary
        $this->table(
            ['Severity', 'Count', 'Status'],
            [
                ['Critical', $criticalCount, $criticalCount > 0 ? '❌' : '✅'],
                ['Warnings', $warningCount, $warningCount > 0 ? '⚠️' : '✅'],
                ['Info', $infoCount, $infoCount > 0 ? 'ℹ️' : '✅'],
            ]
        );

        // Display issues
        foreach ($allIssues as $category) {
            if (!empty($category['critical'])) {
                $this->error('❌ CRITICAL ISSUES:');
                foreach ($category['critical'] as $issue) {
                    $this->line("   • {$issue}");
                }
                $this->newLine();
            }
            
            if (!empty($category['warning'])) {
                $this->warn('⚠️ WARNINGS:');
                foreach ($category['warning'] as $issue) {
                    $this->line("   • {$issue}");
                }
                $this->newLine();
            }
            
            if (!empty($category['info'])) {
                $this->info('ℹ️ INFORMATION:');
                foreach ($category['info'] as $issue) {
                    $this->line("   • {$issue}");
                }
                $this->newLine();
            }
        }

        // Auto-fix suggestions
        if ($autoFix || $criticalCount > 0) {
            $this->info('🛠️ RECOMMENDED FIXES:');
            $this->line('1. php artisan fix:views-permissions --clear --permissions');
            $this->line('2. php artisan storage:link');
            $this->line('3. php artisan config:cache');
            $this->line('4. chmod -R 775 storage/ bootstrap/cache/');
            $this->line('5. php artisan view:clear && php artisan cache:clear');
            $this->newLine();
        }

        // Overall health score
        $totalIssues = $criticalCount + $warningCount + $infoCount;
        if ($totalIssues === 0) {
            $this->info('🏆 SYSTEM HEALTH: EXCELLENT - No issues found!');
        } elseif ($criticalCount === 0) {
            $this->info('✅ SYSTEM HEALTH: GOOD - Minor issues only');
        } else {
            $this->error('🚨 SYSTEM HEALTH: NEEDS ATTENTION - Critical issues found');
        }
    }
}