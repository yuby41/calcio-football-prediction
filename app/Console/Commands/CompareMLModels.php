<?php

namespace App\Console\Commands;

use App\Models\MatchPrediction;
use App\Models\FootballMatch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CompareMLModels extends Command
{
    protected $signature = 'ml:compare-models 
                            {--models=* : Specific model versions to compare (leave empty for all)}
                            {--metric=all : Metric to compare (all, accuracy, precision, recall, f1)}
                            {--type=all : Prediction type (all, outcome, both_teams_score, over_under, first_half)}
                            {--min-predictions=50 : Minimum predictions required for comparison}
                            {--export-csv= : Export results to CSV file}';
    
    protected $description = 'Compare ML model performance across different metrics and prediction types';

    private $metrics = [];
    private $models = [];

    public function handle()
    {
        $this->info('🔍 ML MODEL COMPARISON ANALYSIS');
        $this->info('================================');
        
        $selectedModels = $this->option('models');
        $metric = $this->option('metric');
        $type = $this->option('type');
        $minPredictions = (int) $this->option('min-predictions');
        $exportCsv = $this->option('export-csv');

        // Get available models
        $this->models = $this->getAvailableModels($minPredictions);
        
        if (empty($this->models)) {
            $this->error('No models found with sufficient predictions');
            return 1;
        }

        // Filter models if specified
        if (!empty($selectedModels)) {
            $this->models = array_intersect($this->models, $selectedModels);
        }

        $this->info("Found " . count($this->models) . " models to compare:");
        foreach ($this->models as $model) {
            $count = MatchPrediction::where('model_version', $model)->count();
            $this->line("  • {$model} ({$count} predictions)");
        }
        $this->line('');

        // Run comparisons based on type
        if ($type === 'all' || $type === 'outcome') {
            $this->compareOutcomePredictions();
        }
        
        if ($type === 'all' || $type === 'both_teams_score') {
            $this->compareBothTeamsScore();
        }
        
        if ($type === 'all' || $type === 'over_under') {
            $this->compareOverUnder();
        }
        
        if ($type === 'all' || $type === 'first_half') {
            $this->compareFirstHalf();
        }

        // Show summary
        $this->showSummary();
        
        // Export to CSV if requested
        if ($exportCsv) {
            $this->exportToCsv($exportCsv);
        }

        return 0;
    }

    private function getAvailableModels(int $minPredictions): array
    {
        return MatchPrediction::select('model_version')
            ->whereHas('match', function($query) {
                $query->where('status', 'finished')
                      ->whereNotNull('home_goals')
                      ->whereNotNull('away_goals');
            })
            ->groupBy('model_version')
            ->havingRaw('COUNT(*) >= ?', [$minPredictions])
            ->pluck('model_version')
            ->toArray();
    }

    private function compareOutcomePredictions()
    {
        $this->info('📊 MATCH OUTCOME PREDICTIONS COMPARISON');
        $this->info('=====================================');

        $headers = ['Model', 'Total', 'Correct', 'Accuracy %', 'Home Win %', 'Draw %', 'Away Win %', 'Avg Confidence'];
        $rows = [];

        foreach ($this->models as $model) {
            $predictions = MatchPrediction::where('model_version', $model)
                ->whereHas('match', function($query) {
                    $query->where('status', 'finished')
                          ->whereNotNull('home_goals')
                          ->whereNotNull('away_goals');
                })
                ->with('match')
                ->get();

            if ($predictions->isEmpty()) continue;

            $total = $predictions->count();
            $correct = 0;
            $homeWins = 0;
            $draws = 0;
            $awayWins = 0;
            $totalConfidence = 0;

            foreach ($predictions as $prediction) {
                $match = $prediction->match;
                $actual = $this->getActualOutcome($match);
                
                if ($prediction->predicted_outcome === $actual) {
                    $correct++;
                }

                // Count prediction types
                switch ($prediction->predicted_outcome) {
                    case 'home_win': $homeWins++; break;
                    case 'draw': $draws++; break;
                    case 'away_win': $awayWins++; break;
                }

                $totalConfidence += $prediction->confidence_score ?? 0.5;
            }

            $accuracy = round(($correct / $total) * 100, 2);
            $homeWinPct = round(($homeWins / $total) * 100, 1);
            $drawPct = round(($draws / $total) * 100, 1);
            $awayWinPct = round(($awayWins / $total) * 100, 1);
            $avgConfidence = round(($totalConfidence / $total) * 100, 1);

            $rows[] = [
                $model,
                $total,
                $correct,
                $accuracy . '%',
                $homeWinPct . '%',
                $drawPct . '%', 
                $awayWinPct . '%',
                $avgConfidence . '%'
            ];

            // Store for summary
            $this->metrics[$model]['outcome_accuracy'] = $accuracy;
            $this->metrics[$model]['outcome_total'] = $total;
        }

        $this->table($headers, $rows);
        $this->line('');
    }

    private function compareBothTeamsScore()
    {
        $this->info('⚽ BOTH TEAMS SCORE PREDICTIONS COMPARISON');
        $this->info('=========================================');

        $headers = ['Model', 'Total', 'Correct', 'Accuracy %', 'YES Pred %', 'NO Pred %', 'Precision', 'Recall'];
        $rows = [];

        foreach ($this->models as $model) {
            $predictions = MatchPrediction::where('model_version', $model)
                ->whereNotNull('both_teams_score_probability')
                ->whereHas('match', function($query) {
                    $query->where('status', 'finished')
                          ->whereNotNull('home_goals')
                          ->whereNotNull('away_goals');
                })
                ->with('match')
                ->get();

            if ($predictions->isEmpty()) continue;

            $total = $predictions->count();
            $correct = 0;
            $yesPredictions = 0;
            $noPredictions = 0;
            $truePositives = 0;
            $falsePositives = 0;
            $falseNegatives = 0;

            foreach ($predictions as $prediction) {
                $match = $prediction->match;
                $actualBts = ($match->home_goals > 0 && $match->away_goals > 0);
                $predictedBts = $prediction->both_teams_score_probability > 0.5;

                if ($actualBts === $predictedBts) {
                    $correct++;
                }

                if ($predictedBts) {
                    $yesPredictions++;
                    if ($actualBts) {
                        $truePositives++;
                    } else {
                        $falsePositives++;
                    }
                } else {
                    $noPredictions++;
                    if ($actualBts) {
                        $falseNegatives++;
                    }
                }
            }

            $accuracy = round(($correct / $total) * 100, 2);
            $yesPct = round(($yesPredictions / $total) * 100, 1);
            $noPct = round(($noPredictions / $total) * 100, 1);
            
            $precision = $truePositives + $falsePositives > 0 ? 
                round(($truePositives / ($truePositives + $falsePositives)) * 100, 1) : 0;
            $recall = $truePositives + $falseNegatives > 0 ? 
                round(($truePositives / ($truePositives + $falseNegatives)) * 100, 1) : 0;

            $rows[] = [
                $model,
                $total,
                $correct,
                $accuracy . '%',
                $yesPct . '%',
                $noPct . '%',
                $precision . '%',
                $recall . '%'
            ];

            $this->metrics[$model]['bts_accuracy'] = $accuracy;
            $this->metrics[$model]['bts_precision'] = $precision;
            $this->metrics[$model]['bts_recall'] = $recall;
        }

        $this->table($headers, $rows);
        $this->line('');
    }

    private function compareOverUnder()
    {
        $this->info('🎯 OVER/UNDER 2.5 GOALS COMPARISON');
        $this->info('==================================');

        $headers = ['Model', 'Total', 'Over Correct', 'Under Correct', 'Over Acc %', 'Under Acc %', 'Overall Acc %'];
        $rows = [];

        foreach ($this->models as $model) {
            $predictions = MatchPrediction::where('model_version', $model)
                ->whereNotNull('over_2_5_probability')
                ->whereHas('match', function($query) {
                    $query->where('status', 'finished')
                          ->whereNotNull('home_goals')
                          ->whereNotNull('away_goals');
                })
                ->with('match')
                ->get();

            if ($predictions->isEmpty()) continue;

            $total = $predictions->count();
            $overCorrect = 0;
            $underCorrect = 0;
            $overTotal = 0;
            $underTotal = 0;

            foreach ($predictions as $prediction) {
                $match = $prediction->match;
                $totalGoals = $match->home_goals + $match->away_goals;
                $actualOver = $totalGoals > 2.5;
                $predictedOver = $prediction->over_2_5_probability > 0.5;

                if ($predictedOver) {
                    $overTotal++;
                    if ($actualOver) {
                        $overCorrect++;
                    }
                } else {
                    $underTotal++;
                    if (!$actualOver) {
                        $underCorrect++;
                    }
                }
            }

            $overAcc = $overTotal > 0 ? round(($overCorrect / $overTotal) * 100, 1) : 0;
            $underAcc = $underTotal > 0 ? round(($underCorrect / $underTotal) * 100, 1) : 0;
            $overallAcc = round((($overCorrect + $underCorrect) / $total) * 100, 1);

            $rows[] = [
                $model,
                $total,
                "{$overCorrect}/{$overTotal}",
                "{$underCorrect}/{$underTotal}",
                $overAcc . '%',
                $underAcc . '%',
                $overallAcc . '%'
            ];

            $this->metrics[$model]['over_under_accuracy'] = $overallAcc;
        }

        $this->table($headers, $rows);
        $this->line('');
    }

    private function compareFirstHalf()
    {
        $this->info('🕐 FIRST HALF OVER 0.5 GOALS COMPARISON');
        $this->info('======================================');

        $headers = ['Model', 'Total', 'Correct', 'Accuracy %', 'Over Pred %', 'Under Pred %'];
        $rows = [];

        foreach ($this->models as $model) {
            $predictions = MatchPrediction::where('model_version', $model)
                ->whereNotNull('first_half_over_0_5_probability')
                ->whereHas('match', function($query) {
                    $query->where('status', 'finished')
                          ->whereNotNull('home_goals_first_half')
                          ->whereNotNull('away_goals_first_half');
                })
                ->with('match')
                ->get();

            if ($predictions->isEmpty()) continue;

            $total = $predictions->count();
            $correct = 0;
            $overPredictions = 0;

            foreach ($predictions as $prediction) {
                $match = $prediction->match;
                $firstHalfGoals = ($match->home_goals_first_half ?? 0) + ($match->away_goals_first_half ?? 0);
                $actualOver = $firstHalfGoals > 0.5;
                $predictedOver = $prediction->first_half_over_0_5_probability > 0.5;

                if ($actualOver === $predictedOver) {
                    $correct++;
                }

                if ($predictedOver) {
                    $overPredictions++;
                }
            }

            $accuracy = round(($correct / $total) * 100, 2);
            $overPct = round(($overPredictions / $total) * 100, 1);
            $underPct = round((($total - $overPredictions) / $total) * 100, 1);

            $rows[] = [
                $model,
                $total,
                $correct,
                $accuracy . '%',
                $overPct . '%',
                $underPct . '%'
            ];

            $this->metrics[$model]['first_half_accuracy'] = $accuracy;
        }

        $this->table($headers, $rows);
        $this->line('');
    }

    private function showSummary()
    {
        $this->info('📈 OVERALL MODEL PERFORMANCE SUMMARY');
        $this->info('===================================');

        $headers = ['Model', 'Outcome Acc %', 'BTS Acc %', 'O/U Acc %', 'First Half Acc %', 'Overall Score'];
        $rows = [];

        foreach ($this->models as $model) {
            $metrics = $this->metrics[$model] ?? [];
            
            $outcomeAcc = $metrics['outcome_accuracy'] ?? 0;
            $btsAcc = $metrics['bts_accuracy'] ?? 0;
            $ouAcc = $metrics['over_under_accuracy'] ?? 0;
            $fhAcc = $metrics['first_half_accuracy'] ?? 0;

            // Calculate weighted overall score
            $overallScore = ($outcomeAcc * 0.4) + ($btsAcc * 0.2) + ($ouAcc * 0.2) + ($fhAcc * 0.2);

            $rows[] = [
                $model,
                $outcomeAcc ? $outcomeAcc . '%' : 'N/A',
                $btsAcc ? $btsAcc . '%' : 'N/A',
                $ouAcc ? $ouAcc . '%' : 'N/A',
                $fhAcc ? $fhAcc . '%' : 'N/A',
                round($overallScore, 1) . '%'
            ];
        }

        // Sort by overall score
        usort($rows, function($a, $b) {
            $scoreA = (float) str_replace('%', '', $a[5]);
            $scoreB = (float) str_replace('%', '', $b[5]);
            return $scoreB <=> $scoreA;
        });

        $this->table($headers, $rows);

        // Show winner
        if (!empty($rows)) {
            $winner = $rows[0];
            $this->info("🏆 BEST PERFORMING MODEL: {$winner[0]} (Overall Score: {$winner[5]})");
        }
    }

    private function getActualOutcome(FootballMatch $match): string
    {
        if ($match->home_goals > $match->away_goals) {
            return 'home_win';
        } elseif ($match->home_goals < $match->away_goals) {
            return 'away_win';
        } else {
            return 'draw';
        }
    }

    private function exportToCsv(string $filename)
    {
        $this->info("💾 Exporting results to {$filename}...");
        
        $csvData = [];
        $csvData[] = ['Model', 'Outcome_Accuracy', 'BTS_Accuracy', 'OverUnder_Accuracy', 'FirstHalf_Accuracy', 'Overall_Score'];
        
        foreach ($this->models as $model) {
            $metrics = $this->metrics[$model] ?? [];
            $csvData[] = [
                $model,
                $metrics['outcome_accuracy'] ?? 0,
                $metrics['bts_accuracy'] ?? 0,
                $metrics['over_under_accuracy'] ?? 0,
                $metrics['first_half_accuracy'] ?? 0,
                ($metrics['outcome_accuracy'] ?? 0) * 0.4 + 
                ($metrics['bts_accuracy'] ?? 0) * 0.2 + 
                ($metrics['over_under_accuracy'] ?? 0) * 0.2 + 
                ($metrics['first_half_accuracy'] ?? 0) * 0.2
            ];
        }

        $fp = fopen($filename, 'w');
        foreach ($csvData as $row) {
            fputcsv($fp, $row);
        }
        fclose($fp);

        $this->info("✅ Results exported to {$filename}");
    }
}