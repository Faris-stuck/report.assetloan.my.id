<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class CacheHelper
{
    public static function remember(
        string $key,
        int $ttl,
        callable $callback
    ): mixed {
        return Cache::remember($key, $ttl, $callback);
    }

    public static function get(string $key): mixed
    {
        return Cache::get($key);
    }

    public static function put(
        string $key,
        mixed $value,
        int $ttl
    ): void {
        Cache::put($key, $value, $ttl);
    }

    public static function has(string $key): bool
    {
        return Cache::has($key);
    }

    public static function forget(string $key): bool
    {
        return Cache::forget($key);
    }

    public static function increment(
        string $key,
        int $value = 1
    ): int {
        return Cache::increment($key, $value);
    }

    /**
     * Menghapus cache berdasarkan pattern menggunakan Redis SCAN.
     *
     * SCAN dipakai menggantikan KEYS agar Redis production
     * tidak mengalami blocking ketika jumlah key besar.
     */
    public static function invalidate(string $pattern): void
    {
        if (config('cache.default') !== 'redis') {
            if (! str_contains($pattern, '*')) {
                Cache::forget($pattern);
            }

            return;
        }

        $connectionName = config(
            'cache.stores.redis.connection',
            'cache'
        );

        $redis = Redis::connection($connectionName);

        $prefix = config('cache.prefix', '');

        $searchPattern = $prefix
            ? $prefix.$pattern
            : $pattern;

        if (! str_contains($searchPattern, '*')) {
            $searchPattern .= '*';
        }

        $cursor = null;

        do {
            $result = $redis->scan(
                $cursor,
                [
                    'match' => $searchPattern,
                    'count' => 100,
                ]
            );

            if ($result === false) {
                break;
            }

            foreach ($result as $key) {
                $redis->del($key);
            }
        } while ($cursor !== 0 && $cursor !== '0');
    }

    /**
     * Flush cache berdasarkan tag.
     *
     * Redis mendukung cache tags Laravel.
     */
    public static function invalidateTag(string $tag): void
    {
        Cache::tags([$tag])->flush();
    }

    public static function invalidateTags(array $tags): void
    {
        if (empty($tags)) {
            return;
        }

        Cache::tags($tags)->flush();
    }

    public static function putWithTags(
        string $key,
        mixed $value,
        int $ttl,
        array $tags
    ): void {
        if (empty($tags)) {
            Cache::put($key, $value, $ttl);

            return;
        }

        Cache::tags($tags)->put(
            $key,
            $value,
            $ttl
        );
    }

    public static function stats(): array
    {
        return [
            'driver' => config('cache.default'),
            'prefix' => config('cache.prefix'),
            'redis_client' => config('database.redis.client'),
            'redis_connection' => config(
                'cache.stores.redis.connection',
                'cache'
            ),
            'ttl_config' => config('cache.ttl', []),
        ];
    }

    /**
     * Menghapus satu namespace cache aplikasi saja.
     *
     * Method ini sengaja tidak memakai Cache::flush()
     * karena cache terhubung ke Redis production.
     */
    public static function flush(): bool
    {
        if (config('cache.default') !== 'redis') {
            return false;
        }

        $prefix = config('cache.prefix');

        if (! $prefix) {
            return false;
        }

        static::invalidate('*');

        return true;
    }
}