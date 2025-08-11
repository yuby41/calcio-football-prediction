<?php

namespace App\Console\Commands;

use App\Models\FootballMatch;
use Illuminate\Console\Command;

class FixInconsistentFirstHalfGoals extends Command
{
    protected $signature = 'matches:fix-first-half
                           {--dry-run : Show what would be fixed without making changes}
                           {--limit=100 : Maximum number of matches to fix}';
    
    protected $description = 'Fix matches where first-half goals exceed total goals (impossible)';

    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $limit = $this->option('limit');
        
        $this->info('🔧 CORRIGIENDO GOLES DE PRIMER TIEMPO INCONSISTENTES');
        $this->info('==================================================');
        
        // Find matches where first-half goals > total goals (impossible)
        $inconsistentMatches = FootballMatch::where('status', 'finished')
            ->whereNotNull('home_goals_first_half')
            ->whereNotNull('away_goals_first_half')
            ->whereNotNull('home_goals')
            ->whereNotNull('away_goals')
            ->where(function($query) {
                $query->whereRaw('home_goals_first_half > home_goals')
                      ->orWhereRaw('away_goals_first_half > away_goals');
            })
            ->limit($limit)
            ->get();
            
        if ($inconsistentMatches->isEmpty()) {
            $this->info('✅ No se encontraron inconsistencias en goles de primer tiempo');
            return 0;
        }
        
        $this->warn("Found {$inconsistentMatches->count()} matches with impossible first-half scores");
        
        $fixed = 0;
        
        foreach ($inconsistentMatches as $match) {
            $this->line("\n📊 {$match->homeTeam->name} vs {$match->awayTeam->name}");
            $this->line("   Resultado: {$match->home_goals}-{$match->away_goals}");
            $this->line("   1T actual: {$match->home_goals_first_half}-{$match->away_goals_first_half}");
            
            // Fix the first-half goals to be realistic
            $correctedHome = min($match->home_goals_first_half, $match->home_goals);
            $correctedAway = min($match->away_goals_first_half, $match->away_goals);
            
            // Apply statistical logic: typically 45% of goals are in first half
            if ($correctedHome == $match->home_goals || $correctedAway == $match->away_goals) {
                // If we had to cap to total goals, use statistical estimation
                $totalGoals = $match->home_goals + $match->away_goals;
                $estimatedFirstHalfTotal = max(0, round($totalGoals * 0.45));
                
                if ($totalGoals > 0) {
                    $homeRatio = $match->home_goals / $totalGoals;
                    $correctedHome = round($estimatedFirstHalfTotal * $homeRatio);
                    $correctedAway = $estimatedFirstHalfTotal - $correctedHome;
                } else {
                    $correctedHome = 0;
                    $correctedAway = 0;
                }
                
                // Ensure we don't exceed total goals
                $correctedHome = min($correctedHome, $match->home_goals);
                $correctedAway = min($correctedAway, $match->away_goals);
            }
            
            $this->info("   1T corregido: {$correctedHome}-{$correctedAway}");
            
            if (!$dryRun) {
                $match->home_goals_first_half = $correctedHome;
                $match->away_goals_first_half = $correctedAway;
                $match->save();
                
                $this->info("   ✅ Partido corregido");
                $fixed++;
            } else {
                $this->warn("   🔍 DRY RUN: Se corregiría");
            }
        }
        
        if ($dryRun) {
            $this->warn("\n🔍 DRY RUN: Se corregirían {$inconsistentMatches->count()} partidos");
            $this->info("Ejecuta sin --dry-run para aplicar cambios");
        } else {
            $this->info("\n✅ {$fixed} partidos corregidos");
        }
        
        return 0;
    }
}