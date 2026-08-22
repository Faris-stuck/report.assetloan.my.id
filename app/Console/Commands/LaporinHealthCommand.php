<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

class LaporinHealthCommand extends Command
{
    protected $signature = 'laporin:health
        {--mode=auto : Mode pemeriksaan: auto, local, atau production}';

    protected $description = 'Memeriksa konfigurasi dan koneksi penting LAPORIN tanpa menampilkan secret.';

    private int $failures = 0;

    public function handle(): int
    {
        $mode = strtolower((string) $this->option('mode'));

        if (! in_array($mode, ['auto', 'local', 'production'], true)) {
            $this->error('Mode harus auto, local, atau production.');

            return self::FAILURE;
        }

        if ($mode === 'auto') {
            $mode = app()->environment('production')
                ? 'production'
                : 'local';
        }

        $this->newLine();
        $this->info('============================================================');
        $this->info(' LAPORIN SYSTEM HEALTH CHECK');
        $this->info('============================================================');
        $this->line('Mode pemeriksaan : '.$mode);
        $this->line('Laravel env       : '.app()->environment());
        $this->newLine();

        $env = $this->readEnvFile();

        $this->checkDuplicateEnv($env['duplicates']);
        $this->checkApp($mode);
        $this->checkDatabaseConfig($mode);
        $this->checkRedisConfig($mode);
        $this->checkServices();
        $this->checkTrustedProxies($mode, $env['values']);

        $this->newLine();
        $this->info('------------------------------------------------------------');
        $this->info(' CONNECTIVITY');
        $this->info('------------------------------------------------------------');

        $this->checkDatabaseConnection();
        $this->checkRedisConnection('default');
        $this->checkRedisConnection('cache');

        $this->newLine();
        $this->info('============================================================');

        if ($this->failures === 0) {
            $this->info(' SYSTEM READY - seluruh pemeriksaan utama lolos.');
            $this->info('============================================================');

            return self::SUCCESS;
        }

        $this->error(
            " SYSTEM NOT READY - ditemukan {$this->failures} masalah."
        );
        $this->info('============================================================');

        return self::FAILURE;
    }

    private function checkApp(string $mode): void
    {
        $this->section('APPLICATION');

        $this->assert(
            app()->environment() === $mode,
            'APP_ENV',
            $mode
        );

        if ($mode === 'production') {
            $this->assert(
                config('app.debug') === false,
                'APP_DEBUG',
                'false'
            );
        }

        $url = (string) config('app.url');

        $validUrl =
            $url !== ''
            && ! str_contains($url, '](')
            && filter_var($url, FILTER_VALIDATE_URL) !== false
            && in_array(
                strtolower((string) parse_url($url, PHP_URL_SCHEME)),
                ['http', 'https'],
                true
            );

        $this->assert(
            $validUrl,
            'APP_URL',
            'URL valid'
        );
    }

    private function checkDatabaseConfig(string $mode): void
    {
        $this->section('DATABASE');

        $this->assert(
            config('database.default') === 'mysql',
            'DB_CONNECTION',
            'mysql'
        );

        $host = (string) config('database.connections.mysql.host');
        $port = (string) config('database.connections.mysql.port');
        $database = (string) config('database.connections.mysql.database');

        $this->assert(
            $host !== '',
            'DB_HOST',
            'terisi'
        );

        $this->assert(
            $database !== '',
            'DB_DATABASE',
            'terisi'
        );

        if ($mode === 'local') {
            $this->assert(
                $host === '127.0.0.1',
                'DB_HOST local',
                '127.0.0.1'
            );

            $this->assert(
                $port === '13306',
                'DB_PORT local tunnel',
                '13306'
            );
        }

        if ($mode === 'production') {
            $this->assert(
                $port !== '13306',
                'DB_PORT production',
                'bukan port SSH tunnel 13306'
            );
        }
    }

    private function checkRedisConfig(string $mode): void
    {
        $this->section('REDIS');

        $redisClient = (string) config('database.redis.client');

        $this->assert(
            in_array(
                $redisClient,
                ['predis', 'phpredis'],
                true
            ),
            'REDIS_CLIENT',
            'predis atau phpredis'
        );

        $host = (string) config('database.redis.default.host');
        $port = (string) config('database.redis.default.port');

        $this->assert(
            $host !== '',
            'REDIS_HOST',
            'terisi'
        );

        if ($mode === 'local') {
            $this->assert(
                $host === '127.0.0.1',
                'REDIS_HOST local',
                '127.0.0.1'
            );

            $this->assert(
                $port === '6380',
                'REDIS_PORT local tunnel',
                '6380'
            );
        }

        if ($mode === 'production') {
            $this->assert(
                $port !== '6380',
                'REDIS_PORT production',
                'bukan port SSH tunnel 6380'
            );
        }
    }

    private function checkServices(): void
    {
        $this->section('SERVICES');

        /*
         * Nilai yang diterima harus mengikuti config, bukan sebaliknya.
         * Sebelumnya perintah ini menuntut session.driver dan cache.default
         * tepat 'redis', padahal config/session.php mendefault ke 'database'
         * dan config/cache.php sengaja memakai rantai 'failover'
         * (redis -> database). Akibatnya `php artisan laporin:health`
         * melaporkan "SYSTEM NOT READY" pada konfigurasi yang justru benar,
         * dan sinyalnya jadi tidak bisa dipercaya.
         */
        $this->assertOneOf(
            config('session.driver'),
            ['redis', 'database'],
            'SESSION_DRIVER'
        );

        $this->assertOneOf(
            config('cache.default'),
            ['failover', 'redis', 'database'],
            'CACHE_STORE'
        );

        $this->assertOneOf(
            config('queue.default'),
            ['database', 'redis'],
            'QUEUE_CONNECTION'
        );

        $this->assertOneOf(
            config('mail.default'),
            ['smtp', 'resend', 'log'],
            'MAIL_MAILER'
        );

        $mailFrom = (string) config('mail.from.address');

        $this->assert(
            filter_var($mailFrom, FILTER_VALIDATE_EMAIL) !== false,
            'MAIL_FROM_ADDRESS',
            'email valid'
        );
    }

    private function checkTrustedProxies(
        string $mode,
        array $envValues
    ): void {
        $this->section('TRUSTED PROXIES');

        $trusted = trim(
            (string) ($envValues['TRUSTED_PROXIES'] ?? '')
        );

        if ($mode === 'local') {
            $this->assert(
                $trusted === '',
                'TRUSTED_PROXIES local',
                'kosong'
            );

            return;
        }

        $this->assert(
            $trusted !== '',
            'TRUSTED_PROXIES production',
            'harus dikonfigurasi'
        );

        $this->assert(
            $trusted !== '*',
            'TRUSTED_PROXIES production',
            'tidak boleh wildcard *'
        );
    }

    private function checkDatabaseConnection(): void
    {
        try {
            DB::select('SELECT 1');

            $this->ok('Database connection');
        } catch (Throwable $e) {
            $this->recordFailure(
                'Database connection',
                $this->safeException($e)
            );
        }
    }

    private function checkRedisConnection(string $connection): void
    {
        try {
            $result = Redis::connection($connection)->ping();

            if ($result === true || strtoupper((string) $result) === 'PONG') {
                $this->ok("Redis {$connection} connection");

                return;
            }

            $this->recordFailure(
                "Redis {$connection} connection",
                'PING tidak menghasilkan respons yang valid'
            );
        } catch (Throwable $e) {
            $this->recordFailure(
                "Redis {$connection} connection",
                $this->safeException($e)
            );
        }
    }

    private function readEnvFile(): array
    {
        $path = base_path('.env');

        if (! is_file($path)) {
            $this->recordFailure(
                '.env',
                'file tidak ditemukan'
            );

            return [
                'values' => [],
                'duplicates' => [],
            ];
        }

        $values = [];
        $counts = [];

        foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            $line = trim($line);

            if (
                $line === ''
                || str_starts_with($line, '#')
                || ! str_contains($line, '=')
            ) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);

            $key = trim($key);

            if (! preg_match('/^[A-Z][A-Z0-9_]*$/', $key)) {
                continue;
            }

            $counts[$key] = ($counts[$key] ?? 0) + 1;
            $values[$key] = trim($value);
        }

        $duplicates = array_keys(
            array_filter(
                $counts,
                static fn (int $count): bool => $count > 1
            )
        );

        return compact('values', 'duplicates');
    }

    private function checkDuplicateEnv(array $duplicates): void
    {
        $this->section('ENV FILE');

        if ($duplicates === []) {
            $this->ok('Tidak ada environment variable duplikat');

            return;
        }

        foreach ($duplicates as $key) {
            $this->recordFailure(
                'Duplicate env',
                $key
            );
        }
    }

    private function section(string $title): void
    {
        $this->newLine();
        $this->info('------------------------------------------------------------');
        $this->info(' '.$title);
        $this->info('------------------------------------------------------------');
    }

    private function assert(
        bool $condition,
        string $name,
        string $expected
    ): void {
        if ($condition) {
            $this->ok($name);

            return;
        }

        $this->recordFailure(
            $name,
            'expected: '.$expected
        );
    }

    /**
     * Terima salah satu nilai yang sah, dan selalu tampilkan nilai aktifnya
     * supaya output perintah ini bisa dipakai untuk diagnosis, bukan hanya
     * lolos/gagal.
     *
     * @param  list<string>  $allowed
     */
    private function assertOneOf(
        mixed $actual,
        array $allowed,
        string $name
    ): void {
        $actual = is_scalar($actual) ? (string) $actual : '';

        if (in_array($actual, $allowed, true)) {
            $this->ok($name.' = '.$actual);

            return;
        }

        $this->recordFailure(
            $name,
            'nilai "'.($actual === '' ? '(kosong)' : $actual).'", expected salah satu dari: '.implode(', ', $allowed)
        );
    }

    private function ok(string $message): void
    {
        $this->line("<info>[OK]</info> {$message}");
    }

    private function recordFailure(
        string $message,
        string $reason
    ): void {
        $this->failures++;

        $this->line(
            "<error>[FAIL]</error> {$message} - {$reason}"
        );
    }

    private function safeException(Throwable $e): string
    {
        return class_basename($e);
    }
}
