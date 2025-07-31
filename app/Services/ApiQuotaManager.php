<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ApiQuotaManager
{
    private const DAILY_LIMIT = 7500;
    private const HOURLY_LIMIT = 312; // 7500/24
    private const MINUTE_LIMIT = 5; // Conservative limit to avoid bursting

    private const CACHE_KEYS = [
        'api_requests_today' => 'api_requests_today_',
        'api_requests_hour' => 'api_requests_hour_',
        'api_requests_minute' => 'api_requests_minute_',
        'api_last_request' => 'api_last_request',
        'api_quota_alerts' => 'api_quota_alerts_',
    ];

    /**
     * Check if we can make an API request based on current quota usage
     */
    public function canMakeRequest(string $requestType = 'general', int $priority = 1): bool
    {
        $now = Carbon::now();
        
        // Check daily limit
        $dailyKey = self::CACHE_KEYS['api_requests_today'] . $now->format('Y-m-d');
        $dailyUsage = Cache::get($dailyKey, 0);
        
        // Check hourly limit
        $hourlyKey = self::CACHE_KEYS['api_requests_hour'] . $now->format('Y-m-d-H');
        $hourlyUsage = Cache::get($hourlyKey, 0);
        
        // Check minute limit
        $minuteKey = self::CACHE_KEYS['api_requests_minute'] . $now->format('Y-m-d-H-i');
        $minuteUsage = Cache::get($minuteKey, 0);

        // Priority-based allocation
        $dailyAllowance = $this->getDailyAllowanceByPriority($priority);
        $hourlyAllowance = $this->getHourlyAllowanceByPriority($priority);

        // Check limits
        if ($dailyUsage >= min(self::DAILY_LIMIT, $dailyAllowance)) {
            Log::warning("Daily API limit reached", [
                'usage' => $dailyUsage,
                'limit' => $dailyAllowance,
                'request_type' => $requestType
            ]);
            return false;
        }

        if ($hourlyUsage >= min(self::HOURLY_LIMIT, $hourlyAllowance)) {
            Log::warning("Hourly API limit reached", [
                'usage' => $hourlyUsage,
                'limit' => $hourlyAllowance,
                'request_type' => $requestType
            ]);
            return false;
        }

        if ($minuteUsage >= self::MINUTE_LIMIT) {
            Log::info("Minute API limit reached, throttling", [
                'usage' => $minuteUsage,
                'limit' => self::MINUTE_LIMIT,
                'request_type' => $requestType
            ]);
            return false;
        }

        return true;
    }

    /**
     * Record an API request and update usage counters
     */
    public function recordRequest(string $requestType = 'general', int $requestCount = 1): void
    {
        $now = Carbon::now();
        
        // Daily counter
        $dailyKey = self::CACHE_KEYS['api_requests_today'] . $now->format('Y-m-d');
        Cache::increment($dailyKey, $requestCount);
        Cache::put($dailyKey, Cache::get($dailyKey), $now->endOfDay());
        
        // Hourly counter
        $hourlyKey = self::CACHE_KEYS['api_requests_hour'] . $now->format('Y-m-d-H');
        Cache::increment($hourlyKey, $requestCount);
        Cache::put($hourlyKey, Cache::get($hourlyKey), $now->endOfHour());
        
        // Minute counter
        $minuteKey = self::CACHE_KEYS['api_requests_minute'] . $now->format('Y-m-d-H-i');
        Cache::increment($minuteKey, $requestCount);
        Cache::put($minuteKey, Cache::get($minuteKey), $now->endOfMinute());

        // Last request timestamp
        Cache::put(self::CACHE_KEYS['api_last_request'], $now->timestamp, 86400);

        // Log request for monitoring
        Log::info("API request recorded", [
            'type' => $requestType,
            'count' => $requestCount,
            'daily_usage' => Cache::get($dailyKey),
            'hourly_usage' => Cache::get($hourlyKey),
            'timestamp' => $now->toISOString()
        ]);

        // Check for quota alerts
        $this->checkQuotaAlerts();
    }

    /**
     * Get current usage statistics
     */
    public function getUsageStats(): array
    {
        $now = Carbon::now();
        
        $dailyKey = self::CACHE_KEYS['api_requests_today'] . $now->format('Y-m-d');
        $hourlyKey = self::CACHE_KEYS['api_requests_hour'] . $now->format('Y-m-d-H');
        $minuteKey = self::CACHE_KEYS['api_requests_minute'] . $now->format('Y-m-d-H-i');

        $dailyUsage = Cache::get($dailyKey, 0);
        $hourlyUsage = Cache::get($hourlyKey, 0);
        $minuteUsage = Cache::get($minuteKey, 0);

        return [
            'daily' => [
                'used' => $dailyUsage,
                'limit' => self::DAILY_LIMIT,
                'remaining' => self::DAILY_LIMIT - $dailyUsage,
                'percentage' => round(($dailyUsage / self::DAILY_LIMIT) * 100, 2)
            ],
            'hourly' => [
                'used' => $hourlyUsage,
                'limit' => self::HOURLY_LIMIT,
                'remaining' => self::HOURLY_LIMIT - $hourlyUsage,
                'percentage' => round(($hourlyUsage / self::HOURLY_LIMIT) * 100, 2)
            ],
            'minute' => [
                'used' => $minuteUsage,
                'limit' => self::MINUTE_LIMIT,
                'remaining' => self::MINUTE_LIMIT - $minuteUsage,
                'percentage' => round(($minuteUsage / self::MINUTE_LIMIT) * 100, 2)
            ],
            'last_request' => Cache::get(self::CACHE_KEYS['api_last_request']),
            'estimated_daily_usage' => $this->estimateDailyUsage($hourlyUsage),
        ];
    }

    /**
     * Get optimal delay between requests based on current usage
     */
    public function getOptimalDelay(): int
    {
        $stats = $this->getUsageStats();
        
        // If we're over 80% of any limit, increase delay
        if ($stats['daily']['percentage'] > 80) {
            return 5; // 5 seconds
        }
        
        if ($stats['hourly']['percentage'] > 80) {
            return 3; // 3 seconds
        }
        
        if ($stats['minute']['percentage'] > 60) {
            return 15; // 15 seconds to cool down
        }
        
        return 1; // Default 1 second delay
    }

    /**
     * Reset usage counters (for testing or manual reset)
     */
    public function resetCounters(): void
    {
        $now = Carbon::now();
        
        Cache::forget(self::CACHE_KEYS['api_requests_today'] . $now->format('Y-m-d'));
        Cache::forget(self::CACHE_KEYS['api_requests_hour'] . $now->format('Y-m-d-H'));
        Cache::forget(self::CACHE_KEYS['api_requests_minute'] . $now->format('Y-m-d-H-i'));
        
        Log::info("API usage counters reset");
    }

    /**
     * Get daily allowance based on request priority
     */
    private function getDailyAllowanceByPriority(int $priority): int
    {
        return match($priority) {
            1 => self::DAILY_LIMIT,        // Critical: No limits
            2 => (int)(self::DAILY_LIMIT * 0.8), // High: 80% of daily limit
            3 => (int)(self::DAILY_LIMIT * 0.6), // Medium: 60% of daily limit
            4 => (int)(self::DAILY_LIMIT * 0.4), // Low: 40% of daily limit
            default => (int)(self::DAILY_LIMIT * 0.2), // Very low: 20% of daily limit
        };
    }

    /**
     * Get hourly allowance based on request priority
     */
    private function getHourlyAllowanceByPriority(int $priority): int
    {
        return match($priority) {
            1 => self::HOURLY_LIMIT,       // Critical: No limits
            2 => (int)(self::HOURLY_LIMIT * 0.8), // High: 80% of hourly limit
            3 => (int)(self::HOURLY_LIMIT * 0.6), // Medium: 60% of hourly limit
            4 => (int)(self::HOURLY_LIMIT * 0.4), // Low: 40% of hourly limit
            default => (int)(self::HOURLY_LIMIT * 0.2), // Very low: 20% of hourly limit
        };
    }

    /**
     * Estimate daily usage based on current hourly rate
     */
    private function estimateDailyUsage(int $currentHourlyUsage): int
    {
        $now = Carbon::now();
        $hoursRemaining = 24 - $now->hour;
        
        if ($hoursRemaining <= 0) {
            return $currentHourlyUsage;
        }
        
        $currentDailyUsage = Cache::get(
            self::CACHE_KEYS['api_requests_today'] . $now->format('Y-m-d'), 
            0
        );
        
        // Simple linear projection
        $averageHourlyUsage = $currentDailyUsage / max(1, $now->hour);
        $estimatedDailyUsage = $currentDailyUsage + ($averageHourlyUsage * $hoursRemaining);
        
        return (int)$estimatedDailyUsage;
    }

    /**
     * Check if we should send quota alerts
     */
    private function checkQuotaAlerts(): void
    {
        $stats = $this->getUsageStats();
        $now = Carbon::now();
        $alertKey = self::CACHE_KEYS['api_quota_alerts'] . $now->format('Y-m-d-H');
        
        // Avoid spam - only one alert per hour
        if (Cache::has($alertKey)) {
            return;
        }

        // Daily usage alerts
        if ($stats['daily']['percentage'] >= 90) {
            Log::warning("API quota alert: 90% daily limit reached", $stats);
            Cache::put($alertKey, true, $now->endOfHour());
        } elseif ($stats['daily']['percentage'] >= 75) {
            Log::info("API quota notice: 75% daily limit reached", $stats);
        }

        // Estimated daily usage alerts
        if ($stats['estimated_daily_usage'] > self::DAILY_LIMIT * 1.1) {
            Log::warning("API quota projection alert: Estimated to exceed daily limit", [
                'estimated' => $stats['estimated_daily_usage'],
                'limit' => self::DAILY_LIMIT,
                'current_stats' => $stats
            ]);
            Cache::put($alertKey, true, $now->endOfHour());
        }
    }
}