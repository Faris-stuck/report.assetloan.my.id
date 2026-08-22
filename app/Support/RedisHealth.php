<?php

namespace App\Support;

use Throwable;

class RedisHealth
{
    /**
     * Memo agar satu proses PHP tidak menyondel socket Redis berulang kali.
     */
    private static bool $fallbackApplied = false;

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
        // Dibaca dari config, bukan env(): begitu `php artisan config:cache`
        // dijalankan (dan docker/start.sh selalu menjalankannya) Laravel
        // melewati LoadEnvironmentVariables, sehingga env() diam-diam
        // mengembalikan default 127.0.0.1:6379 dan health check menyondel
        // alamat yang salah.
        $host ??= (string) config('database.redis.'.$connection.'.host', '127.0.0.1');
        $port ??= (int) config('database.redis.'.$connection.'.port', 6379);

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

            /*
             * Redis tidak mengirim banner sambutan seperti SMTP/FTP. Versi
             * sebelumnya langsung fread() dan menunggu banner yang tidak
             * pernah datang, sehingga: (1) setiap request membayar timeout
             * penuh, dan (2) hasilnya selalu string kosong -> Redis yang sehat
             * pun dinyatakan mati. Jadi kirim PING dan baca +PONG, sama
             * seperti yang sudah dilakukan LaporinHealthCommand.
             */
            stream_set_timeout($socket, 0, (int) ($timeout * 1000000));

            $ping = @fwrite($socket, "PING\r\n");
            if ($ping === false || $ping === 0) {
                fclose($socket);

                return false;
            }

            $reply = @fgets($socket, 64);
            $info = stream_get_meta_data($socket);
            fclose($socket);

            if (! empty($info['timed_out'])) {
                return false;
            }

            /*
             * Redis dengan `requirepass` menjawab PING tanpa AUTH dengan
             * -NOAUTH/-ERR. Itu tetap berarti server hidup dan bicara protokol
             * Redis, jadi tetap dihitung tersedia; kredensial diurus oleh klien
             * Redis sebenarnya, bukan oleh probe TCP ini.
             */
            if (! is_string($reply) || $reply === '') {
                return false;
            }

            $reply = strtoupper(trim($reply));

            return str_starts_with($reply, '+PONG')
                || str_starts_with($reply, '-NOAUTH')
                || str_starts_with($reply, '-ERR');
        } catch (Throwable) {
            return false;
        }
    }

    public static function applyGracefulFallback(): void
    {
        /*
         * Urutannya sengaja dibalik dari versi sebelumnya. Dulu isAvailable()
         * dijalankan lebih dulu, lalu hasilnya dibuang begitu tahu env-nya
         * production — jadi setiap request produksi membayar probe TCP untuk
         * keputusan yang sudah pasti "jangan turunkan driver". Sekarang
         * production keluar sebelum menyentuh socket.
         */
        if (app()->environment('production')) {
            return;
        }

        if (self::$fallbackApplied) {
            return;
        }

        self::$fallbackApplied = true;

        if (self::isAvailable()) {
            return;
        }

        logger()?->warning('Redis unavailable; falling back to database session and cache storage.', [
            'host' => config('database.redis.default.host', '127.0.0.1'),
            'port' => config('database.redis.default.port', 6379),
            'session_driver' => config('session.driver'),
            'cache_store' => config('cache.default'),
        ]);

        config()->set('session.driver', 'database');
        config()->set('cache.default', 'database');
    }

    /**
     * Reset the per-process memo. Only needed by tests that toggle the
     * simulated Redis endpoint within a single PHP process.
     */
    public static function forgetFallbackState(): void
    {
        self::$fallbackApplied = false;
    }
}
