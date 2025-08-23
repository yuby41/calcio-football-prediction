<?php

namespace App\Console\Commands;

use App\Models\FootballMatch;
use App\Models\Team;
use App\Models\TeamStatistic;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class GenerateTeamStatistics extends Command
{
    protected $signature = 'teams:generate-statistics 
                            {--season= : Specific season to process (e.g., 2023-24)}
                            {--team= : Specific team ID to process}
                            {--league= : Specific league to process}
                            {--dry-run : Show what would be calculated without storing}
                            {--overwrite : Overwrite existing statistics}
                            {--chunk=100 : Process teams in chunks of N}
                            {--all-seasons : Process all available seasons}';
    
    protected $description = 'Generate comprehensive team statistics from real match results in the database';

    public function handle(): int
    {
        $season = $this->option('season');
        $teamId = $this->option('team');
        $league = $this->option('league');
        $dryRun = $this->option('dry-run');
        $overwrite = $this->option('overwrite');
        $chunkSize = (int) $this->option('chunk');
        $allSeasons = $this->option('all-seasons');

        $this->info('📊 Generando estadísticas de equipos desde datos reales...');
        
        if ($dryRun) {
            $this->warn('🔍 MODO DRY RUN - No se guardarán datos');
        }

        // Get seasons to process
        if ($allSeasons) {
            $seasons = $this->getAvailableSeasons($league);
        } elseif ($season) {
            $seasons = [$season];
        } else {
            $seasons = $this->getRecentSeasons($league, 3); // Last 3 seasons by default
        }
        
        $this->info("Temporadas a procesar: " . implode(', ', $seasons));
        $this->line('');

        $totalProcessed = 0;
        $totalGenerated = 0;

        foreach ($seasons as $currentSeason) {
            $this->info("🏆 Procesando temporada: {$currentSeason}");
            
            $teams = $this->getTeamsForSeason($currentSeason, $teamId, $league);
            
            if ($teams->isEmpty()) {
                $this->warn("No hay equipos para procesar en temporada {$currentSeason}");
                continue;
            }

            $this->info("Equipos encontrados: {$teams->count()}");
            
            $progressBar = $this->output->createProgressBar($teams->count());
            $progressBar->start();

            foreach ($teams->chunk($chunkSize) as $teamChunk) {
                foreach ($teamChunk as $team) {
                    $stats = $this->calculateComprehensiveStats($team->id, $currentSeason, $league);
                    
                    if ($stats && $stats['matches_played'] >= 1) {
                        if ($dryRun) {
                            $this->showDryRunResult($team, $stats, $currentSeason);
                        } else {
                            $this->storeTeamStatistics($team->id, $currentSeason, $stats, $overwrite);
                            $totalGenerated++;
                        }
                    }
                    
                    $totalProcessed++;
                    $progressBar->advance();
                }
            }

            $progressBar->finish();
            $this->line('');
            $this->info("✅ Temporada {$currentSeason} completada");
        }

        $this->line('');
        $this->info("🎉 Proceso completado!");
        $this->info("📈 Equipos procesados: {$totalProcessed}");
        
        if (!$dryRun) {
            $this->info("✅ Estadísticas generadas: {$totalGenerated}");
        }

        return Command::SUCCESS;
    }
    private function getAvailableSeasons($league = null): array
    {
        $query = FootballMatch::where('status', 'finished')
            ->whereNotNull('home_goals')
            ->whereNotNull('away_goals');
            
        if ($league) {
            $query->where('league', $league);
        }

        return $query->distinct()
            ->pluck('season')
            ->filter()
            ->sort()
            ->values()
            ->toArray();
    }

    private function getRecentSeasons($league = null, $count = 3): array
    {
        $seasons = $this->getAvailableSeasons($league);
        return array_slice($seasons, -$count);
    }

    private function getTeamsForSeason($season, $teamId = null, $league = null)
    {
        $query = DB::table('matches as m')
            ->where('m.status', 'finished')
            ->where('m.season', $season)
            ->whereNotNull('m.home_goals')
            ->whereNotNull('m.away_goals');
            
        if ($league) {
            $query->where('m.league', $league);
        }

        // Get all team IDs that played in this season
        $homeTeams = (clone $query)->select('m.home_team_id as team_id');
        $awayTeams = (clone $query)->select('m.away_team_id as team_id');
        
        $teamIds = DB::table(DB::raw("({$homeTeams->toSql()} UNION {$awayTeams->toSql()}) as teams"))
            ->mergeBindings($homeTeams)
            ->mergeBindings($awayTeams)
            ->distinct()
            ->pluck('team_id');

        $teamsQuery = Team::whereIn('id', $teamIds);
        
        if ($teamId) {
            $teamsQuery->where('id', $teamId);
        }

        return $teamsQuery->get();
    }

    private function calculateComprehensiveStats($teamId, $season, $league = null): ?array
    {
        $query = FootballMatch::where('status', 'finished')
            ->where('season', $season)
            ->whereNotNull('home_goals')
            ->whereNotNull('away_goals')
            ->where(function($q) use ($teamId) {
                $q->where('home_team_id', $teamId)
                  ->orWhere('away_team_id', $teamId);
            });
            
        if ($league) {
            $query->where('league', $league);
        }

        $matches = $query->orderBy('match_date')->get();

        if ($matches->isEmpty()) {
            return null;
        }

        $stats = [
            'matches_played' => $matches->count(),
            'wins' => 0,
            'draws' => 0,
            'losses' => 0,
            'goals_for' => 0,
            'goals_against' => 0,
            'points' => 0,
            'home_matches' => 0,
            'away_matches' => 0,
            'home_wins' => 0,
            'away_wins' => 0,
            'home_draws' => 0,
            'away_draws' => 0,
            'home_losses' => 0,
            'away_losses' => 0,
            'home_goals_for' => 0,
            'home_goals_against' => 0,
            'away_goals_for' => 0,
            'away_goals_against' => 0,
        ];

        foreach ($matches as $match) {
            $isHome = $match->home_team_id == $teamId;
            $teamGoals = $isHome ? $match->home_goals : $match->away_goals;
            $opponentGoals = $isHome ? $match->away_goals : $match->home_goals;

            // Update totals
            $stats['goals_for'] += $teamGoals;
            $stats['goals_against'] += $opponentGoals;

            // Determine result
            if ($teamGoals > $opponentGoals) {
                $stats['wins']++;
                $stats['points'] += 3;
                if ($isHome) {
                    $stats['home_wins']++;
                } else {
                    $stats['away_wins']++;
                }
            } elseif ($teamGoals == $opponentGoals) {
                $stats['draws']++;
                $stats['points'] += 1;
                if ($isHome) {
                    $stats['home_draws']++;
                } else {
                    $stats['away_draws']++;
                }
            } else {
                $stats['losses']++;
                if ($isHome) {
                    $stats['home_losses']++;
                } else {
                    $stats['away_losses']++;
                }
            }

            // Home/Away specific stats
            if ($isHome) {
                $stats['home_matches']++;
                $stats['home_goals_for'] += $teamGoals;
                $stats['home_goals_against'] += $opponentGoals;
            } else {
                $stats['away_matches']++;
                $stats['away_goals_for'] += $teamGoals;
                $stats['away_goals_against'] += $opponentGoals;
            }
        }

        // Calculate averages and derived stats
        $stats['avg_goals_for'] = round($stats['goals_for'] / $stats['matches_played'], 2);
        $stats['avg_goals_against'] = round($stats['goals_against'] / $stats['matches_played'], 2);
        $stats['goal_difference'] = $stats['goals_for'] - $stats['goals_against'];
        
        // Win rates
        $stats['win_rate'] = round(($stats['wins'] / $stats['matches_played']) * 100, 1);
        $stats['draw_rate'] = round(($stats['draws'] / $stats['matches_played']) * 100, 1);
        $stats['loss_rate'] = round(($stats['losses'] / $stats['matches_played']) * 100, 1);

        return $stats;
    }

    private function showDryRunResult($team, $stats, $season)
    {
        $this->line('');
        $this->info("🔍 {$team->name} ({$season}):");
        $this->line("  Partidos: {$stats['matches_played']} | V:{$stats['wins']} E:{$stats['draws']} D:{$stats['losses']}");
        $this->line("  Goles: {$stats['goals_for']}-{$stats['goals_against']} | Promedio: {$stats['avg_goals_for']}-{$stats['avg_goals_against']}");
        $this->line("  Puntos: {$stats['points']} | Win Rate: {$stats['win_rate']}%");
        $this->line("  Casa: {$stats['home_matches']} partidos | Fuera: {$stats['away_matches']} partidos");
    }

    private function storeTeamStatistics($teamId, $season, $stats, $overwrite)
    {
        // Check if statistics already exist
        $existing = TeamStatistic::where('team_id', $teamId)
            ->where('season', $season)
            ->first();

        if ($existing && !$overwrite) {
            return; // Skip if exists and not overwriting
        }

        if ($existing && $overwrite) {
            $existing->delete();
        }

        // Create new statistics record
        TeamStatistic::create([
            'team_id' => $teamId,
            'season' => $season,
            'matches_played' => $stats['matches_played'],
            'wins' => $stats['wins'],
            'draws' => $stats['draws'],
            'losses' => $stats['losses'],
            'goals_for' => $stats['goals_for'],
            'goals_against' => $stats['goals_against'],
            'goal_difference' => $stats['goal_difference'],
            'points' => $stats['points'],
            'avg_goals_for' => $stats['avg_goals_for'],
            'avg_goals_against' => $stats['avg_goals_against'],
            'win_rate' => $stats['win_rate'],
            'draw_rate' => $stats['draw_rate'],
            'loss_rate' => $stats['loss_rate'],
            'home_matches' => $stats['home_matches'] ?? 0,
            'away_matches' => $stats['away_matches'] ?? 0,
            'home_wins' => $stats['home_wins'] ?? 0,
            'away_wins' => $stats['away_wins'] ?? 0,
            'home_draws' => $stats['home_draws'] ?? 0,
            'away_draws' => $stats['away_draws'] ?? 0,
            'home_losses' => $stats['home_losses'] ?? 0,
            'away_losses' => $stats['away_losses'] ?? 0,
            'home_goals_for' => $stats['home_goals_for'] ?? 0,
            'home_goals_against' => $stats['home_goals_against'] ?? 0,
            'away_goals_for' => $stats['away_goals_for'] ?? 0,
            'away_goals_against' => $stats['away_goals_against'] ?? 0,
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }
}