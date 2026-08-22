<?php

namespace Tests\Feature;

use Tests\TestCase;

class SessionLifecycleTest extends TestCase
{
    /**
     * Test session lifetime configuration is set to 120 minutes
     */
    public function test_session_lifetime_120_minutes()
    {
        $lifetime = config('session.lifetime');
        $this->assertEquals(120, $lifetime, 'Session lifetime should be 120 minutes for auto-expiration');
    }

    /**
     * Test session driver is redis in production config
     *
     * .env di-gitignore. Sebelumnya file_get_contents() dipanggil tanpa
     * penjagaan, jadi pada checkout bersih test ini meledak dengan ErrorException
     * "Failed to open stream" — bukan kegagalan yang mengatakan apa pun tentang
     * kode. Lewati dengan instruksi konkret, dan tetap periksa isinya bila ada.
     */
    public function test_session_driver_is_redis_in_config()
    {
        $envPath = base_path('.env');

        if (! file_exists($envPath)) {
            $this->markTestSkipped(
                'Tidak ada file .env di '.$envPath.'. Buat file itu dan isi SESSION_DRIVER=redis '
                .'bila ingin memverifikasi konfigurasi deployment lewat test ini.'
            );
        }

        $envContent = file_get_contents($envPath);
        $this->assertStringContainsString('SESSION_DRIVER=redis', $envContent, 'Production config should use redis driver');
    }

    /**
     * Test session store is default which uses the session driver
     */
    public function test_session_store_is_default()
    {
        $store = config('session.store');
        $this->assertNull($store, 'SESSION_STORE harus null agar Laravel menggunakan default Redis/cache store yang dikonfigurasi.');
    }

    /**
     * Test session security attributes are configured
     *
     * $secure sengaja tidak dipaksa true: di test/lokal aplikasi berjalan di
     * HTTP, dan config/session.php menurunkannya dari SESSION_SECURE_COOKIE.
     * Yang bisa dan harus dijamin di mana pun: cookie tidak bisa dibaca
     * JavaScript (http_only) dan punya kebijakan SameSite yang eksplisit.
     * Sebelumnya $secure dan $sameSite hanya diambil lalu tidak pernah di-assert
     * sehingga test ini bicara soal keamanan tanpa benar-benar mengujinya.
     */
    public function test_session_security_attributes_configured()
    {
        $secure = config('session.secure');
        $httpOnly = config('session.http_only');
        $sameSite = config('session.same_site');

        $this->assertTrue($httpOnly !== false, 'HTTP_ONLY should be true for security');
        $this->assertContains(
            $sameSite,
            ['lax', 'strict', 'none'],
            'SESSION_SAME_SITE harus salah satu dari lax/strict/none supaya perilaku cookie lintas situs pasti.'
        );
        $this->assertTrue(
            $secure === null || is_bool($secure),
            'SESSION_SECURE_COOKIE harus bool atau null (null = ikuti skema request), bukan string.'
        );
    }

    /**
     * Test session expire on close is false (manual timeout)
     */
    public function test_session_does_not_expire_on_close()
    {
        $expireOnClose = config('session.expire_on_close');
        $this->assertFalse($expireOnClose, 'Session should NOT expire on browser close, use timer');
    }

    /**
     * Test session cookie configuration
     */
    public function test_session_cookie_configured()
    {
        $cookie = config('session.cookie');
        $this->assertNotEmpty($cookie, 'Session cookie name should be configured');
        $this->assertStringContainsString('session', strtolower($cookie), 'Cookie should contain session');
    }

    /**
     * Test session cookie path is root
     */
    public function test_session_cookie_path_is_root()
    {
        $path = config('session.path');
        $this->assertEquals('/', $path, 'Session cookie path should be root (/)');
    }

    /**
     * Test session encryption is disabled (payloads are encrypted by framework)
     */
    public function test_session_encryption_disabled()
    {
        $encrypt = config('session.encrypt');
        $this->assertFalse($encrypt, 'Session encryption should be disabled (handled by framework)');
    }

    /**
     * Test Redis database selection for sessions
     */
    public function test_redis_default_database_for_sessions()
    {
        $redisDefault = config('database.redis.default');
        $this->assertNotEmpty($redisDefault, 'Redis default connection should be configured for sessions');
        $this->assertEquals(env('REDIS_DB', 0), $redisDefault['database'], 'Session driver should use REDIS_DB');
    }

    /**
     * Test separate Redis database for cache
     */
    public function test_redis_separate_database_for_cache()
    {
        $redisCache = config('database.redis.cache');
        $defaultDb = config('database.redis.default')['database'];
        $cacheDb = $redisCache['database'];
        
        // Cache should use different database to avoid conflicts
        $this->assertNotEmpty($redisCache, 'Redis cache connection should be configured');
        $this->assertNotEquals($defaultDb, $cacheDb, 'Session and cache should use different Redis databases');
    }

    /**
     * Test session table configuration for fallback
     */
    public function test_session_table_name_configured()
    {
        $table = config('session.table');
        $this->assertEquals('sessions', $table, 'Session table name should be "sessions" for database fallback');
    }

    /**
     * Test Redis connection parameters configured
     */
    public function test_redis_connection_parameters()
    {
        $redisDefault = config('database.redis.default');
        $this->assertEquals(env('REDIS_HOST', '127.0.0.1'), $redisDefault['host'], 'Redis host from env');
        $this->assertEquals(env('REDIS_PORT', 6379), $redisDefault['port'], 'Redis port from env');
        $this->assertEquals(env('REDIS_PASSWORD'), $redisDefault['password'], 'Redis password from env');
    }

    /**
     * Test Predis client is configured for Redis connection
     */
    public function test_predis_client_configured()
    {
        $client = config('database.redis.client');
        $this->assertEquals('predis', $client, 'Should use Predis client for Redis connection');
    }

    /**
     * Test session timeout configuration complete
     */
    public function test_session_timeout_configuration_complete()
    {
        // Session should timeout after 120 minutes of inactivity
        $lifetime = config('session.lifetime');
        
        $this->assertEquals(120, $lifetime, 'Session timeout should be 120 minutes');
    }
}
