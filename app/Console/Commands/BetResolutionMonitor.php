<?php

namespace App\Console\Commands;

use App\Models\Bet;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class BetResolutionMonitor extends Command
{
    protected $signature = 'bets:monitor
                           {--alert-threshold=10 : Alert when pending bets exceed this number}
                           {--max-age-hours=6 : Alert when bets are pending for more than this many hours}';
    
    protected $description = 'Monitor bet resolution system and alert on issues';

    public function handle()
    {
        $alertThreshold = $this->option('alert-threshold');
        $maxAgeHours = $this->option('max-age-hours');
        
        $this->info('🔍 Monitoring bet resolution system...');
        
        // Check for stuck pending bets
        $stuckBets = Bet::whereHas('match', function($query) {
            $query->where('status', 'finished');
        })
        ->where('status', 'pending')
        ->where('created_at', '<', now()->subHours($maxAgeHours))
        ->with(['match.homeTeam', 'match.awayTeam'])
        ->get();
        
        if ($stuckBets->count() > 0) {
            $this->error("🚨 Found {$stuckBets->count()} bets stuck for >{$maxAgeHours}h:");
            foreach ($stuckBets as $bet) {
                $this->line(sprintf(
                    "  Bet #%d: %s vs %s | %.1fh old | Missing: %s",
                    $bet->id,
                    $bet->match->homeTeam->name ?? 'Home',
                    $bet->match->awayTeam->name ?? 'Away',
                    now()->diffInHours($bet->created_at),
                    $this->getMissingData($bet->match)
                ));
            }
            
            Log::warning('Stuck pending bets detected', [
                'count' => $stuckBets->count(),
                'bet_ids' => $stuckBets->pluck('id')->toArray(),
            ]);
        }
        
        // Check total pending bets
        $totalPending = Bet::where('status', 'pending')->count();
        
        if ($totalPending > $alertThreshold) {
            $this->error("🚨 High pending bet count: {$totalPending} (threshold: {$alertThreshold})");
            Log::warning('High pending bet count', [
                'count' => $totalPending,
                'threshold' => $alertThreshold,
            ]);
        } else {
            $this->info("✅ Pending bet count normal: {$totalPending}");
        }
        
        // Check for matches without first-half data
        $missingDataCount = Bet::whereHas('match', function($query) {
            $query->where('status', 'finished')
                  ->where(function($q) {
                      $q->whereNull('home_goals_first_half')
                        ->orWhereNull('away_goals_first_half');
                  });
        })
        ->where('status', 'pending')
        ->count();
        
        if ($missingDataCount > 0) {
            $this->warn("⚠️  {$missingDataCount} finished matches missing first-half data");
            $this->line("   Consider running: php artisan bets:auto-resolve --estimate-missing");
        }
        
        $this->info('✅ Bet resolution monitoring complete');
        return 0;
    }
    
    private function getMissingData($match)
    {
        $missing = [];
        
        if (is_null($match->home_goals_first_half)) {
            $missing[] = 'home_1H';
        }
        
        if (is_null($match->away_goals_first_half)) {
            $missing[] = 'away_1H';
        }
        
        return empty($missing) ? 'none' : implode(', ', $missing);
    }
}