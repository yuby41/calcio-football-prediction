<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\FootballMatch;
use App\Services\FootballApiService;

class PopulateActualResults extends Command
{
    protected $signature = 'results:populate 
                            {--matches=10 : Number of matches to populate}
                            {--days=7 : Days back to look for finished matches}';
    
    protected $description = 'Populate actual match results from API-Sports for performance analysis';

    private FootballApiService $apiService;

    public function __construct(FootballApiService $apiService)
    {
        parent::__construct();
        $this->apiService = $apiService;
    }

    public function handle(): int
    {
        $this->info('⚽ POPULATING ACTUAL MATCH RESULTS');
        $this->info('Fetching finished match results for performance analysis...');
        $this->newLine();

        $limit = (int) $this->option('matches');
        $daysBack = (int) $this->option('days');

        // Get matches that might be finished but don't have actual results yet
        $matches = FootballMatch::whereNull('actual_home_goals')
            ->whereNull('actual_away_goals')
            ->where('match_date', '>=', now()->subDays($daysBack))
            ->where('match_date', '<=', now()->subHours(2)) // Matches that should be finished
            ->whereHas('homeTeam', function($query) {
                $query->where('external_id', 'regexp', '^[0-9]+$'); // Only real team IDs
            })
            ->whereHas('awayTeam', function($query) {
                $query->where('external_id', 'regexp', '^[0-9]+$');
            })
            ->with(['homeTeam', 'awayTeam'])
            ->limit($limit)
            ->get();

        if ($matches->isEmpty()) {
            $this->warn('No matches found that need result updates');
            return 0;
        }

        $this->info("Found {$matches->count()} matches to update with actual results");

        $progressBar = $this->output->createProgressBar($matches->count());
        $progressBar->start();

        $updated = 0;
        $failed = 0;

        foreach ($matches as $match) {
            try {
                // Fetch match results from API-Sports
                $fixtureId = $match->external_id;
                if (!$fixtureId) {
                    $failed++;
                    continue;
                }

                $fixtureData = $this->apiService->fetchFixture($fixtureId);
                
                if ($fixtureData && isset($fixtureData['fixture']['status']['short'])) {
                    $status = $fixtureData['fixture']['status']['short'];
                    
                    if ($status === 'FT' || $status === 'AET' || $status === 'PEN') {
                        // Match is finished, get the results
                        $homeGoals = $fixtureData['goals']['home'] ?? null;
                        $awayGoals = $fixtureData['goals']['away'] ?? null;
                        
                        if ($homeGoals !== null && $awayGoals !== null) {
                            $match->update([
                                'actual_home_goals' => $homeGoals,
                                'actual_away_goals' => $awayGoals,
                                'match_status' => 'finished',
                                'result_updated_at' => now(),
                            ]);
                            
                            $updated++;
                        } else {
                            $failed++;
                        }
                    } else {
                        // Match not finished yet, update status
                        $statusMapping = [
                            'TBD' => 'scheduled',
                            'NS' => 'scheduled',
                            '1H' => 'live',
                            'HT' => 'live',
                            '2H' => 'live',
                            'ET' => 'live',
                            'P' => 'live',
                            'SUSP' => 'live',
                            'INT' => 'live',
                            'PST' => 'postponed',
                            'CANC' => 'cancelled',
                        ];
                        
                        $matchStatus = $statusMapping[$status] ?? 'scheduled';
                        $match->update(['match_status' => $matchStatus]);
                        // Don't count as failed, just not finished yet
                    }
                } else {
                    $failed++;
                }
                
            } catch (\Exception $e) {
                $failed++;
                \Log::error("Failed to fetch results for match {$match->id}: " . $e->getMessage());
            }

            $progressBar->advance();
            usleep(100000); // Small delay to respect API limits
        }

        $progressBar->finish();
        $this->newLine(2);

        // Results summary
        $this->table(
            ['Metric', 'Count', 'Percentage'],
            [
                ['Matches Processed', $matches->count(), '100%'],
                ['Results Updated', $updated, $updated > 0 ? round(($updated / $matches->count()) * 100, 1) . '%' : '0%'],
                ['Failed/Pending', $failed, $failed > 0 ? round(($failed / $matches->count()) * 100, 1) . '%' : '0%'],
            ]
        );

        if ($updated > 0) {
            $this->info("✅ Successfully updated {$updated} matches with actual results!");
            
            // Show examples
            $this->newLine();
            $this->info('📊 UPDATED EXAMPLES:');
            $updatedMatches = FootballMatch::whereNotNull('actual_home_goals')
                ->whereNotNull('actual_away_goals')
                ->where('result_updated_at', '>=', now()->subMinute())
                ->with(['homeTeam', 'awayTeam'])
                ->limit(3)
                ->get();
            
            foreach ($updatedMatches as $updatedMatch) {
                $this->line("⚽ {$updatedMatch->homeTeam->name} {$updatedMatch->actual_home_goals} - {$updatedMatch->actual_away_goals} {$updatedMatch->awayTeam->name}");
            }
        }

        if ($failed > 0) {
            $this->warn("⚠️ {$failed} matches failed or are not finished yet");
        }

        $this->newLine();
        $this->info('💡 NEXT STEPS:');
        $this->info('1. Run performance analysis: php artisan performance:analyze');
        $this->info('2. Continue populating: php artisan results:populate --matches=20');
        $this->info('3. Set up automated result fetching in scheduler');

        return 0;
    }
}