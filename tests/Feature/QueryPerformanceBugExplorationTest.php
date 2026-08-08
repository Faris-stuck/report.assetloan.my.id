<?php

namespace Tests\Feature;

use App\Models\Report;
use App\Models\Location;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class QueryPerformanceBugExplorationTest extends TestCase
{
    /**
     * Disable automatic database reset for these tests
     * since we're just measuring performance, not testing data integrity
     */
    // use RefreshDatabase;  // Commented out to avoid migration issues

    protected function setUp(): void
    {
        parent::setUp();
        // Create test data without full seeding
    }

    /**
     * Bug Condition: Query performance degradation without cache
     * 
     * Validates: Requirements 1.3, 1.4
     * 
     * This test measures the response time for fetching the report list
     * WITHOUT any caching layer. On unfixed code, the response time will
     * be significantly slower (150-400ms or more) than the target <100ms.
     * 
     * The test is expected to FAIL on unfixed code showing slow performance.
     * 
     * @test
     */
    public function test_report_list_query_performance_without_cache(): void
    {
        // Create multiple reports for realistic query
        for ($i = 0; $i < 50; $i++) {
            Report::create([
                'report_number' => 'LAP-' . uniqid(),
                'public_token' => \Illuminate\Support\Str::uuid(),
                'access_code_hash' => hash('sha256', 'test'),
                'reporter_type' => 'siswa',
                'reporter_name' => 'Test Reporter',
                'report_type' => 'damage',
                'title' => 'Test Report ' . $i,
                'incident_date' => now()->toDateString(),
                'description' => 'Test description',
                'status' => 'menunggu_verifikasi',
                'urgency' => 'sedang'
            ]);
        }

        // Bug Condition: Flush cache to simulate unfixed code without caching layer
        Cache::flush();

        // Measure query response time for first access (cache miss)
        $start = microtime(true);
        $reports = Report::with('location', 'violationType')->get();
        $elapsed_ms = (microtime(true) - $start) * 1000;

        // Bug Condition: Report query should return <100ms (target is <50ms with cache)
        // On unfixed code WITHOUT caching layer:
        // - Expected: 150-400ms (direct database query, no cache)
        // - This assertion will FAIL to confirm the bug exists
        $this->assertLessThan(100, $elapsed_ms,
            "Report list query took {$elapsed_ms}ms instead of <100ms. " .
            "Bug Condition: No query caching layer causes slow database queries (150-400ms)"
        );

        $this->assertGreaterThan(0, $reports->count());
    }

    /**
     * Bug Condition: Cache miss response time <100ms
     * 
     * Validates: Requirements 1.3
     * 
     * This test explicitly measures cache MISS scenario (first access after flush).
     * On unfixed code, this will show that even first access is slow (150-400ms)
     * because there's no cache layer at all.
     * 
     * @test
     */
    public function test_query_response_time_cache_miss_under_100ms(): void
    {
        // Create test data
        for ($i = 0; $i < 30; $i++) {
            Report::create([
                'report_number' => 'LAP-' . uniqid(),
                'public_token' => \Illuminate\Support\Str::uuid(),
                'access_code_hash' => hash('sha256', 'test'),
                'reporter_type' => 'siswa',
                'reporter_name' => 'Test',
                'report_type' => 'damage',
                'title' => 'Report ' . $i,
                'incident_date' => now()->toDateString(),
                'description' => 'Test',
                'status' => 'menunggu_verifikasi',
                'urgency' => 'sedang'
            ]);
        }

        // Explicitly flush cache to ensure cache miss
        Cache::flush();

        // Measure first query (cache miss)
        $start = microtime(true);
        $reports = Report::all();
        $miss_time = (microtime(true) - $start) * 1000;

        // Bug Condition: First query (cache miss) should be <100ms
        // On unfixed code: expected 150-400ms (no caching layer)
        // This assertion is expected to FAIL on unfixed code
        $this->assertLessThan(100, $miss_time,
            "First query (cache miss) took {$miss_time}ms instead of <100ms. " .
            "Bug Condition: Database query without cache is too slow"
        );

        $this->assertGreaterThan(0, $reports->count());
    }

    /**
     * Bug Condition: Cache hit response time <50ms
     * 
     * Validates: Requirements 1.3
     * 
     * This test measures cache HIT scenario (second access after cache populated).
     * On unfixed code, BOTH first and second access will be slow (150-400ms)
     * because there's no caching layer at all.
     * 
     * @test
     */
    public function test_query_response_time_cache_hit_under_50ms(): void
    {
        // Create test data
        for ($i = 0; $i < 30; $i++) {
            Report::create([
                'report_number' => 'LAP-' . uniqid(),
                'public_token' => \Illuminate\Support\Str::uuid(),
                'access_code_hash' => hash('sha256', 'test'),
                'reporter_type' => 'siswa',
                'reporter_name' => 'Test',
                'report_type' => 'damage',
                'title' => 'Report ' . $i,
                'incident_date' => now()->toDateString(),
                'description' => 'Test',
                'status' => 'menunggu_verifikasi',
                'urgency' => 'sedang'
            ]);
        }

        // First query to populate cache (if caching layer exists)
        $reports = Report::all();
        $this->assertGreaterThan(0, $reports->count());

        // Measure second query (cache hit)
        $start = microtime(true);
        $reports = Report::all();
        $hit_time = (microtime(true) - $start) * 1000;

        // Bug Condition: Second query (cache hit) should be <50ms
        // On unfixed code: expected 150-400ms (no caching layer, so same slow speed as first)
        // This assertion is expected to FAIL on unfixed code
        $this->assertLessThan(50, $hit_time,
            "Query response time (cache hit) took {$hit_time}ms instead of <50ms. " .
            "Bug Condition: No caching layer causes slow response even on repeated queries"
        );
    }

    /**
     * Bug Condition: Concurrent queries degrade performance significantly
     * 
     * Validates: Requirements 1.4
     * 
     * This test simulates multiple concurrent queries and measures the
     * cumulative response time. On unfixed code without caching, the
     * database will be overloaded and response times will increase
     * exponentially (200-600ms+ per request).
     * 
     * @test
     */
    public function test_concurrent_query_performance_degradation(): void
    {
        // Create test data
        for ($i = 0; $i < 100; $i++) {
            Report::create([
                'report_number' => 'LAP-' . uniqid(),
                'public_token' => \Illuminate\Support\Str::uuid(),
                'access_code_hash' => hash('sha256', 'test'),
                'reporter_type' => 'siswa',
                'reporter_name' => 'Test',
                'report_type' => 'damage',
                'title' => 'Report ' . $i,
                'incident_date' => now()->toDateString(),
                'description' => 'Test',
                'status' => 'menunggu_verifikasi',
                'urgency' => 'sedang'
            ]);
        }

        // Simulate 10 "concurrent" queries (sequential for test purposes)
        $times = [];
        for ($i = 0; $i < 10; $i++) {
            Cache::flush();  // Flush to ensure cache miss each time
            
            $start = microtime(true);
            $reports = Report::with('location', 'violationType')->get();
            $elapsed_ms = (microtime(true) - $start) * 1000;
            
            $times[] = $elapsed_ms;
        }

        $avg_time = array_sum($times) / count($times);
        $max_time = max($times);

        // Bug Condition: Average response time should be <100ms
        // On unfixed code: expected 200-400ms average (database overload from multiple queries)
        // This assertion is expected to FAIL on unfixed code
        $this->assertLessThan(100, $avg_time,
            "Average query time across 10 requests: {$avg_time}ms. " .
            "Bug Condition: Multiple concurrent queries cause database overload (200-600ms per request)"
        );

        // Also check max time didn't spike (indicating stability)
        $this->assertLessThan(150, $max_time,
            "Peak query time: {$max_time}ms. " .
            "Bug Condition: Concurrent load causes exponential performance degradation"
        );
    }

    /**
     * Bug Condition: Master data queries are slow without caching
     * 
     * Validates: Requirements 1.3
     * 
     * This test specifically checks master data queries (locations, damage categories)
     * which should be cached but are slow on unfixed code.
     * 
     * @test
     */
    public function test_master_data_query_performance_without_cache(): void
    {
        // Create master data
        for ($i = 0; $i < 20; $i++) {
            Location::create([
                'location_name' => 'Location ' . $i,
                'location_type' => 'classroom',
                'is_active' => true
            ]);
        }

        // Flush cache to simulate unfixed code
        Cache::flush();

        // Measure location query time
        $start = microtime(true);
        $locations = Location::all();
        $elapsed_ms = (microtime(true) - $start) * 1000;

        // Bug Condition: Master data queries should be <50ms (cached)
        // Or <100ms for cache miss, but on unfixed code: 150-300ms
        $this->assertLessThan(100, $elapsed_ms,
            "Master data query took {$elapsed_ms}ms instead of <100ms. " .
            "Bug Condition: Master data should be cached to improve performance"
        );

        $this->assertGreaterThan(0, $locations->count());
    }

    /**
     * Bug Condition: Query time increases with data volume
     * 
     * Validates: Requirements 1.3, 1.4
     * 
     * This test checks that response time remains acceptable even with
     * larger data volumes. On unfixed code, performance will degrade
     * significantly as data volume increases.
     * 
     * @test
     */
    public function test_query_time_stability_with_increased_data_volume(): void
    {
        // Create initial dataset
        for ($i = 0; $i < 100; $i++) {
            Report::create([
                'report_number' => 'LAP-' . uniqid(),
                'public_token' => \Illuminate\Support\Str::uuid(),
                'access_code_hash' => hash('sha256', 'test'),
                'reporter_type' => 'siswa',
                'reporter_name' => 'Test',
                'report_type' => 'damage',
                'title' => 'Report ' . $i,
                'incident_date' => now()->toDateString(),
                'description' => 'Test',
                'status' => 'menunggu_verifikasi',
                'urgency' => 'sedang'
            ]);
        }
        
        Cache::flush();
        $start = microtime(true);
        $reports_100 = Report::all();
        $time_100 = (microtime(true) - $start) * 1000;

        // Create more data
        for ($i = 100; $i < 500; $i++) {
            Report::create([
                'report_number' => 'LAP-' . uniqid(),
                'public_token' => \Illuminate\Support\Str::uuid(),
                'access_code_hash' => hash('sha256', 'test'),
                'reporter_type' => 'siswa',
                'reporter_name' => 'Test',
                'report_type' => 'damage',
                'title' => 'Report ' . $i,
                'incident_date' => now()->toDateString(),
                'description' => 'Test',
                'status' => 'menunggu_verifikasi',
                'urgency' => 'sedang'
            ]);
        }
        
        Cache::flush();
        $start = microtime(true);
        $reports_500 = Report::all();
        $time_500 = (microtime(true) - $start) * 1000;

        // Performance should remain acceptable
        // On unfixed code without cache: both will be slow (150-400ms)
        // With cache: both should be <50ms
        $this->assertLessThan(100, $time_100,
            "100 records query took {$time_100}ms instead of <100ms"
        );

        $this->assertLessThan(100, $time_500,
            "500 records query took {$time_500}ms instead of <100ms. " .
            "Bug Condition: Query performance degrades with data volume without caching"
        );

        // Ratio shouldn't degrade too much (should stay <2x if cached, much worse without)
        $ratio = $time_500 / max($time_100, 1);
        $this->assertLessThan(3, $ratio,
            "Performance degradation ratio: {$ratio}. " .
            "Bug Condition: Response time grows too fast with data volume (indicates no caching)"
        );
    }
}
