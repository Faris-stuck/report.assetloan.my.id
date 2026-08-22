<?php

namespace Tests\Feature;

use Tests\TestCase;

class QueryCachingPerformanceTest extends TestCase
{
    /**
     * Test cache TTL configuration for different query types
     */
    public function test_cache_ttl_configured_for_all_types()
    {
        $ttlConfig = config('cache.ttl');
        
        $this->assertNotEmpty($ttlConfig, 'Cache TTL configuration should not be empty');
        
        // Verify common query types have TTL configured
        $expectedTypes = [
            'damage_categories',
            'violation_types',
            'user_profile',
            'report_list',
            'report_detail',
            'rate_limit',
        ];
        
        foreach ($expectedTypes as $type) {
            $this->assertArrayHasKey($type, $ttlConfig, "TTL should be configured for {$type}");
            $this->assertIsInt($ttlConfig[$type], "TTL for {$type} should be integer");
            $this->assertGreaterThan(0, $ttlConfig[$type], "TTL for {$type} should be positive");
        }
    }

    /**
     * Test master data has longer TTL (24 hours)
     */
    public function test_master_data_has_longer_ttl()
    {
        $ttl = config('cache.ttl');
        
        // Master data should be 24 hours (86400 seconds)
        $this->assertEquals(86400, $ttl['damage_categories'], 'Damage categories should have 24-hour TTL');
        $this->assertEquals(86400, $ttl['violation_types'], 'Violation types should have 24-hour TTL');
    }

    /**
     * Test user data has moderate TTL (30 min to 1 hour)
     */
    public function test_user_data_has_moderate_ttl()
    {
        $ttl = config('cache.ttl');
        
        $this->assertEquals(1800, $ttl['user_profile'], 'User profile should have 30-minute TTL');
        $this->assertEquals(3600, $ttl['user_list'], 'User list should have 1-hour TTL');
        $this->assertEquals(3600, $ttl['user_by_role'], 'User by role should have 1-hour TTL');
    }

    /**
     * Test report data has moderate TTL (30 min to 1 hour)
     */
    public function test_report_data_has_moderate_ttl()
    {
        $ttl = config('cache.ttl');
        
        $this->assertEquals(3600, $ttl['report_list'], 'Report list should have 1-hour TTL');
        $this->assertEquals(1800, $ttl['report_detail'], 'Report detail should have 30-minute TTL');
        $this->assertEquals(1800, $ttl['report_statistics'], 'Report statistics should have 30-minute TTL');
    }

    /**
     * Test rate limiting has short TTL (1 minute)
     */
    public function test_rate_limiting_has_short_ttl()
    {
        $ttl = config('cache.ttl');
        
        $this->assertEquals(60, $ttl['rate_limit'], 'Rate limit should have 1-minute TTL');
        $this->assertEquals(300, $ttl['api_request'], 'API request should have 5-minute TTL');
    }

    /**
     * Test session TTL matches SESSION_LIFETIME config
     */
    public function test_session_ttl_matches_session_lifetime()
    {
        $sessionLifetime = config('session.lifetime') * 60; // Convert minutes to seconds
        $cachedSessionTtl = config('cache.ttl.session');
        
        // Cache TTL for session should be in seconds and match session lifetime
        $this->assertEquals($sessionLifetime, $cachedSessionTtl, 'Cache TTL for session should match SESSION_LIFETIME');
    }

    /**
     * Test cache prefix is configured
     */
    public function test_cache_prefix_configured()
    {
        $prefix = config('cache.prefix');
        $this->assertNotEmpty($prefix, 'Cache prefix should be configured');
        $this->assertStringContainsString('laporin', $prefix, 'Cache prefix should contain "laporin"');
    }

    /**
     * Test Redis is default cache store
     */
    public function test_redis_configured_as_cache_store()
    {
        // Check Redis cache connection is configured
        $redisCache = config('database.redis.cache');
        $this->assertNotEmpty($redisCache, 'Redis cache connection should be configured');
        
        // Verify cache driver supports Redis
        $cacheStores = config('cache.stores');
        $this->assertArrayHasKey('redis', $cacheStores, 'Redis should be available cache store');
    }

    /**
     * Test cache stores configuration is complete
     */
    public function test_cache_stores_configured()
    {
        $stores = config('cache.stores');
        
        $expectedStores = ['array', 'redis', 'database', 'file'];
        foreach ($expectedStores as $store) {
            $this->assertArrayHasKey($store, $stores, "{$store} cache store should be configured");
        }
    }

    /**
     * Test individual TTL values are reasonable
     */
    public function test_ttl_values_are_reasonable()
    {
        $ttl = config('cache.ttl');
        
        foreach ($ttl as $type => $seconds) {
            // TTL should be between 1 second and 7 days
            $this->assertGreaterThanOrEqual(1, $seconds, "{$type} TTL should be at least 1 second");
            $this->assertLessThanOrEqual(604800, $seconds, "{$type} TTL should not exceed 7 days");
        }
    }

    /**
     * Test short-lived queries have short TTL
     */
    public function test_short_lived_queries_have_short_ttl()
    {
        $ttl = config('cache.ttl');
        
        // Short-lived queries should have TTL < 5 minutes
        $this->assertLessThan(300, $ttl['rate_limit'], 'Rate limit TTL should be < 5 minutes');
    }

    /**
     * Test list and detail queries have similar TTL ranges
     */
    public function test_list_and_detail_queries_consistent_ttl()
    {
        $ttl = config('cache.ttl');
        
        // Report list should be slightly longer than report detail
        $this->assertGreaterThanOrEqual($ttl['report_detail'], $ttl['report_list'], 
            'Report list TTL should be >= report detail TTL');
        
        // Both should be in reasonable range (30 min - 1 hour for reports)
        $this->assertLessThanOrEqual(3600, $ttl['report_list'], 'Report list TTL should be <= 1 hour');
        $this->assertLessThanOrEqual(3600, $ttl['report_detail'], 'Report detail TTL should be <= 1 hour');
    }
}
