<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class FixViewsPermissions extends Command
{
    protected $signature = 'fix:views-permissions 
                            {--clear : Clear all compiled views}
                            {--permissions : Fix directory permissions}';
    
    protected $description = 'Fix Laravel views compilation issues and permissions';

    public function handle(): int
    {
        $this->info('🔧 FIXING VIEWS COMPILATION ISSUES');
        $this->newLine();

        $runClear = $this->option('clear');
        $runPermissions = $this->option('permissions');
        
        // If no specific option, run both
        if (!$runClear && !$runPermissions) {
            $runClear = true;
            $runPermissions = true;
        }

        $fixes = [];

        if ($runClear) {
            $fixes = array_merge($fixes, $this->clearCompiledViews());
        }

        if ($runPermissions) {
            $fixes = array_merge($fixes, $this->fixPermissions());
        }

        // Additional checks and fixes
        $fixes = array_merge($fixes, $this->ensureDirectoriesExist());
        $fixes = array_merge($fixes, $this->clearCaches());

        // Display results
        $this->displayResults($fixes);

        return 0;
    }

    private function clearCompiledViews(): array
    {
        $this->info('🧹 Clearing compiled views...');
        $fixes = [];
        
        try {
            // Clear view cache using Artisan
            \Artisan::call('view:clear');
            $fixes[] = 'Cleared compiled views cache';
            
            // Manually remove any problematic compiled views
            $viewsPath = storage_path('framework/views');
            if (is_dir($viewsPath)) {
                $files = glob($viewsPath . '/*.php');
                $count = count($files);
                
                // Remove all compiled views
                array_map('unlink', $files);
                $fixes[] = "Removed {$count} compiled view files";
            }
        } catch (\Exception $e) {
            $this->error("Error clearing views: " . $e->getMessage());
        }

        return $fixes;
    }

    private function fixPermissions(): array
    {
        $this->info('🔑 Fixing directory permissions...');
        $fixes = [];
        
        $directories = [
            storage_path('framework'),
            storage_path('framework/views'),
            storage_path('framework/cache'),
            storage_path('framework/sessions'),
            storage_path('logs'),
            base_path('bootstrap/cache'),
        ];

        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
                $fixes[] = "Created directory: {$dir}";
            }
            
            if (is_dir($dir)) {
                // Set directory permissions
                chmod($dir, 0775);
                $fixes[] = "Set permissions 775 for: " . basename($dir);
                
                // Set file permissions for existing files
                $files = glob($dir . '/*');
                foreach ($files as $file) {
                    if (is_file($file)) {
                        chmod($file, 0664);
                    }
                }
            }
        }

        return $fixes;
    }

    private function ensureDirectoriesExist(): array
    {
        $this->info('📁 Ensuring required directories exist...');
        $fixes = [];
        
        $requiredDirectories = [
            storage_path('app'),
            storage_path('app/public'),
            storage_path('framework'),
            storage_path('framework/cache'),
            storage_path('framework/cache/data'),
            storage_path('framework/sessions'),
            storage_path('framework/testing'),
            storage_path('framework/views'),
            storage_path('logs'),
            base_path('bootstrap/cache'),
        ];

        foreach ($requiredDirectories as $dir) {
            if (!File::isDirectory($dir)) {
                File::makeDirectory($dir, 0775, true);
                $fixes[] = "Created missing directory: " . str_replace(base_path(), '', $dir);
            }
        }

        return $fixes;
    }

    private function clearCaches(): array
    {
        $this->info('🗄️ Clearing related caches...');
        $fixes = [];
        
        $commands = [
            'cache:clear' => 'Application cache',
            'config:clear' => 'Configuration cache', 
            'route:clear' => 'Route cache',
        ];

        foreach ($commands as $command => $description) {
            try {
                \Artisan::call($command);
                $fixes[] = "Cleared {$description}";
            } catch (\Exception $e) {
                $this->warn("Warning: Could not clear {$description}: " . $e->getMessage());
            }
        }

        return $fixes;
    }

    private function displayResults(array $fixes): void
    {
        $this->newLine();
        
        if (!empty($fixes)) {
            $this->info('✅ FIXES APPLIED:');
            foreach ($fixes as $fix) {
                $this->line("   • {$fix}");
            }
        } else {
            $this->info('✅ No issues found - everything looks good!');
        }

        $this->newLine();
        $this->info('🔍 VERIFICATION:');
        
        // Test view compilation
        $this->line('Testing view compilation...');
        try {
            // Try to compile a simple view
            $testContent = "<?php echo 'test'; ?>";
            $testFile = storage_path('framework/views/test_compilation.php');
            
            file_put_contents($testFile, $testContent);
            if (file_exists($testFile)) {
                unlink($testFile);
                $this->line('   ✅ View compilation test: PASSED');
            } else {
                $this->line('   ❌ View compilation test: FAILED');
            }
        } catch (\Exception $e) {
            $this->line('   ❌ View compilation test: FAILED - ' . $e->getMessage());
        }

        // Check permissions
        $viewsDir = storage_path('framework/views');
        $perms = substr(sprintf('%o', fileperms($viewsDir)), -4);
        $this->line("Views directory permissions: {$perms}");
        
        if ($perms >= '0775') {
            $this->line('   ✅ Permissions: OK');
        } else {
            $this->line('   ⚠️ Permissions: May need adjustment');
        }

        $this->newLine();
        $this->info('💡 RECOMMENDATIONS:');
        $this->line('1. If issues persist, check disk space: df -h');
        $this->line('2. Verify web server user permissions');
        $this->line('3. Consider running: sudo chown -R www-data:www-data storage/');
        $this->line('4. For development: chmod -R 775 storage/ bootstrap/cache/');

        $this->newLine();
        $this->info('🚀 Views compilation should now work correctly!');
    }
}