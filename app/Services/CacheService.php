<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CacheService
{
    // Cache TTL constants (in seconds)
    public const TTL_STATISTICS = 300;      // 5 minutes
    public const TTL_LIVE_DATA = 30;        // 30 seconds
    public const TTL_MATCH_DATA = 180;      // 3 minutes
    public const TTL_PREDICTIONS = 900;     // 15 minutes
    public const TTL_TEAM_STATS = 3600;     // 1 hour
    public const TTL_LEAGUE_DATA = 1800;    // 30 minutes

    /**
     * Get or set cached statistics data
     */
    public function rememberStatistics(string $type, callable $callback, int $ttl = self::TTL_STATISTICS)
    {
        $key = "statistics:{$type}:" . date('Y-m-d-H');
        
        try {
            return Cache::remember($key, $ttl, function () use ($callback, $key) {
                Log::info("Cache miss for key: {$key}");
                return $callback();
            });
        } catch (\Exception $e) {
            Log::warning("Cache error for key {$key}: " . $e->getMessage());
            return $callback();
        }
    }

    /**
     * Get or set cached live data
     */
    public function rememberLiveData(string $identifier, callable $callback): mixed
    {
        $key = "live_data:{$identifier}:" . floor(time() / self::TTL_LIVE_DATA);
        
        try {
            return Cache::remember($key, self::TTL_LIVE_DATA, function () use ($callback, $key) {
                Log::debug("Cache miss for live data: {$key}");
                return $callback();
            });
        } catch (\Exception $e) {
            Log::warning("Cache error for live data {$key}: " . $e->getMessage());
            return $callback();
        }
    }

    /**
     * Get or set cached predictions
     */
    public function rememberPredictions(int $matchId, callable $callback): mixed
    {
        $key = "predictions:match:{$matchId}:" . date('Y-m-d');
        
        try {
            return Cache::remember($key, self::TTL_PREDICTIONS, function () use ($callback, $key) {
                Log::info("Cache miss for predictions: {$key}");
                return $callback();
            });
        } catch (\Exception $e) {
            Log::warning("Cache error for predictions {$key}: " . $e->getMessage());
            return $callback();
        }
    }

    /**
     * Get or set cached team statistics
     */
    public function rememberTeamStats(int $teamId, string $season, callable $callback): mixed
    {
        $key = "team_stats:{$teamId}:{$season}";
        
        try {
            return Cache::remember($key, self::TTL_TEAM_STATS, function () use ($callback, $key) {
                Log::info("Cache miss for team stats: {$key}");
                return $callback();
            });
        } catch (\Exception $e) {
            Log::warning("Cache error for team stats {$key}: " . $e->getMessage());
            return $callback();
        }
    }

    /**
     * Get or set cached league data
     */
    public function rememberLeagueData(string $league, string $type, callable $callback): mixed
    {
        $key = "league_data:{$league}:{$type}:" . date('Y-m-d-H');
        
        try {
            return Cache::remember($key, self::TTL_LEAGUE_DATA, function () use ($callback, $key) {
                Log::info("Cache miss for league data: {$key}");
                return $callback();
            });
        } catch (\Exception $e) {
            Log::warning("Cache error for league data {$key}: " . $e->getMessage());
            return $callback();
        }
    }

    /**
     * Invalidate statistics cache
     */
    public function invalidateStatistics(array $types = []): void
    {
        if (empty($types)) {
            $types = ['accuracy', 'monthly', 'league', 'detail'];
        }

        foreach ($types as $type) {
            $pattern = "statistics:{$type}:*";
            $this->invalidateByPattern($pattern);
        }

        Log::info("Invalidated statistics cache for types: " . implode(', ', $types));
    }

    /**
     * Invalidate cache by pattern
     */
    public function invalidateByPattern(string $pattern): void
    {
        try {
            // This works with Redis
            if (Cache::getStore() instanceof \Illuminate\Cache\RedisStore) {
                $keys = Cache::getStore()->getRedis()->keys($pattern);
                if (!empty($keys)) {
                    Cache::getStore()->getRedis()->del($keys);
                }
            } else {
                // Fallback for other cache stores - less efficient
                Log::warning("Cache pattern invalidation not optimal for current store");
            }
        } catch (\Exception $e) {
            Log::warning("Cache invalidation error for pattern {$pattern}: " . $e->getMessage());
        }
    }

    /**
     * Warm up cache for critical data
     */
    public function warmupCache(): void
    {
        Log::info("Starting cache warmup");

        try {
            // Warm up statistics
            $this->rememberStatistics('accuracy', function () {
                return app(\App\Services\SimpleAccuracyService::class)->getCurrentAccuracy();
            });

            // Warm up live data structure
            $this->rememberLiveData('matches', function () {
                return app(\App\Http\Controllers\HomeController::class)->getLiveData()->getData(true);
            });

            Log::info("Cache warmup completed successfully");
        } catch (\Exception $e) {
            Log::error("Cache warmup failed: " . $e->getMessage());
        }
    }

    /**
     * Get cache health statistics
     */
    public function getCacheHealth(): array
    {
        try {
            $stats = [
                'status' => 'healthy',
                'store' => class_basename(Cache::getStore()),
                'keys_checked' => 0,
                'memory_usage' => null
            ];

            // Test basic cache operations
            $testKey = 'cache_health_test:' . time();
            Cache::put($testKey, 'test_value', 60);
            $retrieved = Cache::get($testKey);
            Cache::forget($testKey);

            if ($retrieved !== 'test_value') {
                $stats['status'] = 'unhealthy';
                $stats['error'] = 'Cache read/write test failed';
            }

            // Get Redis specific stats if available
            if (Cache::getStore() instanceof \Illuminate\Cache\RedisStore) {
                $redis = Cache::getStore()->getRedis();
                $info = $redis->info();
                $stats['memory_usage'] = $info['used_memory_human'] ?? null;
                $stats['connected_clients'] = $info['connected_clients'] ?? null;
            }

            return $stats;
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
                'store' => 'unknown'
            ];
        }
    }

    /**
     * Clear all cache (use with caution)
     */
    public function clearAll(): void
    {
        try {
            Cache::flush();
            Log::warning("All cache cleared - this affects performance");
        } catch (\Exception $e) {
            Log::error("Cache clear failed: " . $e->getMessage());
        }
    }
}