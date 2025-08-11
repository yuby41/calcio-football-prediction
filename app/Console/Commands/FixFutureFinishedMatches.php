<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class FixFutureFinishedMatches extends Command
{
    protected $signature = 'matches:fix-future-finished';
    protected $description = 'Fix matches marked as finished but with future dates';

    public function handle()
    {
        $this->info('🔧 Fixing matches marked as finished with future dates...');

        // Find matches marked as finished with future dates
        $futureMatches = DB::table('matches')
            ->where('status', 'finished')
            ->where('match_date', '>', now())
            ->get(['id', 'match_date', 'home_goals', 'away_goals']);

        $this->info("Found {$futureMatches->count()} matches with future dates marked as finished");

        if ($futureMatches->count() > 0) {
            foreach ($futureMatches as $match) {
                $matchDate = Carbon::parse($match->match_date);
                
                $this->line("Match ID {$match->id}: Date {$matchDate->format('Y-m-d H:i')} (Future)");
                
                // If the match has goals, it might be legitimate but with wrong date
                if (!is_null($match->home_goals) && !is_null($match->away_goals)) {
                    // Set the match date to now to indicate it's actually finished
                    DB::table('matches')
                        ->where('id', $match->id)
                        ->update([
                            'match_date' => now(),
                            'updated_at' => now()
                        ]);
                    
                    $this->info("  ✅ Updated match date to now (has results {$match->home_goals}-{$match->away_goals})");
                } else {
                    // No goals, probably should not be marked as finished
                    DB::table('matches')
                        ->where('id', $match->id)
                        ->update([
                            'status' => 'scheduled',
                            'home_goals' => null,
                            'away_goals' => null,
                            'updated_at' => now()
                        ]);
                    
                    $this->info("  ✅ Changed status to scheduled (no results)");
                }
            }
        } else {
            $this->info("✅ No future finished matches found");
        }

        return 0;
    }
}