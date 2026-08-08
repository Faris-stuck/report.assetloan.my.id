<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

/**
 * CacheHelper Class
 * 
 * Provides wrapper functions for query caching pattern.
 * Simplifies cache operations with sensible defaults and error handling.
 * 
 * Usage:
 * - CacheHelper::remember($key, $ttl, $callback) - Cache or retrieve value
 * - CacheHelper::get($key) - Get cached value if exists
 * - CacheHelper::invalidate($pattern) - Clear cache by pattern
 * - CacheHelper::invalidateTag($tag) - Clear cache by tag
 * 
 * Example:
 *   $reports = CacheHelper::remember('laporin:report:list:all', 3600, fn() => Report::all());
 *   CacheHelper::invalidateTag('reports');  // Clear all report caches
 */
class CacheHelper
{
    /**
     * Cache query result with TTL
     * 
     * Stores the result of $query callback in cache with specified TTL.
     * If value already cached, returns cached value without executing callback.
     * 
     * @param string $key Cache key to store/retrieve
     * @param int $ttl Time to live in seconds
     * @param callable $query Callback that returns value to cache
     * @return mixed Cached or freshly computed value
     * 
     * @example
     * $reports = CacheHelper::remember('laporin:report:list:all', 3600, 
     *     fn() => Report::with('location')->get()
     * );
     */
    public static function remember(string $key, int $ttl, callable $query): mixed
    {
        return Cache::remember($key, $ttl, $query);
    }

    /**
     * Get cached value if exists
     * 
     * Returns the value from cache if it exists, otherwise returns null.
     * Does not execute any callback.
     * 
     * @param string $key Cache key to retrieve
     * @return mixed|null Cached value or null if not found
     * 
     * @example
     * $reports = CacheHelper::get('laporin:report:list:all');
     * if ($reports) {
     *     return response()->json($reports);
     * }
     */
    public static function get(string $key): mixed
    {
        return Cache::get($key);
    }

    /**
     * Invalidate cache keys matching pattern
     * 
     * Clears all cache keys that contain the pattern.
     * Useful for invalidating multiple related cache entries.
     * 
     * @param string $pattern Pattern to match (e.g., 'laporin:report:*')
     * @return void
     * 
     * @example
     * CacheHelper::invalidate('laporin:report:list:*');  // Clear all report lists
     * CacheHelper::invalidate('laporin:report:detail:*'); // Clear all report details
     */
    public static function invalidate(string $pattern): void
    {
        // Get Redis connection and find matching keys
        $connection = Cache::store('redis')->connection();
        
        // Escape the cache prefix if it exists
        $prefix = config('cache.prefix', '');
        $prefixedPattern = $prefix ? "{$prefix}*{$pattern}*" : "*{$pattern}*";
        
        // Use KEYS command to find matching keys
        $keys = $connection->keys($prefixedPattern);
        
        // Delete each matching key
        foreach ($keys as $key) {
            // Remove prefix if it exists to use with Cache::forget()
            $keyWithoutPrefix = str_replace($prefix, '', $key);
            Cache::forget($keyWithoutPrefix);
        }
    }

    /**
     * Invalidate all cache entries with given tag
     * 
     * Clears all cache entries that were tagged with the provided tag.
     * Only works with cache drivers that support tagging (Redis, Memcached).
     * 
     * @param string $tag Tag to flush
     * @return void
     * 
     * @example
     * CacheHelper::invalidateTag('reports');          // Clear all report-related caches
     * CacheHelper::invalidateTag('locations');        // Clear all location caches
     * 
     * @see https://laravel.com/docs/cache#cache-tags
     */
    public static function invalidateTag(string $tag): void
    {
        Cache::tags($tag)->flush();
    }

    /**
     * Invalidate multiple tags at once
     * 
     * Convenience method to clear caches for multiple tags in one call.
     * 
     * @param array $tags Array of tags to flush
     * @return void
     * 
     * @example
     * CacheHelper::invalidateTags(['reports', 'locations', 'users']);
     */
    public static function invalidateTags(array $tags): void
    {
        foreach ($tags as $tag) {
            static::invalidateTag($tag);
        }
    }

    /**
     * Put value in cache with TTL
     * 
     * Stores a value in cache without checking if it already exists.
     * Useful for explicitly setting cache values.
     * 
     * @param string $key Cache key
     * @param mixed $value Value to store
     * @param int $ttl Time to live in seconds
     * @return void
     * 
     * @example
     * CacheHelper::put('laporin:report:total:count', 42, 3600);
     */
    public static function put(string $key, mixed $value, int $ttl): void
    {
        Cache::put($key, $value, $ttl);
    }

    /**
     * Put value in cache with tags
     * 
     * Stores a value in cache with associated tags for grouped invalidation.
     * 
     * @param string $key Cache key
     * @param mixed $value Value to store
     * @param int $ttl Time to live in seconds
     * @param array $tags Tags to associate with this cache entry
     * @return void
     * 
     * @example
     * CacheHelper::putWithTags('laporin:report:list:all', $reports, 3600, ['reports', 'lists']);
     */
    public static function putWithTags(string $key, mixed $value, int $ttl, array $tags): void
    {
        Cache::tags($tags)->put($key, $value, $ttl);
    }

    /**
     * Check if key exists in cache
     * 
     * @param string $key Cache key to check
     * @return bool True if key exists in cache, false otherwise
     * 
     * @example
     * if (CacheHelper::has('laporin:report:list:all')) {
     *     return CacheHelper::get('laporin:report:list:all');
     * }
     */
    public static function has(string $key): bool
    {
        return Cache::has($key);
    }

    /**
     * Delete a specific cache key
     * 
     * @param string $key Cache key to delete
     * @return bool True if key was deleted, false if not found
     * 
     * @example
     * CacheHelper::forget('laporin:report:list:all');
     */
    public static function forget(string $key): bool
    {
        return Cache::forget($key);
    }

    /**
     * Increment a cached integer value
     * 
     * Atomically increments a numeric cache value.
     * Useful for counters and rate limiting.
     * 
     * @param string $key Cache key
     * @param int $value Amount to increment by (default: 1)
     * @return int New value after increment
     * 
     * @example
     * $count = CacheHelper::increment('laporin:api:requests:rate_limit');
     */
    public static function increment(string $key, int $value = 1): int
    {
        return Cache::increment($key, $value);
    }

    /**
     * Get cache statistics
     * 
     * Returns information about cache usage and Redis connection status.
     * Useful for debugging cache issues.
     * 
     * @return array Statistics array
     * 
     * @example
     * $stats = CacheHelper::stats();
     * // Returns: ['driver' => 'redis', 'prefix' => 'laporin_', ...]
     */
    public static function stats(): array
    {
        return [
            'driver' => config('cache.default'),
            'prefix' => config('cache.prefix'),
            'ttl_config' => config('cache.ttl', []),
            'redis_client' => config('database.redis.client'),
        ];
    }

    /**
     * Flush all cache entries
     * 
     * Clears the entire cache store. Use with caution!
     * 
     * @return bool True on success
     * 
     * @example
     * CacheHelper::flush();  // Clear entire cache
     */
    public static function flush(): bool
    {
        return Cache::flush();
    }
}
