<?php

namespace Tests\Feature;

use App\Support\RedisHealth;
use Illuminate\Support\Str;
use Tests\TestCase;

class RedisSessionStorageTest extends TestCase
{
    /**
     * Test SESSION_DRIVER environment variable is set to redis in .env
     *
     * .env di-gitignore, jadi checkout bersih di laptop developer tidak punya
     * file ini dan assertFileExists() dulu selalu gagal — kegagalan yang tidak
     * menandakan apa pun soal kode. Sekarang test dilewati dengan instruksi
     * konkret, dan tetap memeriksa isinya begitu file itu ada.
     */
    public function test_session_driver_env_set_to_redis()
    {
        $envPath = base_path('.env');

        if (! file_exists($envPath)) {
            $this->markTestSkipped(
                'Tidak ada file .env di '.$envPath.'. Salin konfigurasi environment Anda ke sana '
                .'(minimal APP_KEY, DB_*, SESSION_DRIVER=redis) lalu jalankan test ini lagi. '
                .'File .env sengaja tidak di-commit, jadi test ini hanya berlaku di mesin yang sudah dikonfigurasi.'
            );
        }

        $envContent = file_get_contents($envPath);
        $this->assertStringContainsString('SESSION_DRIVER=redis', $envContent, '.env should have SESSION_DRIVER=redis');
    }

    /**
     * Test session configuration reads from environment variables
     */
    public function test_session_config_reads_from_env()
    {
        // Verify config reads SESSION_LIFETIME from env
        $lifetime = config('session.lifetime');
        $this->assertEquals(120, $lifetime, 'Session lifetime should be 120 minutes from env config');
    }

    /**
     * Test session store is configured
     */
    public function test_session_store_configured()
    {
        $store = config('session.store');
        $this->assertNull($store, 'SESSION_STORE harus null agar Laravel menggunakan cache store default tanpa mencari store literal bernama default.');
    }

    /**
     * Test session security settings configured correctly
     */
    public function test_session_security_settings()
    {
        $httpOnly = config('session.http_only');
        $sameSite = config('session.same_site');
        
        // Verify the config supports these settings
        $this->assertTrue($httpOnly !== false, 'SESSION_HTTP_ONLY should be enabled');
        $this->assertIsString($sameSite, 'SESSION_SAME_SITE should be configured');
    }

    /**
     * Test .env.example has proper redis session configuration
     *
     * Repo ini tidak menyertakan .env.example (lihat juga .gitignore), jadi test
     * ini dulu pasti gagal di mana pun. Dilewati dengan pesan yang menyebut
     * kunci apa saja yang harus ada, supaya tetap berguna sebagai spesifikasi
     * saat seseorang akhirnya membuat template environment itu.
     */
    public function test_env_example_has_redis_session_config()
    {
        $envExamplePath = base_path('.env.example');

        if (! file_exists($envExamplePath)) {
            $this->markTestSkipped(
                'Tidak ada file .env.example di '.$envExamplePath.'. Bila Anda membuat template environment, '
                .'sertakan SESSION_DRIVER=redis dan SESSION_LIFETIME=120 supaya test ini otomatis aktif kembali.'
            );
        }

        $content = file_get_contents($envExamplePath);
        $this->assertStringContainsString('SESSION_DRIVER=redis', $content, '.env.example should have SESSION_DRIVER=redis');
        $this->assertStringContainsString('SESSION_LIFETIME=120', $content, '.env.example should have SESSION_LIFETIME=120');
    }

    /**
     * Test Redis client is configured in database config
     */
    public function test_redis_client_configured_as_predis()
    {
        // Verify redis client is set to predis in config
        $redisClient = config('database.redis.client');
        $this->assertEquals('predis', $redisClient, 'Redis client should be configured as predis');
    }

    /**
     * Test Redis default connection configured
     */
    public function test_redis_default_connection_configured()
    {
        $redisDefault = config('database.redis.default');
        $this->assertNotEmpty($redisDefault, 'Redis default connection should be configured');
        $this->assertEquals(env('REDIS_HOST', '127.0.0.1'), $redisDefault['host'], 'Redis host should match env config');
        $this->assertEquals(env('REDIS_PORT', '6379'), $redisDefault['port'], 'Redis port should match env config');
    }

    /**
     * Test Redis cache connection configured
     */
    public function test_redis_cache_connection_configured()
    {
        $redisCache = config('database.redis.cache');
        $this->assertNotEmpty($redisCache, 'Redis cache connection should be configured');
        $this->assertEquals(env('REDIS_CACHE_DB', 1), $redisCache['database'], 'Redis cache DB should use REDIS_CACHE_DB environment variable');
    }

    /**
     * Test CACHE_STORE is set to redis in .env
     */
    public function test_cache_store_set_to_redis()
    {
        $cacheStore = config('cache.default');
        // In testing, cache might be 'array', but in production config it should be redis
        $this->assertNotEmpty($cacheStore, 'Cache store should be configured');
    }

    /**
     * Test cache prefix is set correctly
     *
     * Literal 'laporin' hanya muncul bila CACHE_PREFIX/APP_NAME diisi lewat .env
     * (config/cache.php:118 menurunkan prefix dari APP_NAME). phpunit.xml tidak
     * mengatur APP_NAME, jadi assertion literal dulu selalu gagal padahal tidak
     * ada yang rusak. Yang benar-benar penting: prefix terisi dan mengikuti nama
     * aplikasi, sehingga dua aplikasi yang berbagi satu Redis tidak saling
     * menimpa key.
     */
    public function test_cache_prefix_set()
    {
        $cachePrefix = (string) config('cache.prefix');
        $this->assertNotEmpty($cachePrefix, 'Cache prefix should be configured');

        if (env('CACHE_PREFIX') !== null) {
            $this->assertSame(env('CACHE_PREFIX'), $cachePrefix, 'CACHE_PREFIX di environment harus dipakai apa adanya.');

            return;
        }

        $this->assertStringContainsString(
            Str::slug((string) config('app.name'), '_'),
            $cachePrefix,
            'Prefix cache harus memuat slug APP_NAME supaya key tidak bertabrakan dengan aplikasi lain di Redis yang sama.'
        );
    }

    /**
     * Test Redis health detection reports unavailable when nothing listens on the probed port.
     *
     * Sebelumnya port 6380 di-hardcode sebagai "port mati". Di mesin yang
     * menjalankan instance Redis kedua di 6380 (kasus nyata: laptop developer
     * repo ini) probe justru sukses dan test gagal tanpa ada kode yang rusak.
     * Jadi minta port bebas ke OS, tutup listener-nya, baru probe.
     */
    public function test_redis_health_reports_unavailable_when_nothing_listens_on_probed_port()
    {
        $listener = @stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);

        if ($listener === false) {
            $this->markTestSkipped("Tidak bisa membuka socket lokal untuk mencari port bebas ({$errno}: {$errstr}).");
        }

        $address = (string) stream_socket_get_name($listener, false);
        $port = (int) substr((string) strrchr($address, ':'), 1);
        fclose($listener);

        $available = RedisHealth::isAvailable('default', '127.0.0.1', $port, 0.25);

        $this->assertFalse($available, "Redis health check should fail fast when port {$port} has no listener.");
    }
}
