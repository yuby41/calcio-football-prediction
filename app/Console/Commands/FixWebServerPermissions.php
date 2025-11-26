<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class FixWebServerPermissions extends Command
{
    protected $signature = 'fix:webserver-permissions 
                            {--test : Test write permissions}
                            {--acl : Use ACL permissions (requires setfacl)}';
    
    protected $description = 'Fix web server permissions for Laravel (www-data/apache)';

    public function handle(): int
    {
        $this->info('🔧 FIXING WEB SERVER PERMISSIONS');
        $this->info('Detected issue: Web server (www-data) cannot write to storage directories');
        $this->newLine();

        $testMode = $this->option('test');
        $useACL = $this->option('acl');

        // First, detect the issue
        $this->analyzePermissionIssue();

        if ($testMode) {
            return $this->testPermissions();
        }

        // Try different solutions
        $solutions = [];

        // Solution 1: Set more permissive permissions
        $solutions = array_merge($solutions, $this->setPermissivePermissions());

        // Solution 2: Use ACL if available
        if ($useACL && $this->isSetfaclAvailable()) {
            $solutions = array_merge($solutions, $this->setACLPermissions());
        }

        // Solution 3: Create alternative storage structure
        $solutions = array_merge($solutions, $this->createAlternativeStorage());

        $this->displayResults($solutions);

        return 0;
    }

    private function analyzePermissionIssue(): void
    {
        $this->info('🔍 ANALYZING PERMISSION ISSUE');

        // Check current user
        $currentUser = get_current_user();
        $this->line("Current PHP user: {$currentUser}");

        // Check web server user
        $webUser = $this->detectWebServerUser();
        $this->line("Web server user: {$webUser}");

        // Check ownership of critical directories
        $dirs = [
            'storage/framework/views',
            'storage/framework/cache',
            'storage/logs',
            'bootstrap/cache'
        ];

        $this->table(['Directory', 'Owner', 'Group', 'Permissions', 'Writable by Web Server'], 
            array_map(function($dir) use ($webUser) {
                $fullPath = base_path($dir);
                if (!is_dir($fullPath)) return [$dir, 'N/A', 'N/A', 'N/A', '❌ Missing'];
                
                $stat = stat($fullPath);
                $owner = function_exists('posix_getpwuid') ? posix_getpwuid($stat['uid'])['name'] : $stat['uid'];
                $group = function_exists('posix_getgrgid') ? posix_getgrgid($stat['gid'])['name'] : $stat['gid'];
                $perms = substr(sprintf('%o', fileperms($fullPath)), -4);
                $writable = is_writable($fullPath) ? '✅' : '❌';
                
                return [$dir, $owner, $group, $perms, $writable];
            }, $dirs)
        );

        $this->newLine();
    }

    private function detectWebServerUser(): string
    {
        // Try to detect web server user
        $possibleUsers = ['www-data', 'apache', 'nginx', 'httpd'];
        
        foreach ($possibleUsers as $user) {
            if (function_exists('posix_getpwnam') && posix_getpwnam($user)) {
                return $user;
            }
        }
        
        return 'unknown';
    }

    private function setPermissivePermissions(): array
    {
        $this->info('🔧 Setting permissive permissions (777)...');
        $solutions = [];

        $directories = [
            'storage',
            'storage/framework',
            'storage/framework/views',
            'storage/framework/cache',
            'storage/framework/sessions',
            'storage/logs',
            'bootstrap/cache'
        ];

        foreach ($directories as $dir) {
            $fullPath = base_path($dir);
            
            if (!is_dir($fullPath)) {
                mkdir($fullPath, 0777, true);
                $solutions[] = "Created directory: {$dir}";
            }
            
            if (chmod($fullPath, 0777)) {
                $solutions[] = "Set 777 permissions for: {$dir}";
            } else {
                $solutions[] = "⚠️ Failed to set permissions for: {$dir}";
            }
        }

        return $solutions;
    }

    private function isSetfaclAvailable(): bool
    {
        return !empty(shell_exec('which setfacl 2>/dev/null'));
    }

    private function setACLPermissions(): array
    {
        $this->info('🔧 Setting ACL permissions...');
        $solutions = [];

        $webUser = $this->detectWebServerUser();
        if ($webUser === 'unknown') {
            $solutions[] = '⚠️ Could not detect web server user for ACL';
            return $solutions;
        }

        $directories = [
            'storage/framework/views',
            'storage/framework/cache',
            'storage/logs',
            'bootstrap/cache'
        ];

        foreach ($directories as $dir) {
            $fullPath = base_path($dir);
            if (is_dir($fullPath)) {
                // Try to set ACL (this might fail without sudo)
                $command = "setfacl -R -m u:{$webUser}:rwx {$fullPath} 2>/dev/null";
                $result = shell_exec($command);
                
                if ($result === null) {
                    $solutions[] = "Set ACL for {$webUser} on: {$dir}";
                } else {
                    $solutions[] = "⚠️ ACL failed for: {$dir}";
                }
            }
        }

        return $solutions;
    }

    private function createAlternativeStorage(): array
    {
        $this->info('🔧 Creating alternative storage solution...');
        $solutions = [];

        // Create a temporary storage directory in /tmp (usually world-writable)
        $tempStorageBase = '/tmp/laravel_storage_' . md5(base_path());
        
        $tempDirs = [
            $tempStorageBase,
            $tempStorageBase . '/framework',
            $tempStorageBase . '/framework/views',
            $tempStorageBase . '/framework/cache',
            $tempStorageBase . '/framework/sessions',
            $tempStorageBase . '/logs'
        ];

        foreach ($tempDirs as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
                chmod($dir, 0777);
                $solutions[] = "Created temp directory: " . basename($dir);
            }
        }

        // Create symlinks to temp directories (if possible)
        $symlinks = [
            'storage/framework/views' => $tempStorageBase . '/framework/views',
            'storage/framework/cache/data' => $tempStorageBase . '/framework/cache'
        ];

        foreach ($symlinks as $link => $target) {
            $linkPath = base_path($link);
            
            // Remove existing directory if it exists and is empty
            if (is_dir($linkPath) && count(scandir($linkPath)) <= 2) {
                rmdir($linkPath);
            }
            
            if (!file_exists($linkPath) && symlink($target, $linkPath)) {
                $solutions[] = "Created symlink: {$link} -> {$target}";
            }
        }

        return $solutions;
    }

    private function testPermissions(): int
    {
        $this->info('🧪 TESTING WRITE PERMISSIONS');
        $this->newLine();

        $testDirs = [
            'storage/framework/views',
            'storage/framework/cache',
            'storage/logs',
            'bootstrap/cache'
        ];

        $results = [];
        foreach ($testDirs as $dir) {
            $fullPath = base_path($dir);
            $testFile = $fullPath . '/permission_test_' . time() . '.tmp';
            
            $canWrite = false;
            $error = '';
            
            try {
                if (file_put_contents($testFile, 'test')) {
                    $canWrite = true;
                    unlink($testFile);
                } else {
                    $error = 'file_put_contents failed';
                }
            } catch (\Exception $e) {
                $error = $e->getMessage();
            }
            
            $results[] = [
                $dir,
                $canWrite ? '✅ Yes' : '❌ No',
                $error ? "⚠️ {$error}" : 'OK'
            ];
        }

        $this->table(['Directory', 'Writable', 'Status'], $results);

        return 0;
    }

    private function displayResults(array $solutions): void
    {
        $this->newLine();
        
        if (!empty($solutions)) {
            $this->info('✅ SOLUTIONS APPLIED:');
            foreach ($solutions as $solution) {
                $this->line("   • {$solution}");
            }
        }

        $this->newLine();
        $this->info('🔍 TESTING PERMISSIONS AFTER FIXES...');
        
        // Test if we can create a file now
        $testPath = storage_path('framework/views/permission_test.php');
        try {
            if (file_put_contents($testPath, '<?php // Test file')) {
                $this->info('✅ Success: Can now write to views directory');
                unlink($testPath);
            } else {
                $this->error('❌ Still cannot write to views directory');
            }
        } catch (\Exception $e) {
            $this->error('❌ Permission test failed: ' . $e->getMessage());
        }

        $this->newLine();
        $this->info('💡 IF PROBLEM PERSISTS:');
        $this->line('1. Run this command with sudo:');
        $this->line('   sudo /home/yualbe/Homestead/code/Calcio/fix-permissions.sh');
        $this->line('2. Or manually execute:');
        $this->line('   sudo chown -R www-data:www-data storage/ bootstrap/cache/');
        $this->line('   sudo chmod -R 775 storage/ bootstrap/cache/');
        $this->line('3. Restart Apache:');
        $this->line('   sudo systemctl restart apache2');
        
        $this->newLine();
        $this->info('4. Test permissions: php artisan fix:webserver-permissions --test');
    }
}