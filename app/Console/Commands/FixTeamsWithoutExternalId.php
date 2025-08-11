<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixTeamsWithoutExternalId extends Command
{
    protected $signature = 'teams:fix-external-ids';
    protected $description = 'Fix teams without external_id by generating placeholder IDs';

    public function handle()
    {
        $this->info('🔧 Fixing teams without external_id...');

        // Find teams without external_id
        $teamsWithoutId = DB::table('teams')
            ->whereNull('external_id')
            ->orWhere('external_id', '')
            ->get(['id', 'name']);

        $this->info("Found {$teamsWithoutId->count()} teams without external_id");

        if ($teamsWithoutId->count() > 0) {
            foreach ($teamsWithoutId as $team) {
                // Generate a placeholder external_id based on team ID
                $externalId = 'placeholder_' . $team->id;
                
                DB::table('teams')
                    ->where('id', $team->id)
                    ->update([
                        'external_id' => $externalId,
                        'updated_at' => now()
                    ]);

                $this->line("Team: {$team->name} → external_id: {$externalId}");
            }

            $this->info("✅ Updated {$teamsWithoutId->count()} teams with placeholder external_ids");
        } else {
            $this->info("✅ All teams have external_id");
        }

        return 0;
    }
}