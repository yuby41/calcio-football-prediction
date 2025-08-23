<?php

namespace App\Console\Commands;

use App\Models\Team;
use App\Models\FootballMatch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupDuplicateTeams extends Command
{
    protected $signature = 'teams:cleanup-duplicates
                           {--dry-run : Show what would be cleaned without making changes}
                           {--merge : Merge matches from duplicate to original team}';
    
    protected $description = 'Clean up duplicate team entries by keeping the original and removing duplicates';

    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $merge = $this->option('merge');
        
        $this->info('🧹 LIMPIANDO EQUIPOS DUPLICADOS');
        $this->info('==============================');
        
        // Find duplicate team names
        $duplicateNames = Team::select('name')
            ->groupBy('name')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('name');
            
        if ($duplicateNames->isEmpty()) {
            $this->info('✅ No se encontraron equipos duplicados');
            return 0;
        }
        
        $totalCleaned = 0;
        $totalMerged = 0;
        
        foreach ($duplicateNames as $name) {
            $teams = Team::where('name', $name)
                ->orderBy('created_at', 'asc') // Keep the oldest
                ->get();
                
            if ($teams->count() < 2) continue;
            
            $keepTeam = $teams->first(); // Keep the oldest team
            $duplicateTeams = $teams->slice(1); // Remove the rest
            
            $this->line("\n📊 Procesando: {$name}");
            $this->line("  Equipo original: ID {$keepTeam->id} (creado {$keepTeam->created_at->format('d/m/Y')})");
            
            foreach ($duplicateTeams as $duplicate) {
                $matchCount = FootballMatch::where('home_team_id', $duplicate->id)
                    ->orWhere('away_team_id', $duplicate->id)
                    ->count();
                    
                $this->line("  Duplicado: ID {$duplicate->id} - {$matchCount} partidos (creado {$duplicate->created_at->format('d/m/Y')})");
                
                if ($matchCount > 0 && $merge) {
                    // Merge matches to the original team
                    if (!$dryRun) {
                        DB::transaction(function() use ($duplicate, $keepTeam) {
                            // Update home matches
                            FootballMatch::where('home_team_id', $duplicate->id)
                                ->update(['home_team_id' => $keepTeam->id]);
                                
                            // Update away matches
                            FootballMatch::where('away_team_id', $duplicate->id)
                                ->update(['away_team_id' => $keepTeam->id]);
                        });
                        
                        $this->info("    ✅ {$matchCount} partidos transferidos al equipo original");
                        $totalMerged += $matchCount;
                    } else {
                        $this->warn("    🔍 DRY RUN: {$matchCount} partidos se transferirían");
                    }
                } elseif ($matchCount > 0) {
                    $this->warn("    ⚠️  {$matchCount} partidos se perderían (use --merge para transferir)");
                    continue; // Skip deletion if there are matches and no merge flag
                }
                
                // Delete the duplicate team and related data
                if (!$dryRun) {
                    // Delete related team statistics first
                    DB::table('team_statistics')->where('team_id', $duplicate->id)->delete();
                    
                    $duplicate->delete();
                    $this->info("    ✅ Equipo duplicado eliminado (incluyendo estadísticas)");
                    $totalCleaned++;
                } else {
                    $this->warn("    🔍 DRY RUN: Equipo se eliminaría (incluyendo estadísticas)");
                }
            }
        }
        
        if ($dryRun) {
            $this->warn("\n🔍 DRY RUN completado");
            $this->info("Se encontraron {$duplicateNames->count()} grupos de equipos duplicados");
            $this->info("Use --merge para transferir partidos antes de eliminar duplicados");
        } else {
            $this->info("\n✅ Limpieza completada");
            $this->info("Equipos duplicados eliminados: {$totalCleaned}");
            if ($merge) {
                $this->info("Partidos transferidos: {$totalMerged}");
            }
        }
        
        return 0;
    }
}