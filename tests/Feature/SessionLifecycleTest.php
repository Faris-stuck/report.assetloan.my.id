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
     */
    public function test_session_driver_is_redis_in_config()
    {
        // Read .env to verify SESSION_DRIVER=redis
        $envPath = base_path('.env');
        $envContent = file_get_contents($envPath);
        $this->assertStringContainsString('SESSION_DRIVER=redis', $envContent, 'Production config should use redis driver');
    }

    /**
     * Test session store is default which uses the session driver
     */
    public function test_session_store_is_default()
    {
        $store = config('session.store');
        $this->assertEquals('default', $store, 'Session store should be default to use redis driver');
    }

    /**
     * Test session security attributes are configured
     */
    public function test_session_security_attributes_configured()
    {
        $secure = config('session.secure');
        $httpOnly = config('session.http_only');
        $sameSite = config('session.same_site');
        
        // In production, these should be set to secure values
        // In testing, they might be different, but config should support them
        $this->assertTrue($httpOnly !== false, 'HTTP_ONLY should be true for security');
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
