<?php

namespace App\Console\Commands;

use App\Models\FootballMatch;
use Carbon\Carbon;
use Illuminate\Console\Command;

class FixMatchTimezones extends Command
{
    protected $signature = 'matches:fix-timezones 
                           {--dry-run : Show what would be changed without making changes}';

    protected $description = 'Fix timezone issues in existing matches';

    public function handle()
    {
        $this->info('Checking match timezones...');
        
        $timezone = config('services.football_api.timezone', 'Europe/Madrid');
        $isDryRun = $this->option('dry-run');
        
        // Obtener partidos que podrían tener problemas de zona horaria
        $matches = FootballMatch::whereNotNull('match_date')
            ->where('created_at', '>', now()->subDays(7)) // Solo últimos 7 días
            ->orderBy('match_date')
            ->get();
        
        $this->info("Revisando {$matches->count()} partidos...");
        
        $updated = 0;
        $bar = $this->output->createProgressBar($matches->count());
        
        foreach ($matches as $match) {
            $originalDate = $match->match_date;
            
            // Si el partido parece estar en UTC (terminación +00:00 o Z)
            if ($originalDate->timezone->getName() === 'UTC') {
                $newDate = $originalDate->setTimezone($timezone);
                
                if ($isDryRun) {
                    $this->line("Match {$match->id}: {$originalDate->format('Y-m-d H:i T')} -> {$newDate->format('Y-m-d H:i T')}");
                } else {
                    $match->update(['match_date' => $newDate]);
                    $updated++;
                }
            }
            
            $bar->advance();
        }
        
        $bar->finish();
        $this->newLine();
        
        if ($isDryRun) {
            $this->info('Dry run completed. Use --dry-run=false to apply changes.');
        } else {
            $this->info("Updated {$updated} matches with correct timezone.");
        }
        
        // Mostrar algunos ejemplos de horarios actuales
        $this->newLine();
        $this->info('Sample of current match times:');
        
        $samples = FootballMatch::whereDate('match_date', '>=', today())
            ->with(['homeTeam', 'awayTeam'])
            ->limit(5)
            ->get();
            
        foreach ($samples as $sample) {
            $this->line(
                $sample->match_date->format('Y-m-d H:i T') . ' - ' . 
                $sample->homeTeam->name . ' vs ' . $sample->awayTeam->name
            );
        }
    }
}