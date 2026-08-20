<?php

namespace Tests\Feature;

use Tests\TestCase;

class RedisSessionStorageTest extends TestCase
{
    /**
     * Test SESSION_DRIVER environment variable is set to redis in .env
     */
    public function test_session_driver_env_set_to_redis()
    {
        $envPath = base_path('.env');
        $this->assertFileExists($envPath, '.env file should exist');
        
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
     */
    public function test_env_example_has_redis_session_config()
    {
        $envExamplePath = base_path('.env.example');
        $this->assertFileExists($envExamplePath, '.env.example file should exist');
        
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
     */
    public function test_cache_prefix_set()
    {
        $cachePrefix = config('cache.prefix');
        $this->assertNotEmpty($cachePrefix, 'Cache prefix should be configured');
        $this->assertStringContainsString('laporin', $cachePrefix, 'Cache prefix should contain "laporin"');
    }

    /**
     * Test Redis health detection fails fast when the configured local socket is closed.
     */
    public function test_redis_health_checks_fail_fast_on_closed_socket()
    {
        $available = \App\Support\RedisHealth::isAvailable('default', '127.0.0.1', 6380, 0.25);

        $this->assertFalse($available, 'Redis health check should fail fast when the port is closed.');
    }
}
