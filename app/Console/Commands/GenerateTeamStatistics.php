<?php

namespace App\Console\Commands;

use App\Models\FootballMatch;
use App\Models\Team;
use App\Models\TeamStatistic;
use Illuminate\Console\Command;

class GenerateTeamStatistics extends Command
{
    protected $signature = 'football:stats {--season=}';
    
    protected $description = 'Generate team statistics from match data';
    
    public function handle(): int
    {
        $season = $this->option('season') ?? date('Y');
        
        $this->info("Generating team statistics for season {$season}...");
        
        $teams = Team::all();
        $progressBar = $this->output->createProgressBar($teams->count());
        
        foreach ($teams as $team) {
            $this->generateTeamStats($team->id, $season);
            $progressBar->advance();
        }
        
        $progressBar->finish();
        $this->newLine();
        $this->info('Team statistics generated successfully!');
        
        return Command::SUCCESS;
    }
    
    private function generateTeamStats(int $teamId, string $season): void
    {
        $homeMatches = FootballMatch::where('home_team_id', $teamId)
            ->where('season', $season)
            ->where('status', 'finished')
            ->whereNotNull('home_goals')
            ->whereNotNull('away_goals')
            ->get();
            
        $awayMatches = FootballMatch::where('away_team_id', $teamId)
            ->where('season', $season)
            ->where('status', 'finished')
            ->whereNotNull('home_goals')
            ->whereNotNull('away_goals')
            ->get();
        
        $stats = [
            'team_id' => $teamId,
            'season' => $season,
            'matches_played' => 0,
            'wins' => 0,
            'draws' => 0,
            'losses' => 0,
            'goals_for' => 0,
            'goals_against' => 0,
            'points' => 0,
            'form' => [],
            'home_stats' => ['played' => 0, 'wins' => 0, 'draws' => 0, 'losses' => 0],
            'away_stats' => ['played' => 0, 'wins' => 0, 'draws' => 0, 'losses' => 0],
        ];
        
        // Process home matches
        foreach ($homeMatches as $match) {
            $stats['matches_played']++;
            $stats['home_stats']['played']++;
            $stats['goals_for'] += $match->home_goals;
            $stats['goals_against'] += $match->away_goals;
            
            if ($match->home_goals > $match->away_goals) {
                $stats['wins']++;
                $stats['home_stats']['wins']++;
                $stats['points'] += 3;
                array_unshift($stats['form'], 'win');
            } elseif ($match->home_goals < $match->away_goals) {
                $stats['losses']++;
                $stats['home_stats']['losses']++;
                array_unshift($stats['form'], 'loss');
            } else {
                $stats['draws']++;
                $stats['home_stats']['draws']++;
                $stats['points'] += 1;
                array_unshift($stats['form'], 'draw');
            }
        }
        
        // Process away matches
        foreach ($awayMatches as $match) {
            $stats['matches_played']++;
            $stats['away_stats']['played']++;
            $stats['goals_for'] += $match->away_goals;
            $stats['goals_against'] += $match->home_goals;
            
            if ($match->away_goals > $match->home_goals) {
                $stats['wins']++;
                $stats['away_stats']['wins']++;
                $stats['points'] += 3;
                array_unshift($stats['form'], 'win');
            } elseif ($match->away_goals < $match->home_goals) {
                $stats['losses']++;
                $stats['away_stats']['losses']++;
                array_unshift($stats['form'], 'loss');
            } else {
                $stats['draws']++;
                $stats['away_stats']['draws']++;
                $stats['points'] += 1;
                array_unshift($stats['form'], 'draw');
            }
        }
        
        // Calculate averages
        $stats['goals_difference'] = $stats['goals_for'] - $stats['goals_against'];
        $stats['avg_goals_for'] = $stats['matches_played'] > 0 ? 
            round($stats['goals_for'] / $stats['matches_played'], 2) : 0;
        $stats['avg_goals_against'] = $stats['matches_played'] > 0 ? 
            round($stats['goals_against'] / $stats['matches_played'], 2) : 0;
        $stats['form'] = array_slice($stats['form'], 0, 5);
        
        // Save statistics
        TeamStatistic::updateOrCreate(
            ['team_id' => $teamId, 'season' => $season],
            $stats
        );
    }
}