<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\IntelligentPredictionEngine;
use App\Models\FootballMatch;
use App\Models\MatchPrediction;

class MigrateToIntelligentEngine extends Command
{
    protected $signature = 'prediction:upgrade-to-intelligent 
                            {--matches=25 : Number of matches to upgrade}
                            {--force : Force upgrade without confirmation}';
    
    protected $description = 'Upgrade existing predictions to use Intelligent Prediction Engine';

    public function handle(): int
    {
        $this->info('🚀 UPGRADING TO INTELLIGENT PREDICTION ENGINE');
        $this->info('Advanced algorithms with multi-source data');
        $this->newLine();

        if (!$this->option('force') && !$this->confirm('Upgrade predictions to intelligent engine?')) {
            $this->info('Upgrade cancelled.');
            return 0;
        }

        $limit = (int) $this->option('matches');
        $engine = app(IntelligentPredictionEngine::class);

        // Get matches with old predictions to upgrade
        $matches = FootballMatch::whereHas('prediction', function($query) {
            $query->where('model_version', 'not like', '%intelligent%')
                  ->where('model_version', 'not like', '%real_data_api_sports%'); // Keep our real data ones
        })
        ->whereHas('homeTeam', function($query) {
            $query->where('external_id', 'regexp', '^[0-9]+$'); // Only real IDs
        })
        ->whereHas('awayTeam', function($query) {
            $query->where('external_id', 'regexp', '^[0-9]+$');
        })
        ->with(['homeTeam', 'awayTeam', 'prediction'])
        ->where('match_date', '>=', now()->subDays(14))
        ->limit($limit)
        ->get();

        $this->info("Found {$matches->count()} matches to upgrade");

        if ($matches->count() === 0) {
            $this->warn('No matches found for upgrade');
            return 0;
        }

        $progressBar = $this->output->createProgressBar($matches->count());
        $progressBar->start();

        $upgraded = 0;
        $failed = 0;

        foreach ($matches as $match) {
            try {
                // Generate intelligent prediction
                $intelligentPrediction = $engine->generateIntelligentPrediction($match);
                
                if ($intelligentPrediction) {
                    // Update existing prediction with intelligent version
                    $match->prediction->update($intelligentPrediction);
                    $upgraded++;
                } else {
                    $failed++;
                }
            } catch (\Exception $e) {
                $failed++;
                \Log::error("Intelligent upgrade failed for match {$match->id}: " . $e->getMessage());
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        // Results
        $this->table(
            ['Metric', 'Count'],
            [
                ['Matches Processed', $matches->count()],
                ['Successfully Upgraded', $upgraded],
                ['Failed Upgrades', $failed],
                ['Success Rate', $upgraded > 0 ? round(($upgraded / $matches->count()) * 100, 1) . '%' : '0%'],
            ]
        );

        if ($upgraded > 0) {
            $this->info("🎉 Successfully upgraded {$upgraded} predictions to Intelligent Engine!");
            
            // Show comparison example
            $this->newLine();
            $this->info('🔍 UPGRADE EXAMPLE:');
            $exampleMatch = $matches->first();
            if ($exampleMatch && $exampleMatch->prediction) {
                $this->line("Match: {$exampleMatch->homeTeam->name} vs {$exampleMatch->awayTeam->name}");
                $this->line("Goals: {$exampleMatch->prediction->home_goals_prediction} - {$exampleMatch->prediction->away_goals_prediction}");
                $this->line("Model: {$exampleMatch->prediction->model_version}");
                $confidence = round($exampleMatch->prediction->confidence_score * 100, 1);
                $this->line("Confidence: {$confidence}%");
                
                $features = json_decode($exampleMatch->prediction->features_used, true);
                if (isset($features['data_sources'])) {
                    $sources = implode(', ', $features['data_sources']);
                    $this->line("Sources: {$sources}");
                }
            }
        }

        if ($failed > 0) {
            $this->warn("⚠️ {$failed} upgrades failed - check logs for details");
        }

        // Next steps
        $this->newLine();
        $this->info('💡 NEXT STEPS:');
        $this->info('1. All new predictions will automatically use Intelligent Engine');
        $this->info('2. Run migration: php artisan data:migrate-quota --limit=50');
        $this->info('3. Monitor performance vs old predictions');
        $this->info('4. Consider upgrading more matches: php artisan prediction:upgrade-to-intelligent --matches=100');

        return 0;
    }
}