<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class OptimizeDatabase extends Command
{
    protected $signature = 'db:optimize {--analyze : Run ANALYZE TABLE} {--repair : Run REPAIR TABLE}';
    protected $description = 'Optimize database tables for better performance';

    public function handle()
    {
        $analyze = $this->option('analyze');
        $repair = $this->option('repair');
        
        $this->info('🔧 Optimizing database tables...');

        $tables = [
            'matches',
            'match_predictions', 
            'bets',
            'teams',
            'budget_history'
        ];

        foreach ($tables as $table) {
            $this->line("Optimizing table: {$table}");
            
            try {
                // Optimize table
                DB::statement("OPTIMIZE TABLE {$table}");
                
                if ($analyze) {
                    DB::statement("ANALYZE TABLE {$table}");
                    $this->line("  ✅ Analyzed {$table}");
                }
                
                if ($repair) {
                    DB::statement("REPAIR TABLE {$table}");
                    $this->line("  ✅ Repaired {$table}");
                }
                
                $this->line("  ✅ Optimized {$table}");
                
            } catch (\Exception $e) {
                $this->warn("  ❌ Failed to optimize {$table}: " . $e->getMessage());
            }
        }

        $this->info('✅ Database optimization completed');
        return 0;
    }
}