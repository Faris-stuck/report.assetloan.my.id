<?php

namespace App\Support;

use Throwable;

class RedisHealth
{
    /**
     * Determine whether the configured Redis endpoint is reachable.
     *
     * This intentionally uses a very short timeout so a dead local Redis
     * socket becomes a fast "unavailable" signal instead of hanging Laravel
     * for the default 30-second PHP execution timeout.
     */
    public static function isAvailable(
        string $connection = 'default',
        ?string $host = null,
        ?int $port = null,
        float $timeout = 0.25
    ): bool {
        $host ??= (string) env('REDIS_HOST', '127.0.0.1');
        $port ??= (int) env('REDIS_PORT', 6379);

        if ($host === '' || $host === 'null' || $port <= 0) {
            return false;
        }

        try {
            $socket = @stream_socket_client(
                'tcp://'.$host.':'.$port,
                $errno,
                $errstr,
                $timeout
            );

            if ($socket === false) {
                return false;
            }

            stream_set_timeout($socket, 0, (int) ($timeout * 1000000));
            $banner = @fread($socket, 1024);
            fclose($socket);

            return is_string($banner) && $banner !== '';
        } catch (Throwable) {
            return false;
        }
    }

    public static function applyGracefulFallback(): void
    {
        if (self::isAvailable()) {
            return;
        }

        if (app()->environment('production')) {
            return;
        }

        logger()?->warning('Redis unavailable; falling back to database session and cache storage.', [
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'port' => env('REDIS_PORT', 6379),
            'session_driver' => config('session.driver'),
            'cache_store' => config('cache.default'),
        ]);

        config()->set('session.driver', 'database');
        config()->set('cache.default', 'database');
    }
}
