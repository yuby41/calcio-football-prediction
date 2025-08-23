<?php

namespace App\Constants;

/**
 * Constants for ML Prediction System
 */
class PredictionConstants
{
    // Confidence Score Ranges
    public const MIN_CONFIDENCE_SCORE = 0;
    public const MAX_CONFIDENCE_SCORE = 100;
    public const DEFAULT_CONFIDENCE_SCORE = 50;
    public const HIGH_CONFIDENCE_THRESHOLD = 80;
    public const LOW_CONFIDENCE_THRESHOLD = 60;
    
    // Probability Ranges
    public const MIN_PROBABILITY = 0.0;
    public const MAX_PROBABILITY = 1.0;
    public const DEFAULT_PROBABILITY = 0.5;
    
    // ML Model Timeouts (seconds)
    public const ML_PREDICTION_TIMEOUT = 45;
    public const ML_TRAINING_TIMEOUT = 1800; // 30 minutes
    public const ML_DEPENDENCY_CHECK_TIMEOUT = 30;
    public const ML_INSTALL_TIMEOUT = 300; // 5 minutes
    
    // Fallback Prediction Values
    public const FALLBACK_HOME_WIN_PROBABILITY = 0.45;
    public const FALLBACK_AWAY_WIN_PROBABILITY = 0.30;
    public const FALLBACK_DRAW_PROBABILITY = 0.25;
    public const FALLBACK_BOTH_TEAMS_SCORE_PROBABILITY = 0.55;
    public const FALLBACK_OVER_2_5_PROBABILITY = 0.50;
    public const FALLBACK_FIRST_HALF_OVER_0_5_PROBABILITY = 0.65;
    
    // Team Statistics Weights
    public const HOME_ADVANTAGE_WEIGHT = 0.15;
    public const RECENT_FORM_WEIGHT = 0.25;
    public const HEAD_TO_HEAD_WEIGHT = 0.20;
    public const LEAGUE_POSITION_WEIGHT = 0.10;
    public const GOALS_SCORED_WEIGHT = 0.15;
    public const GOALS_CONCEDED_WEIGHT = 0.15;
    
    // Match Analysis
    public const MIN_MATCHES_FOR_ANALYSIS = 5;
    public const MAX_MATCHES_FOR_ANALYSIS = 50;
    public const RECENT_MATCHES_LIMIT = 10;
    
    // ML Model Accuracy Thresholds
    public const MIN_ACCEPTABLE_ACCURACY = 0.55; // 55%
    public const GOOD_ACCURACY_THRESHOLD = 0.65; // 65%
    public const EXCELLENT_ACCURACY_THRESHOLD = 0.75; // 75%
}