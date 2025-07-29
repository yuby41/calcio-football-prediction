<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Team;
use App\Models\FootballMatch;
use App\Models\TeamStatistic;
use Carbon\Carbon;

class CalculateTeamStatistics extends Command
{
    protected $signature = 'teams:calculate-statistics 
                           {--season= : Specific season (default: current year)}
                           {--force : Recalculate even if statistics exist}
                           {--min-matches=5 : Minimum matches played to calculate stats}';

    protected $description = 'Calculate team statistics based on historical match data';

    public function handle()
    {
        $season = $this->option('season') ?: date('Y');
        $force = $this->option('force');
        $minMatches = (int) $this->option('min-matches');

        $this->info("Calculating team statistics for season {$season}...");
        $this->info("Minimum matches required: {$minMatches}");

        if ($force) {
            $this->warn('Force mode: Will recalculate existing statistics');
        }

        // Get teams that have played matches
        $teamsQuery = Team::whereHas('homeMatches', function($query) use ($season) {
            $query->where('status', 'finished')
                  ->whereYear('match_date', $season);
        })->orWhereHas('awayMatches', function($query) use ($season) {
            $query->where('status', 'finished')
                  ->whereYear('match_date', $season);
        });

        if (!$force) {
            $teamsQuery->whereDoesntHave('statistics', function($query) use ($season) {
                $query->where('season', $season);
            });
        }

        $teams = $teamsQuery->get();

        $this->info("Found {$teams->count()} teams to process");

        if ($teams->isEmpty()) {
            $this->info('No teams to process. Use --force to recalculate existing statistics.');
            return Command::SUCCESS;
        }

        $progressBar = $this->output->createProgressBar($teams->count());
        $processed = 0;
        $created = 0;
        $skipped = 0;

        foreach ($teams as $team) {
            $stats = $this->calculateTeamStatistics($team, $season);
            
            if ($stats && $stats['matches_played'] >= $minMatches) {
                TeamStatistic::updateOrCreate(
                    [
                        'team_id' => $team->id,
                        'season' => $season
                    ],
                    $stats
                );
                $created++;
            } else {
                $skipped++;
                $this->line("Skipped {$team->name}: Only " . ($stats['matches_played'] ?? 0) . " matches played");
            }
            
            $processed++;
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();
        
        $this->info("✅ Statistics calculation completed!");
        $this->info("Processed: {$processed} teams");
        $this->info("Created/Updated: {$created} statistics");
        $this->info("Skipped (insufficient matches): {$skipped} teams");

        // Show sample of created statistics
        $this->showSampleStatistics($season);

        return Command::SUCCESS;
    }

    private function calculateTeamStatistics(Team $team, string $season): ?array
    {
        // Get all finished matches for this team in the season
        $matches = FootballMatch::where('status', 'finished')
            ->whereYear('match_date', $season)
            ->where(function($query) use ($team) {
                $query->where('home_team_id', $team->id)
                      ->orWhere('away_team_id', $team->id);
            })
            ->whereNotNull('home_goals')
            ->whereNotNull('away_goals')
            ->orderBy('match_date', 'asc')
            ->get();

        if ($matches->isEmpty()) {
            return null;
        }

        $stats = [
            'team_id' => $team->id,
            'season' => $season,
            'matches_played' => $matches->count(),
            'wins' => 0,
            'draws' => 0,
            'losses' => 0,
            'goals_for' => 0,
            'goals_against' => 0,
            'home_wins' => 0,
            'home_draws' => 0,
            'home_losses' => 0,
            'home_goals_for' => 0,
            'home_goals_against' => 0,
            'away_wins' => 0,
            'away_draws' => 0,
            'away_losses' => 0,
            'away_goals_for' => 0,
            'away_goals_against' => 0,
            'clean_sheets' => 0,
            'goals_difference' => 0,
            'points' => 0,
            'avg_goals_for' => 0,
            'avg_goals_against' => 0,
            'form' => '',
            'league' => null,
            'position' => null,
        ];

        $recentForm = []; // For last 5 matches

        foreach ($matches as $match) {
            $isHome = $match->home_team_id == $team->id;
            $teamGoals = $isHome ? $match->home_goals : $match->away_goals;
            $opponentGoals = $isHome ? $match->away_goals : $match->home_goals;

            // Overall stats
            $stats['goals_for'] += $teamGoals;
            $stats['goals_against'] += $opponentGoals;

            if ($opponentGoals == 0) {
                $stats['clean_sheets']++;
            }

            // Determine result
            if ($teamGoals > $opponentGoals) {
                $stats['wins']++;
                $stats['points'] += 3;
                $recentForm[] = 'W';
                
                if ($isHome) {
                    $stats['home_wins']++;
                } else {
                    $stats['away_wins']++;
                }
            } elseif ($teamGoals == $opponentGoals) {
                $stats['draws']++;
                $stats['points'] += 1;
                $recentForm[] = 'D';
                
                if ($isHome) {
                    $stats['home_draws']++;
                } else {
                    $stats['away_draws']++;
                }
            } else {
                $stats['losses']++;
                $recentForm[] = 'L';
                
                if ($isHome) {
                    $stats['home_losses']++;
                } else {
                    $stats['away_losses']++;
                }
            }

            // Home/Away specific stats
            if ($isHome) {
                $stats['home_goals_for'] += $teamGoals;
                $stats['home_goals_against'] += $opponentGoals;
            } else {
                $stats['away_goals_for'] += $teamGoals;
                $stats['away_goals_against'] += $opponentGoals;
            }

            // Store league info from most recent match
            if ($match->league) {
                $stats['league'] = $match->league;
            }
        }

        // Calculate derived stats
        $stats['goals_difference'] = $stats['goals_for'] - $stats['goals_against'];
        $stats['avg_goals_for'] = round($stats['goals_for'] / $stats['matches_played'], 2);
        $stats['avg_goals_against'] = round($stats['goals_against'] / $stats['matches_played'], 2);
        
        // Recent form (last 5 matches)
        $stats['form'] = implode('', array_slice($recentForm, -5));

        return $stats;
    }

    private function showSampleStatistics(string $season): void
    {
        $this->newLine();
        $this->info('Sample of calculated statistics:');
        
        $sampleStats = TeamStatistic::with('team')
            ->where('season', $season)
            ->orderBy('points', 'desc')
            ->limit(10)
            ->get();

        if ($sampleStats->isEmpty()) {
            $this->info('No statistics found.');
            return;
        }

        $this->table(
            ['Team', 'Matches', 'W-D-L', 'GF-GA', 'GD', 'Pts', 'Avg GF', 'Avg GA', 'Form'],
            $sampleStats->map(function($stat) {
                return [
                    $stat->team->name,
                    $stat->matches_played,
                    "{$stat->wins}-{$stat->draws}-{$stat->losses}",
                    "{$stat->goals_for}-{$stat->goals_against}",
                    sprintf('%+d', $stat->goals_difference),
                    $stat->points,
                    $stat->avg_goals_for,
                    $stat->avg_goals_against,
                    $stat->form ?: 'N/A'
                ];
            })->toArray()
        );
    }
}