<?php

namespace App\Constants;

/**
 * Constants for Betting and Financial System
 */
class BettingConstants
{
    // Betting Strategy Types
    public const STRATEGY_FIXED = 'fixed';
    public const STRATEGY_MARTINGALE = 'martingale';
    public const STRATEGY_MANSANIELLO = 'mansaniello';
    public const STRATEGY_FIBONACCI = 'fibonacci';
    public const STRATEGY_PERCENTAGE = 'percentage';
    
    // Risk Management
    public const MIN_BET_AMOUNT = 1.0;
    public const MAX_BET_PERCENTAGE_OF_BALANCE = 0.10; // 10% max of available balance
    public const DEFAULT_RISK_MULTIPLIER = 2.0;
    public const MAX_CONSECUTIVE_LOSSES = 5;
    public const EMERGENCY_STOP_LOSS_PERCENTAGE = 0.80; // Stop if lost 80% of budget
    
    // Odds Ranges
    public const MIN_ACCEPTABLE_ODDS = 1.10;
    public const MAX_ACCEPTABLE_ODDS = 10.0;
    public const DEFAULT_FALLBACK_ODDS = 2.0;
    
    // Confidence Thresholds for Betting
    public const MIN_BETTING_CONFIDENCE = 65.0;
    public const HIGH_CONFIDENCE_BETTING = 85.0;
    public const ULTRA_HIGH_CONFIDENCE = 95.0;
    
    // Profit Targets
    public const DEFAULT_TARGET_PROFIT_PERCENTAGE = 20.0; // 20%
    public const MIN_TARGET_PROFIT_PERCENTAGE = 5.0;
    public const MAX_TARGET_PROFIT_PERCENTAGE = 100.0;
    
    // Budget Management
    public const MIN_BUDGET_AMOUNT = 50.0;
    public const BUDGET_RESERVE_PERCENTAGE = 0.10; // Keep 10% as reserve
    public const AUTO_DEACTIVATE_THRESHOLD = 10.0; // Deactivate if balance < $10
    
    // Mansaniello Sequence
    public const MANSANIELLO_SEQUENCE = [1, 1, 2, 4, 8, 16];
    public const MANSANIELLO_MAX_STEPS = 6;
    
    // Fibonacci Sequence for betting
    public const FIBONACCI_SEQUENCE = [1, 1, 2, 3, 5, 8, 13, 21];
    public const FIBONACCI_MAX_STEPS = 8;
    
    // Bet Types
    public const BET_TYPE_MATCH_OUTCOME = 'match_outcome';
    public const BET_TYPE_OVER_UNDER_2_5 = 'over_under_2_5';
    public const BET_TYPE_BOTH_TEAMS_SCORE = 'both_teams_score';
    public const BET_TYPE_FIRST_HALF_OVER_0_5 = 'first_half_over_0_5';
    
    // Bet Outcomes
    public const OUTCOME_HOME_WIN = 'home_win';
    public const OUTCOME_AWAY_WIN = 'away_win';
    public const OUTCOME_DRAW = 'draw';
    public const OUTCOME_OVER_2_5 = 'over_2_5';
    public const OUTCOME_UNDER_2_5 = 'under_2_5';
    public const OUTCOME_YES = 'yes';
    public const OUTCOME_NO = 'no';
    
    // Performance Tracking
    public const PERFORMANCE_TRACKING_DAYS = 30;
    public const MIN_BETS_FOR_STRATEGY_EVALUATION = 20;
    
    // System Limits
    public const MAX_ACTIVE_BUDGETS_PER_USER = 5;
    public const MAX_DAILY_BETS_PER_BUDGET = 10;
    public const COOLING_PERIOD_HOURS = 1; // Wait 1 hour between failed bets
}