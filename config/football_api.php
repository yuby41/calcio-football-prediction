<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Football API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Football API with optimized settings for 7500 daily requests
    |
    */

    'base_url' => env('FOOTBALL_API_BASE_URL', 'https://v3.football.api-sports.io'),
    'key' => env('FOOTBALL_API_KEY'),
    'timezone' => env('APP_TIMEZONE', 'Europe/Madrid'),

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting Configuration (7500 daily requests plan)
    |--------------------------------------------------------------------------
    */
    'rate_limits' => [
        'daily_limit' => 7500,                    // Daily request limit
        'requests_per_minute' => 250,             // Safe buffer from 300/min limit
        'requests_per_hour' => 312,               // 7500/24 hours
        'burst_limit' => 100,                     // Max burst requests
        'cooldown_seconds' => 1,                  // Delay between requests
    ],

    /*
    |--------------------------------------------------------------------------
    | Optimized Cache Durations (in seconds)
    |--------------------------------------------------------------------------
    */
    'cache_durations' => [
        // Static data - long cache
        'timezones' => 2592000,                   // 30 days
        'countries' => 604800,                    // 7 days
        'leagues' => 86400,                       // 1 day
        'teams' => 604800,                        // 7 days
        'venues' => 2592000,                      // 30 days

        // Dynamic data - medium cache
        'standings' => 3600,                      // 1 hour (was 6 hours)
        'team_statistics' => 3600,                // 1 hour (was 6 hours)
        'player_statistics' => 7200,              // 2 hours
        'injuries' => 10800,                      // 3 hours

        // Live data - short cache
        'fixtures_today' => 300,                  // 5 minutes (was 15)
        'fixtures_live' => 60,                    // 1 minute (was none)
        'fixtures_upcoming' => 1800,              // 30 minutes
        'fixtures_week' => 3600,                  // 1 hour

        // Match-specific data
        'match_lineups' => 3600,                  // 1 hour
        'match_events' => 300,                    // 5 minutes for live
        'match_statistics' => 300,                // 5 minutes for live
        'match_predictions' => 43200,             // 12 hours

        // Odds data
        'odds_pre_match' => 900,                  // 15 minutes
        'odds_live' => 60,                        // 1 minute
    ],

    /*
    |--------------------------------------------------------------------------
    | Priority Leagues (High-frequency updates)
    |--------------------------------------------------------------------------
    */
    'priority_leagues' => [
        'PL',     // Premier League
        'PD',     // La Liga
        'BL1',    // Bundesliga
        'SA',     // Serie A
        'FL1',    // Ligue 1
        'CL',     // Champions League
        'EL',     // Europa League
    ],

    /*
    |--------------------------------------------------------------------------
    | Secondary Leagues (Lower-frequency updates)
    |--------------------------------------------------------------------------
    */
    'secondary_leagues' => [
        'EC',     // Championship
        'PPL',    // Primeira Liga
        'DED',    // Eredivisie
        'BSA',    // Brasileirão
        'MLS',    // MLS
        'WC',     // World Cup
        'EURO',   // European Championship
    ],

    /*
    |--------------------------------------------------------------------------
    | Sync Frequencies (optimized for 7500 daily requests)
    |--------------------------------------------------------------------------
    */
    'sync_frequencies' => [
        // Live matches (during match hours: 12:00-23:00 CET)
        'live_matches' => [
            'interval' => '*/2',                  // Every 2 minutes
            'active_hours' => [12, 23],           // 12:00-23:00
            'estimated_requests' => 330,          // ~5.5 hours * 30 intervals/hour * 2 requests
        ],

        // Today's matches
        'today_matches' => [
            'interval' => '*/10',                 // Every 10 minutes
            'estimated_requests' => 144,          // 24 hours * 6 intervals/hour * 1 request
        ],

        // Weekly fixtures
        'weekly_fixtures' => [
            'interval' => '*/30',                 // Every 30 minutes
            'estimated_requests' => 1152,         // 24 hours * 2 intervals/hour * 24 requests (7 days * various leagues)
        ],

        // Standings & team stats
        'standings_stats' => [
            'interval' => '*/30',                 // Every 30 minutes
            'estimated_requests' => 336,          // 24 hours * 2 intervals/hour * 7 leagues
        ],

        // Season data (comprehensive)
        'season_data' => [
            'interval' => '0 */2',                // Every 2 hours
            'estimated_requests' => 840,          // 12 intervals/day * 70 requests (10 leagues * 7 requests each)
        ],

        // Predictions & ML
        'predictions' => [
            'interval' => '*/30',                 // Every 30 minutes
            'estimated_requests' => 480,          // 24 hours * 2 intervals/hour * 10 predictions
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Request Distribution Strategy
    |--------------------------------------------------------------------------
    */
    'distribution' => [
        'peak_hours' => [14, 22],                 // 14:00-22:00 (match times)
        'peak_multiplier' => 2.0,                 // 2x more requests during peak
        'off_peak_multiplier' => 0.5,             // Half requests during off-peak
        'night_hours' => [0, 7],                  // 00:00-07:00 (minimal activity)
        'night_multiplier' => 0.2,                // Minimal requests at night
    ],

    /*
    |--------------------------------------------------------------------------
    | Request Monitoring & Alerting
    |--------------------------------------------------------------------------
    */
    'monitoring' => [
        'daily_usage_alert' => 6000,              // Alert at 80% usage (6000/7500)
        'hourly_usage_alert' => 250,              // Alert at 80% hourly usage
        'low_quota_alert' => 500,                 // Alert when <500 requests remaining
        'enable_slack_alerts' => false,
        'enable_email_alerts' => true,
    ],
];