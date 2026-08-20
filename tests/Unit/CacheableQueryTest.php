<?php

namespace Tests\Unit;

use App\Traits\CacheableQuery;
use Tests\TestCase;

class CacheableQueryTest extends TestCase
{
    /**
     * Test cache key generation without parameters
     */
    public function test_cache_key_without_parameters()
    {
        // Create a mock model using the trait
        $model = new class {
            use CacheableQuery;
        };
        
        $key = $model::cacheKey('list');
        
        $this->assertStringContainsString('laporin:', $key);
        $this->assertStringContainsString(':list', $key);
        // Should have the format: laporin:classname:list
        $this->assertTrue(str_contains($key, 'laporin:'), 'Cache key should start with laporin:');
    }

    /**
     * Test cache key generation with single parameter
     */
    public function test_cache_key_with_single_parameter()
    {
        $model = new class {
            use CacheableQuery;
        };
        
        $key = $model::cacheKey('detail', 123);
        
        $this->assertStringContainsString('laporin:', $key);
        $this->assertStringContainsString(':detail:', $key);
        // MD5 of "123"
        $this->assertStringContainsString('202cb962ac59075b964b07152d234b70', $key);
    }

    /**
     * Test cache key generation with multiple parameters
     */
    public function test_cache_key_with_multiple_parameters()
    {
        $model = new class {
            use CacheableQuery;
        };
        
        $key = $model::cacheKey('search', 'active', 'admin');
        
        $this->assertStringContainsString('laporin:', $key);
        $this->assertStringContainsString(':search:', $key);
        // Should have two MD5 hashes separated
        $parts = explode(':', $key);
        $this->assertGreaterThan(4, count($parts)); // laporin:class:action:hash1:hash2
    }

    /**
     * Test cache key consistency
     */
    public function test_cache_key_is_consistent()
    {
        $model = new class {
            use CacheableQuery;
        };
        
        $key1 = $model::cacheKey('list', 'all');
        $key2 = $model::cacheKey('list', 'all');
        
        $this->assertEquals($key1, $key2, 'Same parameters should produce same cache key');
    }

    /**
     * Test cache key with JSON parameters
     */
    public function test_cache_key_with_array_parameter()
    {
        $model = new class {
            use CacheableQuery;
        };
        
        $params = ['status' => 'active', 'role' => 'admin'];
        $key = $model::cacheKey('filter', $params);
        
        $this->assertStringContainsString('laporin:', $key);
        $this->assertStringContainsString(':filter:', $key);
    }

    /**
     * Test cache tag generation
     */
    public function test_cache_tag_generation()
    {
        $model = new class {
            use CacheableQuery;
        };
        
        $tag = $model::cacheTag();
        
        $this->assertIsString($tag);
        $this->assertNotEmpty($tag);
        // Tag should be lowercase
        $this->assertEquals($tag, strtolower($tag));
    }

    /**
     * Test cache prefix generation without action
     */
    public function test_cache_prefix_without_action()
    {
        $model = new class {
            use CacheableQuery;
        };
        
        $prefix = $model::cachePrefix();
        
        $this->assertStringContainsString('laporin:', $prefix);
        $this->assertTrue(str_ends_with($prefix, ':'));
    }

    /**
     * Test cache prefix generation with action
     */
    public function test_cache_prefix_with_action()
    {
        $model = new class {
            use CacheableQuery;
        };
        
        $prefix = $model::cachePrefix('list');
        
        $this->assertStringContainsString('laporin:', $prefix);
        $this->assertStringContainsString(':list:', $prefix);
        $this->assertTrue(str_ends_with($prefix, ':'));
    }

    /**
     * Test cache prefix can be used for pattern matching
     */
    public function test_cache_prefix_for_pattern_matching()
    {
        $model = new class {
            use CacheableQuery;
        };
        
        $prefix = $model::cachePrefix('list');
        $key = $model::cacheKey('list', 'all');
        
        // Key should match the prefix pattern
        $this->assertTrue(str_starts_with($key, substr($prefix, 0, -1))); // Remove trailing :
    }
}
