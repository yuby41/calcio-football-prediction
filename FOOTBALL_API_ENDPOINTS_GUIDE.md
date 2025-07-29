# Football API v3.football.api-sports.io - Endpoints Guide

## 🏆 Complete Endpoints Reference & Best Practices

This guide covers all major endpoints available in the v3.football.api-sports.io API with optimized usage patterns, caching strategies, and rate limiting recommendations.

## 📊 Rate Limits by Plan

| Plan | Daily Requests | Per Minute | Recommended Usage |
|------|----------------|------------|-------------------|
| Free | 100 | ~10 | Development/Testing |
| Basic | 1,000 | 10 | Small apps |
| Pro | 10,000 | 100 | Medium apps |
| Ultra | 100,000 | 300 | Large apps |
| Mega | 1,000,000 | 900 | Enterprise |

## 🗂️ Endpoints by Category

### 1. CORE REFERENCE DATA

#### 🌍 Timezone Endpoint
```php
GET /timezone
```
- **Purpose**: Get available timezones
- **Cache**: 30 days (rarely changes)
- **Frequency**: Once per application lifecycle
- **Usage**: Initialize timezone data

#### 🏳️ Countries Endpoint
```php
GET /countries
```
- **Purpose**: Get all countries with football leagues
- **Cache**: 1 week (countries rarely change)
- **Frequency**: Weekly or less
- **Usage**: Country selection, league filtering

#### 🏆 Leagues Endpoint
```php
GET /leagues?country={country}&season={season}
```
- **Purpose**: Get leagues by country/season
- **Cache**: 1 day (league info stable)
- **Frequency**: Daily or less
- **Usage**: League management, competition setup

#### 📅 Seasons Endpoint
```php
GET /seasons
```
- **Purpose**: Get available seasons
- **Cache**: 1 month (predictable changes)
- **Frequency**: Monthly
- **Usage**: Season selection, historical data

---

### 2. TEAMS & PLAYERS

#### 🏟️ Teams Endpoint
```php
GET /teams?league={league}&season={season}
```
- **Purpose**: Get teams in a league/season
- **Cache**: 1 week (stable within season)
- **Frequency**: Weekly or per season
- **Usage**: Team data, roster management

#### 👨‍⚽ Players Endpoint
```php
GET /players?team={team}&season={season}
```
- **Purpose**: Get players for a team/season
- **Cache**: 1 week (transfers are infrequent)
- **Frequency**: Weekly
- **Usage**: Player statistics, team analysis

#### 🏥 Injuries Endpoint
```php
GET /injuries?league={league}&season={season}
```
- **Purpose**: Get injury reports
- **Cache**: 1 day (medical updates daily)
- **Frequency**: Daily
- **Usage**: Team strength analysis, predictions

---

### 3. COMPETITION DATA

#### 📈 Standings Endpoint
```php
GET /standings?league={league}&season={season}
```
- **Purpose**: Get league table/standings
- **Cache**: 6 hours (changes after matches)
- **Frequency**: Every 6 hours during season
- **Usage**: League tables, team positions

#### 📊 Team Statistics Endpoint
```php
GET /teams/statistics?team={team}&league={league}&season={season}
```
- **Purpose**: Detailed team performance stats
- **Cache**: 6 hours (updated after matches)
- **Frequency**: Every 6 hours during season
- **Usage**: Advanced analytics, predictions

---

### 4. FIXTURES & MATCHES

#### 📅 Fixtures Endpoint (Main)
```php
GET /fixtures?league={league}&season={season}&date={date}
```
- **Purpose**: Get matches with flexible filtering
- **Cache**: Varies by status:
  - **Historical**: 1 week
  - **Future**: 1 hour
  - **Today**: 15 minutes
- **Frequency**: 
  - **Historical**: Rarely
  - **Future**: Hourly
  - **Today**: Every 15-30 minutes

#### 🔴 Live Fixtures
```php
GET /fixtures?live=all
```
- **Purpose**: Get currently live matches
- **Cache**: None (real-time)
- **Frequency**: Every 15 seconds during matches
- **Usage**: Live scores, real-time updates

#### 📋 Fixture Events
```php
GET /fixtures/events?fixture={fixture_id}
```
- **Purpose**: Get match events (goals, cards, etc.)
- **Cache**: 
  - **Live**: None
  - **Finished**: 1 day
- **Frequency**: 
  - **Live**: Every 15-30 seconds
  - **Finished**: Once
- **Usage**: Match timeline, detailed analysis

#### 👥 Fixture Lineups
```php
GET /fixtures/lineups?fixture={fixture_id}
```
- **Purpose**: Get team lineups and formations
- **Cache**: 24 hours (set before match)
- **Frequency**: Once per match (1-2 hours before)
- **Usage**: Tactical analysis, player tracking

#### 📊 Fixture Statistics
```php
GET /fixtures/statistics?fixture={fixture_id}
```
- **Purpose**: Get detailed match statistics
- **Cache**: 
  - **Live**: 1 hour
  - **Finished**: 1 day
- **Frequency**: 
  - **Live**: Hourly
  - **Finished**: Once after completion
- **Usage**: Performance analysis, advanced metrics

---

### 5. BETTING & PREDICTIONS

#### 💰 Odds Endpoint
```php
GET /odds?fixture={fixture_id}
GET /odds/live  // Live odds
```
- **Purpose**: Get betting odds
- **Cache**: 
  - **Pre-match**: 30 minutes
  - **Live**: None
- **Frequency**: 
  - **Pre-match**: Every 30 minutes
  - **Live**: Every 15 seconds
- **Usage**: Betting analysis, market predictions

#### 🔮 Predictions Endpoint
```php
GET /predictions?fixture={fixture_id}
```
- **Purpose**: Get AI predictions
- **Cache**: 24 hours (until match starts)
- **Frequency**: Once per match
- **Usage**: AI insights, betting guidance

---

## 🚀 Optimized Usage Patterns

### Daily Operations Schedule

```bash
# Morning (8:00 AM)
php artisan football:sync-leagues-teams --force  # Weekly
php artisan football:sync-fixtures-optimized --type=week

# Pre-match Hours (11:00 AM, 3:00 PM, 7:00 PM)
php artisan football:sync-fixtures-optimized --type=today --with-events

# During Match Days (Every 15 seconds)
php artisan football:sync-fixtures-optimized --type=live

# Post-Match (11:00 PM)
php artisan football:sync-fixtures-optimized --type=today --with-stats
```

### Caching Strategy

```php
// Long-term cache (rarely changes)
Cache::remember('api_timezones', now()->addDays(30), ...);
Cache::remember('api_countries', now()->addWeek(), ...);

// Medium-term cache (seasonal changes)
Cache::remember('api_teams_PL_2025', now()->addDays(7), ...);
Cache::remember('api_standings_PL_2025', now()->addHours(6), ...);

// Short-term cache (frequent updates)
Cache::remember('api_today_matches', now()->addMinutes(15), ...);

// No cache (real-time)
// Live matches, live odds, live events
```

### Rate Limiting Best Practices

```php
// Sequential requests with delays
foreach ($leagues as $league) {
    $data = $api->fetchFixtures($league);
    sleep(1); // 1 second delay between leagues
}

// Batch processing with progress tracking
$bar = $this->output->createProgressBar(count($matches));
foreach ($matches as $match) {
    $this->processMatch($match);
    usleep(500000); // 500ms delay
    $bar->advance();
}

// Monitor rate limits
$rateLimit = $api->checkRateLimit();
if ($rateLimit['requests_remaining'] < 100) {
    $this->warn('Approaching daily rate limit!');
}
```

## 🛠️ Implementation Examples

### Basic Match Sync
```php
// High-frequency (every 15 minutes)
php artisan football:sync-fixtures-optimized --type=today

// Medium-frequency (every 6 hours)  
php artisan football:sync-fixtures-optimized --type=week --leagues=PL,PD

// Low-frequency (daily)
php artisan football:sync-leagues-teams --leagues=PL,PD,BL1,SA,FL1
```

### Live Match Monitoring
```php
// Real-time live scores (every 15 seconds during matches)
php artisan football:sync-fixtures-optimized --type=live --with-events

// Cron job setup for live monitoring
*/1 * * * * php artisan football:sync-fixtures-optimized --type=live
```

### Historical Data Import
```php
// Full season import (use sparingly!)
php artisan football:sync-fixtures-optimized --type=season --leagues=PL

// Date range import
php artisan football:sync-fixtures-optimized --type=week
```

## ⚠️ Important Warnings

### Rate Limit Considerations
- **Free Plan**: Max 10 requests per day for testing only
- **Basic Plan**: Suitable for single league tracking
- **Pro+ Plans**: Required for multi-league real-time updates

### Request Cost Optimization
1. **Most Expensive**: Season sync (50+ requests per league)
2. **Moderate**: Live updates with events (1-5 requests per minute)
3. **Cheap**: Daily fixture sync (1-5 requests per day)

### Caching is Critical
- Without caching, you'll hit rate limits quickly
- Cache historical data for weeks, live data for minutes
- Use Redis for production caching

## 📈 Monitoring & Alerting

### Rate Limit Monitoring
```php
// Check before expensive operations
$usage = $api->checkRateLimit();
$dailyUsage = ($usage['requests_limit'] - $usage['requests_remaining']) / $usage['requests_limit'];

if ($dailyUsage > 0.8) {
    Log::warning('API usage above 80%', $usage);
}
```

### Error Handling
```php
try {
    $matches = $api->fetchTodayMatches();
} catch (RateLimitException $e) {
    Log::error('Rate limit exceeded', ['message' => $e->getMessage()]);
    // Implement backoff strategy
} catch (ApiException $e) {
    Log::error('API error', ['message' => $e->getMessage()]);
    // Retry with exponential backoff
}
```

## 🎯 Recommended Cron Jobs

```bash
# Master schedule for production use

# Every 15 minutes - Today's matches and live updates
*/15 * * * * cd /path/to/calcio && php artisan football:sync-fixtures-optimized --type=today

# Every minute during match hours (14:00-23:00 UTC)
* 14-23 * * * cd /path/to/calcio && php artisan football:sync-fixtures-optimized --type=live

# Every 6 hours - Week view and standings
0 */6 * * * cd /path/to/calcio && php artisan football:sync-fixtures-optimized --type=week

# Daily at 6 AM - League and team sync
0 6 * * * cd /path/to/calcio && php artisan football:sync-leagues-teams

# Weekly on Sunday at 3 AM - Full refresh
0 3 * * 0 cd /path/to/calcio && php artisan football:sync-leagues-teams --force
```

This comprehensive guide ensures optimal usage of the Football API while respecting rate limits and maximizing data freshness for your Calcio application.