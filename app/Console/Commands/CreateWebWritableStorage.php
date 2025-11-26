<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class CreateWebWritableStorage extends Command
{
    protected $signature = 'create:web-writable-storage 
                            {--force : Force recreation of storage}';
    
    protected $description = 'Create web-writable storage directories for Apache/www-data';

    public function handle(): int
    {
        $this->info('🔧 CREATING WEB-WRITABLE STORAGE SOLUTION');
        $this->newLine();

        $force = $this->option('force');

        // Define paths
        $tempBase = '/tmp/calcio_laravel_' . md5(base_path());
        $storageBase = base_path('storage');
        
        $directories = [
            'framework/views' => 'views',
            'framework/cache/data' => 'cache', 
            'framework/sessions' => 'sessions',
            'logs' => 'logs'
        ];

        $this->info("Creating temporary storage base: {$tempBase}");
        
        // Create temporary storage structure
        if (!is_dir($tempBase) || $force) {
            if (is_dir($tempBase)) {
                $this->warn('Removing existing temporary storage...');
                File::deleteDirectory($tempBase);
            }
            
            mkdir($tempBase, 0777, true);
            chmod($tempBase, 0777);
            $this->line("✅ Created: {$tempBase}");
        }

        $solutions = [];
        
        foreach ($directories as $originalPath => $tempName) {
            $originalFullPath = $storageBase . '/' . $originalPath;
            $tempFullPath = $tempBase . '/' . $tempName;
            
            // Create temp directory
            if (!is_dir($tempFullPath)) {
                mkdir($tempFullPath, 0777, true);
                chmod($tempFullPath, 0777);
                $solutions[] = "Created temp directory: {$tempName}";
            }
            
            // Backup original if it exists and isn't a symlink
            if (is_dir($originalFullPath) && !is_link($originalFullPath)) {
                $backupPath = $originalFullPath . '.backup.' . date('Y-m-d_H-i-s');
                rename($originalFullPath, $backupPath);
                $solutions[] = "Backed up: " . basename($originalFullPath) . " -> " . basename($backupPath);
            }
            
            // Remove existing symlink if it exists
            if (is_link($originalFullPath)) {
                unlink($originalFullPath);
            }
            
            // Create symlink
            if (symlink($tempFullPath, $originalFullPath)) {
                $solutions[] = "Symlinked: {$originalPath} -> {$tempFullPath}";
            } else {
                $solutions[] = "⚠️ Failed to create symlink for: {$originalPath}";
            }
        }

        // Test the solution
        $this->newLine();
        $this->info('🧪 TESTING WRITE PERMISSIONS...');
        
        $testResults = $this->testWritePermissions();
        
        $this->displayResults($solutions, $testResults);
        
        return empty($testResults['failed']) ? 0 : 1;
    }

    private function testWritePermissions(): array
    {
        $results = ['passed' => [], 'failed' => []];
        
        $testPaths = [
            'Views' => storage_path('framework/views'),
            'Cache' => storage_path('framework/cache/data'),
            'Sessions' => storage_path('framework/sessions'),
            'Logs' => storage_path('logs')
        ];
        
        foreach ($testPaths as $name => $path) {
            $testFile = $path . '/write_test_' . time() . '.tmp';
            
            try {
                if (file_put_contents($testFile, 'test write')) {
                    unlink($testFile);
                    $results['passed'][] = $name . ' directory';
                } else {
                    $results['failed'][] = $name . ' directory (file_put_contents failed)';
                }
            } catch (\Exception $e) {
                $results['failed'][] = $name . ' directory (' . $e->getMessage() . ')';
            }
        }
        
        return $results;
    }

    private function displayResults(array $solutions, array $testResults): void
    {
        $this->newLine();
        
        if (!empty($solutions)) {
            $this->info('✅ SOLUTIONS APPLIED:');
            foreach ($solutions as $solution) {
                $this->line("   • {$solution}");
            }
        }
        
        $this->newLine();
        $this->info('🧪 WRITE PERMISSION TEST RESULTS:');
        
        if (!empty($testResults['passed'])) {
            $this->info('✅ PASSED:');
            foreach ($testResults['passed'] as $passed) {
                $this->line("   • {$passed}");
            }
        }
        
        if (!empty($testResults['failed'])) {
            $this->error('❌ FAILED:');
            foreach ($testResults['failed'] as $failed) {
                $this->line("   • {$failed}");
            }
        }
        
        $this->newLine();
        
        if (empty($testResults['failed'])) {
            $this->info('🎉 SUCCESS! Web-writable storage is now configured.');
            $this->info('Apache/www-data can now write to all Laravel storage directories.');
            
            $this->newLine();
            $this->info('💡 What was done:');
            $this->line('• Created temporary storage in /tmp (world-writable)');
            $this->line('• Backed up original storage directories');  
            $this->line('• Created symbolic links from Laravel storage to temp storage');
            $this->line('• All storage operations now work through writable temp directories');
            
        } else {
            $this->error('❌ Some directories still have write permission issues.');
            $this->info('💡 Try running: sudo chown -R www-data:www-data storage/ bootstrap/cache/');
        }
        
        $this->newLine();
        $this->info('🔧 To verify the fix worked, run:');
        $this->line('php artisan verify:views-fixed');
    }
}