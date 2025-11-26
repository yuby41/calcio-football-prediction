<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Team;
use App\Models\FootballMatch;
use Illuminate\Support\Facades\DB;

class MergeDuplicateTeams extends Command
{
    protected $signature = 'data:merge-duplicate-teams';
    protected $description = 'Merge duplicate teams and fix match references';

    public function handle(): int
    {
        $this->info('🔄 Merging duplicate teams with synthetic IDs...');

        // Define the duplicates to merge
        $duplicates = [
            'Manchester City' => [
                'synthetic_id' => 2842, // pred_mancity
                'real_id' => 7795,      // Real Manchester City with external_id 50
            ],
            'Tottenham' => [
                'synthetic_id' => 2843, // pred_tottenham  
                'real_id' => 7796,      // Real Tottenham with external_id 47
            ]
        ];

        foreach ($duplicates as $teamName => $ids) {
            $this->info("🔄 Processing: {$teamName}");
            
            // Get the teams
            $syntheticTeam = Team::find($ids['synthetic_id']);
            $realTeam = Team::find($ids['real_id']);
            
            if (!$syntheticTeam || !$realTeam) {
                $this->error("❌ Could not find teams for {$teamName}");
                continue;
            }

            $this->line("  Synthetic: {$syntheticTeam->name} (ID: {$syntheticTeam->id}, external_id: {$syntheticTeam->external_id})");
            $this->line("  Real: {$realTeam->name} (ID: {$realTeam->id}, external_id: {$realTeam->external_id})");

            // Update all matches that reference the synthetic team
            $homeMatches = FootballMatch::where('home_team_id', $syntheticTeam->id)->count();
            $awayMatches = FootballMatch::where('away_team_id', $syntheticTeam->id)->count();
            
            $this->line("  Found {$homeMatches} home matches and {$awayMatches} away matches to update");

            // Update all foreign key references
            DB::transaction(function () use ($syntheticTeam, $realTeam) {
                // Update home matches
                FootballMatch::where('home_team_id', $syntheticTeam->id)
                    ->update(['home_team_id' => $realTeam->id]);
                
                // Update away matches  
                FootballMatch::where('away_team_id', $syntheticTeam->id)
                    ->update(['away_team_id' => $realTeam->id]);
                    
                // Update team statistics
                DB::table('team_statistics')
                    ->where('team_id', $syntheticTeam->id)
                    ->update(['team_id' => $realTeam->id]);
                    
                // Update any other foreign key references
                DB::table('match_predictions')
                    ->whereIn('match_id', function ($query) use ($syntheticTeam) {
                        $query->select('id')
                              ->from('matches')
                              ->where('home_team_id', $syntheticTeam->id)
                              ->orWhere('away_team_id', $syntheticTeam->id);
                    }); // Just check, don't update predictions
            });

            $this->info("  ✅ Updated all references");

            // Delete the synthetic team
            $syntheticTeam->delete();
            $this->info("  ✅ Deleted synthetic team: {$syntheticTeam->name}");
        }

        $this->info("\n🎉 Duplicate team merge completed!");
        $this->info("💡 Now you can run migration with correct team IDs:");
        $this->info("   php artisan data:migrate-quota --limit=10 --max-requests=80");

        return 0;
    }
}