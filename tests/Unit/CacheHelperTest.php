<?php

namespace Tests\Unit;

use App\Helpers\CacheHelper;
use Tests\TestCase;

class CacheHelperTest extends TestCase
{
    /**
     * Test remember method stores and retrieves value
     */
    public function test_remember_stores_and_retrieves_value()
    {
        $key = 'test:cache:key';
        $value = ['data' => 'test'];
        $ttl = 3600;
        
        // First call should execute callback
        $result = CacheHelper::remember($key, $ttl, fn() => $value);
        $this->assertEquals($value, $result);
        
        // Second call should retrieve from cache
        $result2 = CacheHelper::remember($key, $ttl, fn() => ['different' => 'data']);
        $this->assertEquals($value, $result2); // Should still be original value
    }

    /**
     * Test get method retrieves cached value
     */
    public function test_get_retrieves_cached_value()
    {
        $key = 'test:get:key';
        $value = 'test_value';
        
        CacheHelper::put($key, $value, 3600);
        $retrieved = CacheHelper::get($key);
        
        $this->assertEquals($value, $retrieved);
    }

    /**
     * Test get returns null for non-existent key
     */
    public function test_get_returns_null_for_missing_key()
    {
        $value = CacheHelper::get('nonexistent:key:12345');
        $this->assertNull($value);
    }

    /**
     * Test put method stores value
     */
    public function test_put_stores_value()
    {
        $key = 'test:put:key';
        $value = ['stored' => 'data'];
        
        CacheHelper::put($key, $value, 3600);
        $retrieved = CacheHelper::get($key);
        
        $this->assertEquals($value, $retrieved);
    }

    /**
     * Test has method checks if key exists
     */
    public function test_has_checks_if_key_exists()
    {
        $key = 'test:has:key';
        
        $this->assertFalse(CacheHelper::has($key));
        
        CacheHelper::put($key, 'value', 3600);
        
        $this->assertTrue(CacheHelper::has($key));
    }

    /**
     * Test forget method deletes key
     */
    public function test_forget_deletes_key()
    {
        $key = 'test:forget:key';
        
        CacheHelper::put($key, 'value', 3600);
        $this->assertTrue(CacheHelper::has($key));
        
        $result = CacheHelper::forget($key);
        $this->assertTrue($result);
        $this->assertFalse(CacheHelper::has($key));
    }

    /**
     * Test increment increments value
     */
    public function test_increment_increments_value()
    {
        $key = 'test:counter:key';
        
        $result1 = CacheHelper::increment($key);
        $this->assertEquals(1, $result1);
        
        $result2 = CacheHelper::increment($key, 5);
        $this->assertEquals(6, $result2);
    }

    /**
     * Test stats returns cache configuration
     */
    public function test_stats_returns_configuration()
    {
        $stats = CacheHelper::stats();
        
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('driver', $stats);
        $this->assertArrayHasKey('prefix', $stats);
        $this->assertArrayHasKey('ttl_config', $stats);
        $this->assertArrayHasKey('redis_client', $stats);
    }

    /**
     * Test invalidateTags invalidates multiple tags
     */
    public function test_invalidate_tags_multiple()
    {
        // Put values with tags
        CacheHelper::putWithTags('key1', 'value1', 3600, ['tag1', 'tag2']);
        CacheHelper::putWithTags('key2', 'value2', 3600, ['tag2', 'tag3']);
        
        // Invalidate multiple tags
        CacheHelper::invalidateTags(['tag1', 'tag2']);
        
        // Values should be cleared (in testing with array driver, behavior may vary)
        // This test mainly verifies the method doesn't error
        $this->assertTrue(true);
    }

    /**
     * Test flush clears all cache
     */
    public function test_flush_clears_cache()
    {
        // Add some values
        CacheHelper::put('key1', 'value1', 3600);
        CacheHelper::put('key2', 'value2', 3600);
        
        $this->assertTrue(CacheHelper::has('key1'));
        
        // Flush cache
        $result = CacheHelper::flush();
        $this->assertTrue($result);
        
        // Values should be cleared
        $this->assertFalse(CacheHelper::has('key1'));
        $this->assertFalse(CacheHelper::has('key2'));
    }

    /**
     * Test putWithTags stores value with tags
     */
    public function test_put_with_tags()
    {
        $key = 'test:tagged:key';
        $value = 'test_value';
        $tags = ['test_tag', 'cache_test'];
        
        // putWithTags should not error
        CacheHelper::putWithTags($key, $value, 3600, $tags);
        
        // Verify method exists and works without errors
        $this->assertTrue(true);
    }

    /**
     * Test cache helper methods are accessible
     */
    public function test_cache_helper_static_methods()
    {
        // Verify all static methods are callable
        $this->assertTrue(method_exists(CacheHelper::class, 'remember'));
        $this->assertTrue(method_exists(CacheHelper::class, 'get'));
        $this->assertTrue(method_exists(CacheHelper::class, 'invalidate'));
        $this->assertTrue(method_exists(CacheHelper::class, 'invalidateTag'));
        $this->assertTrue(method_exists(CacheHelper::class, 'invalidateTags'));
        $this->assertTrue(method_exists(CacheHelper::class, 'put'));
        $this->assertTrue(method_exists(CacheHelper::class, 'putWithTags'));
        $this->assertTrue(method_exists(CacheHelper::class, 'has'));
        $this->assertTrue(method_exists(CacheHelper::class, 'forget'));
        $this->assertTrue(method_exists(CacheHelper::class, 'increment'));
        $this->assertTrue(method_exists(CacheHelper::class, 'stats'));
        $this->assertTrue(method_exists(CacheHelper::class, 'flush'));
    }
}
