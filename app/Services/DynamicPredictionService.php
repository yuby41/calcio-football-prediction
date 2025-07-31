<?php

namespace App\Services;

use App\Models\PredictionStatistic;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class DynamicPredictionService
{
    const MIN_ACCURACY_THRESHOLD = 50.0;
    const CACHE_KEY = 'active_prediction_types';
    const CACHE_DURATION = 86400; // 24 hours in seconds

    /**
     * Get active prediction types based on accuracy thresholds
     * Updates daily and caches results
     */
    public function getActivePredictionTypes(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_DURATION, function () {
            return $this->calculateActivePredictionTypes();
        });
    }

    /**
     * Force refresh of active prediction types
     */
    public function refreshActivePredictionTypes(): array
    {
        Cache::forget(self::CACHE_KEY);
        return $this->getActivePredictionTypes();
    }

    /**
     * Calculate which prediction types should be active based on accuracy
     */
    private function calculateActivePredictionTypes(): array
    {
        $statistics = PredictionStatistic::all();
        $activePredictions = [];

        foreach ($statistics as $stat) {
            if ($stat->accuracy_percentage >= self::MIN_ACCURACY_THRESHOLD) {
                $activePredictions[] = [
                    'type' => $stat->prediction_type,
                    'accuracy' => $stat->accuracy_percentage,
                    'display_name' => $stat->display_name,
                    'total_predictions' => $stat->total_predictions,
                    'correct_predictions' => $stat->correct_predictions
                ];
            }
        }

        // Sort by accuracy descending
        usort($activePredictions, function($a, $b) {
            return $b['accuracy'] <=> $a['accuracy'];
        });

        return $activePredictions;
    }

    /**
     * Check if a specific prediction type is currently active
     */
    public function isPredictionTypeActive(string $predictionType): bool
    {
        $activeTypes = $this->getActivePredictionTypes();
        
        foreach ($activeTypes as $type) {
            if ($type['type'] === $predictionType) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the accuracy for a specific prediction type
     */
    public function getAccuracyForType(string $predictionType): float
    {
        $activeTypes = $this->getActivePredictionTypes();
        
        foreach ($activeTypes as $type) {
            if ($type['type'] === $predictionType) {
                return $type['accuracy'];
            }
        }

        return 0.0;
    }

    /**
     * Get prediction types mapped for bet recommendations
     */
    public function getActiveBetTypes(): array
    {
        $activeTypes = $this->getActivePredictionTypes();
        $betTypeMappings = [
            'match_outcome' => ['home_win', 'away_win', 'draw'],
            'both_teams_score_yes' => ['both_teams_score'], // "Gol" prediction type
            // Note: both_teams_score_no is handled separately, not duplicated
            'over_2_5' => ['over_2_5'],
            'under_2_5' => ['under_2_5'],
            'first_half_over_0_5' => ['over_0_5_first_half']
        ];

        $activeBetTypes = [];
        
        foreach ($activeTypes as $type) {
            if (isset($betTypeMappings[$type['type']])) {
                foreach ($betTypeMappings[$type['type']] as $betType) {
                    $activeBetTypes[] = [
                        'bet_type' => $betType,
                        'prediction_type' => $type['type'],
                        'accuracy' => $type['accuracy'],
                        'display_name' => $type['display_name']
                    ];
                }
            }
        }

        return $activeBetTypes;
    }

    /**
     * Get summary statistics for admin/dashboard
     */
    public function getActivePredictionsSummary(): array
    {
        $activeTypes = $this->getActivePredictionTypes();
        $allTypes = PredictionStatistic::count();
        
        return [
            'active_count' => count($activeTypes),
            'total_count' => $allTypes,
            'last_updated' => Cache::get(self::CACHE_KEY . '_timestamp', now()),
            'threshold' => self::MIN_ACCURACY_THRESHOLD,
            'predictions' => $activeTypes
        ];
    }

    /**
     * Update cache timestamp when predictions are refreshed
     */
    public function updateCacheTimestamp(): void
    {
        Cache::put(self::CACHE_KEY . '_timestamp', now(), self::CACHE_DURATION);
    }
}