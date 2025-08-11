<?php

namespace App\Console\Commands;

use App\Models\FootballMatch;
use App\Services\FootballApiOddsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class VerifyOddsAccuracy extends Command
{
    protected $signature = 'odds:verify
                           {--match-id= : Specific match ID to verify}
                           {--limit=10 : Number of matches to check}
                           {--fix : Update stored odds with real API odds}';
    
    protected $description = 'Verify odds accuracy and compare real vs displayed odds';

    public function handle()
    {
        $matchId = $this->option('match-id');
        $limit = $this->option('limit');
        $fix = $this->option('fix');
        
        $this->info('🔍 Verifying odds accuracy...');
        
        if ($matchId) {
            $matches = FootballMatch::where('id', $matchId)->with(['homeTeam', 'awayTeam'])->get();
        } else {
            $matches = FootballMatch::whereIn('status', ['timed', 'live'])
                ->where('match_date', '>=', now())
                ->where('match_date', '<=', now()->addDays(7))
                ->with(['homeTeam', 'awayTeam'])
                ->limit($limit)
                ->get();
        }
        
        if ($matches->isEmpty()) {
            $this->info('No matches found to verify');
            return 0;
        }
        
        $oddsService = new FootballApiOddsService();
        $discrepancies = 0;
        
        $this->info("Checking {$matches->count()} matches...\n");
        
        foreach ($matches as $match) {
            $this->line("🔍 {$match->homeTeam->name} vs {$match->awayTeam->name}");
            $this->line("   Status: {$match->status} | Date: {$match->match_date->format('d/m/Y H:i')}");
            
            try {
                // Get current odds from service
                $currentOdds = $oddsService->getRealOddsForMatch($match);
                
                // Get stored odds from database
                $storedOdds = $match->odds ? json_decode($match->odds, true) : null;
                
                // Compare key odds
                $keysToCheck = ['home_win', 'away_win', 'draw'];
                $hasDiscrepancy = false;
                
                foreach ($keysToCheck as $key) {
                    $currentValue = $currentOdds[$key] ?? null;
                    $storedValue = $storedOdds[$key] ?? null;
                    
                    if ($currentValue && $storedValue) {
                        $diff = abs($currentValue - $storedValue);
                        $percentDiff = ($diff / $storedValue) * 100;
                        
                        if ($percentDiff > 10) { // More than 10% difference
                            $hasDiscrepancy = true;
                            $this->error("   ❌ {$key}: Stored {$storedValue} vs Current {$currentValue} (" . round($percentDiff, 1) . "% diff)");
                        } else {
                            $this->line("   ✅ {$key}: {$currentValue} (stored: {$storedValue})");
                        }
                    } else {
                        $this->line("   📝 {$key}: Current {$currentValue} | Stored: " . ($storedValue ? $storedValue : 'None'));
                        if (!$storedValue && $currentValue) {
                            $hasDiscrepancy = true;
                        }
                    }
                }
                
                // Show source information
                $source = $currentOdds['source'] ?? 'unknown';
                $this->line("   📊 Source: {$source}");
                
                if ($hasDiscrepancy) {
                    $discrepancies++;
                    
                    if ($fix) {
                        // Update stored odds
                        $match->odds = json_encode($currentOdds);
                        $match->save();
                        $this->info("   🔧 Updated stored odds for match {$match->id}");
                        
                        Log::info('Odds updated for match', [
                            'match_id' => $match->id,
                            'match' => "{$match->homeTeam->name} vs {$match->awayTeam->name}",
                            'old_odds' => $storedOdds,
                            'new_odds' => $currentOdds,
                            'source' => $source
                        ]);
                    }
                }
                
            } catch (\Exception $e) {
                $this->error("   ❌ Error checking odds: " . $e->getMessage());
            }
            
            $this->line("");
        }
        
        // Summary
        $this->info("📊 VERIFICATION COMPLETE");
        $this->info("Matches checked: {$matches->count()}");
        $this->info("Discrepancies found: {$discrepancies}");
        
        if ($discrepancies > 0 && !$fix) {
            $this->warn("💡 Run with --fix to update stored odds with current real values");
        }
        
        return 0;
    }
}