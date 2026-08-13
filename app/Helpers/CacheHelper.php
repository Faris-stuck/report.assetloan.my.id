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
        if (! Cache::has($key)) {
            Cache::put($key, $value, 3600);
            return $value;
        }

        $result = Cache::increment($key, $value);
        if ($result === false || $result === 0) {
            $current = (int) Cache::get($key, 0);
            $new = $current + $value;
            Cache::put($key, $new, 3600);
            return $new;
        }

        return (int) $result;
    }

    /**
     * Menghapus cache berdasarkan pattern menggunakan Redis SCAN.
     *
     * Tidak menggunakan Redis KEYS karena dapat memblokir Redis
     * ketika jumlah key besar.
     */
    public static function invalidate(string $pattern): void
    {
        if (config('cache.default') !== 'redis') {
            if (! str_contains($pattern, '*')) {
                Cache::forget($pattern);
            }

            return;
        }

        $connectionName = (string) config(
            'cache.stores.redis.connection',
            'cache'
        );

        $redis = Redis::connection($connectionName);

        $prefix = (string) config('cache.prefix', '');

        $searchPattern = $prefix.$pattern;

        if (! str_contains($searchPattern, '*')) {
            $searchPattern .= '*';
        }

        $cursor = '0';

        do {
            $result = $redis->scan(
                $cursor,
                [
                    'match' => $searchPattern,
                    'count' => 100,
                ]
            );

            if (
                $result === false
                || ! is_array($result)
                || count($result) < 2
            ) {
                break;
            }

            [$nextCursor, $keys] = $result;

            $cursor = (string) $nextCursor;

            if (is_array($keys) && $keys !== []) {
                foreach ($keys as $key) {
                    $redis->del($key);
                }
            }
        } while ($cursor !== '0');
    }

    public static function invalidateTag(string $tag): void
    {
        if (! Cache::supportsTags()) {
            static::invalidate("laporin:{$tag}:*");
            return;
        }

        Cache::tags([$tag])->flush();
    }

    public static function invalidateTags(array $tags): void
    {
        if (empty($tags)) {
            return;
        }

        if (! Cache::supportsTags()) {
            foreach ($tags as $tag) {
                static::invalidate("laporin:{$tag}:*");
            }
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
        if (empty($tags) || ! Cache::supportsTags()) {
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
     * Menghapus namespace cache aplikasi saja.
     *
     * Tidak menggunakan Cache::flush() agar Redis production
     * tidak ikut dikosongkan secara global.
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
