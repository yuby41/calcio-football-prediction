<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixMatchesWithoutGoals extends Command
{
    protected $signature = 'matches:fix-without-goals';
    protected $description = 'Fix finished matches without goals registered';

    public function handle()
    {
        $this->info('🔧 Fixing finished matches without goals...');

        // Count matches without goals
        $count = DB::table('matches')
            ->where('status', 'finished')
            ->where(function($query) {
                $query->whereNull('home_goals')
                      ->orWhereNull('away_goals');
            })
            ->count();

        $this->info("Found {$count} matches without goals");

        if ($count > 0) {
            // Update matches without goals to have 0-0 scores
            $updated = DB::table('matches')
                ->where('status', 'finished')
                ->where(function($query) {
                    $query->whereNull('home_goals')
                          ->orWhereNull('away_goals');
                })
                ->update([
                    'home_goals' => 0,
                    'away_goals' => 0,
                    'updated_at' => now()
                ]);

            $this->info("✅ Updated {$updated} matches to 0-0 scores");
        } else {
            $this->info("✅ No matches found without goals");
        }

        return 0;
    }
}