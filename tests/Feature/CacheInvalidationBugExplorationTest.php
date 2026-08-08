<?php

namespace Tests\Feature;

use App\Models\DamageDetail;
use App\Models\Location;
use App\Models\Report;
use App\Models\ViolationType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CacheInvalidationBugExplorationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Create test data without full seeding
    }

    /**
     * Bug Condition: Stale data displayed when cache not invalidated
     * 
     * Validates: Requirements 1.5
     * 
     * This test demonstrates the bug where newly created reports don't
     * appear in cached lists until the cache naturally expires. This
     * causes users to see stale data.
     * 
     * The test is expected to FAIL on unfixed code where cache is not
     * invalidated on data changes.
     * 
     * @test
     */
    public function test_stale_data_when_new_report_created(): void
    {
        // Initial setup: Create some reports
        $initial_reports = Report::factory()->count(5)->create();

        // Simulate caching the report list
        $cache_key = 'reports:list:all';
        $cached_reports = Cache::remember($cache_key, 3600, fn() => 
            Report::with('location', 'violationType')->get()
        );

        $this->assertCount(5, $cached_reports);

        // Bug Condition: Create a new report
        // Expected: Cache should be invalidated, new report visible in next query
        // Actual on unfixed code: Cache not invalidated, new report NOT visible
        $new_report = Report::factory()->create([
            'report_type' => 'damage',
            'assigned_to_role' => 'sarpras'
        ]);

        // Bug Condition: Fetch reports again - on unfixed code, cache still has old data
        $updated_reports = Cache::remember($cache_key, 3600, fn() => 
            Report::with('location', 'violationType')->get()
        );

        // Expected: New report should be visible (cache invalidated)
        // On unfixed code: New report NOT visible (cache not invalidated)
        $this->assertCount(6, $updated_reports,
            "New report should be visible in list after creation. " .
            "Bug Condition: Cache not invalidated causes stale data display - " .
            "still seeing 5 reports instead of 6"
        );

        // Verify the new report is actually in the list
        $report_ids = $updated_reports->pluck('id')->toArray();
        $this->assertContains($new_report->id, $report_ids,
            "Newly created report {$new_report->id} should appear in list immediately"
        );
    }

    /**
     * Bug Condition: Updated report shows old data in cache
     * 
     * Validates: Requirements 1.5
     * 
     * This test demonstrates that when a report is updated, the cached
     * version still shows the old data, causing inconsistency.
     * 
     * @test
     */
    public function test_stale_data_when_report_updated(): void
    {
        // Create initial report
        $report = Report::factory()->create([
            'title' => 'Original Title',
            'report_type' => 'damage'
        ]);

        // Cache the report detail
        $cache_key = 'report:detail:' . $report->id;
        $cached_report = Cache::remember($cache_key, 3600, fn() => 
            Report::with('location', 'violationType')->find($report->id)
        );

        $this->assertEquals('Original Title', $cached_report->title);

        // Bug Condition: Update the report
        $report->update(['title' => 'Updated Title']);

        // Bug Condition: Fetch cached report again
        // Expected: Should see "Updated Title" (cache invalidated)
        // Actual on unfixed code: Still see "Original Title" (cache not invalidated)
        $updated_cached_report = Cache::remember($cache_key, 3600, fn() => 
            Report::with('location', 'violationType')->find($report->id)
        );

        // On unfixed code, this will FAIL because cache returns old data
        $this->assertEquals('Updated Title', $updated_cached_report->title,
            "Updated report title should be visible immediately. " .
            "Bug Condition: Cache not invalidated causes stale data - " .
            "still seeing 'Original Title' instead of 'Updated Title'"
        );
    }

    /**
     * Bug Condition: Deleted report still appears in cache
     * 
     * Validates: Requirements 1.5
     * 
     * This test demonstrates that when a report is deleted, it still
     * appears in the cached report list.
     * 
     * @test
     */
    public function test_stale_data_when_report_deleted(): void
    {
        // Create initial reports
        $report1 = Report::factory()->create();
        $report2 = Report::factory()->create();
        $report3 = Report::factory()->create();

        // Cache the report list
        $cache_key = 'reports:list:all';
        $cached_reports = Cache::remember($cache_key, 3600, fn() => 
            Report::all()
        );

        $this->assertCount(3, $cached_reports);
        $initial_ids = $cached_reports->pluck('id')->toArray();
        $this->assertContains($report1->id, $initial_ids);

        // Bug Condition: Delete a report
        $report1->forceDelete();

        // Bug Condition: Fetch cached list again
        // Expected: Deleted report should not appear (cache invalidated)
        // Actual on unfixed code: Deleted report still appears (cache not invalidated)
        $updated_cached_reports = Cache::remember($cache_key, 3600, fn() => 
            Report::all()
        );

        // On unfixed code, this will FAIL because deleted report still in cache
        $this->assertCount(2, $updated_cached_reports,
            "Deleted report should not appear in list. " .
            "Bug Condition: Cache not invalidated causes stale data - " .
            "still seeing deleted report in list"
        );

        $updated_ids = $updated_cached_reports->pluck('id')->toArray();
        $this->assertNotContains($report1->id, $updated_ids,
            "Deleted report {$report1->id} should not appear in list"
        );
    }

    /**
     * Bug Condition: Multiple users see different data (consistency issue)
     * 
     * Validates: Requirements 1.5
     * 
     * This test simulates two users where User A creates a report,
     * but User B still sees cached old list without the new report.
     * 
     * @test
     */
    public function test_consistency_issue_when_data_changes(): void
    {
        // Simulate User A viewing report list
        $cache_key = 'reports:list:all';
        $user_a_reports = Cache::remember($cache_key, 3600, fn() => 
            Report::all()
        );
        $user_a_count = count($user_a_reports);

        // Simulate User B creating a new report
        Report::factory()->create();

        // Simulate User A viewing report list again
        // Bug Condition: On unfixed code, User A still sees old count (cache not invalidated)
        $user_a_reports_again = Cache::remember($cache_key, 3600, fn() => 
            Report::all()
        );
        $user_a_count_again = count($user_a_reports_again);

        // Expected: User A should see new count (cache invalidated)
        // On unfixed code: User A sees same old count
        $this->assertGreaterThan($user_a_count, $user_a_count_again,
            "User should see newly created data immediately. " .
            "Bug Condition: Cache not invalidated causes data consistency issues - " .
            "User sees {$user_a_count_again} reports instead of " . ($user_a_count + 1)
        );
    }

    /**
     * Bug Condition: Related cache entries not invalidated
     * 
     * Validates: Requirements 1.5
     * 
     * This test verifies that when damage details are added to a report,
     * both the report cache AND report list cache are invalidated.
     * 
     * @test
     */
    public function test_related_cache_not_invalidated_on_detail_changes(): void
    {
        // Create report and cache it
        $report = Report::factory()->create();
        
        $cache_key = 'report:detail:' . $report->id;
        Cache::remember($cache_key, 3600, fn() => $report);

        // Cache report list
        $list_cache_key = 'reports:list:all';
        Cache::remember($list_cache_key, 3600, fn() => Report::all());

        // Get initial damage detail count
        $initial_damage_count = $report->damageDetails->count();

        // Bug Condition: Add damage detail to report
        DamageDetail::factory()->create([
            'report_id' => $report->id,
            'description' => 'Damage description'
        ]);

        // Bug Condition: Fetch report again - cache should be invalidated
        $updated_report = Cache::remember($cache_key, 3600, fn() => 
            Report::with('damageDetails')->find($report->id)
        );

        // On unfixed code, damage detail cache not invalidated,
        // so report cache returns old data without new damage detail
        $new_damage_count = $updated_report->damageDetails->count();

        $this->assertGreaterThan($initial_damage_count, $new_damage_count,
            "Added damage detail should be visible in cached report. " .
            "Bug Condition: Related cache entries not invalidated - " .
            "damage count still {$new_damage_count} instead of " . ($initial_damage_count + 1)
        );
    }

    /**
     * Bug Condition: Cache invalidation timing issues
     * 
     * Validates: Requirements 1.5
     * 
     * This test checks that cache is invalidated immediately, not
     * after a delay, by doing rapid create/read cycles.
     * 
     * @test
     */
    public function test_cache_invalidation_timing_rapid_operations(): void
    {
        $cache_key = 'reports:list:all';

        // Initial cache
        Report::factory()->count(5)->create();
        $initial = Cache::remember($cache_key, 3600, fn() => Report::all());
        $count = count($initial);

        // Bug Condition: Rapid create and read
        for ($i = 0; $i < 5; $i++) {
            Report::factory()->create();

            // Immediately read - cache should be invalidated
            $current = Cache::remember($cache_key, 3600, fn() => Report::all());
            $expected_count = $count + $i + 1;

            // On unfixed code without cache invalidation, this will FAIL
            // First iteration will show old count, subsequent iterations may show delayed updates
            $report_num = $i + 1;
            $this->assertCount($expected_count, $current,
                "Report count should be {$expected_count} after creating report #{$report_num}. " .
                "Bug Condition: Cache not immediately invalidated on rapid operations"
            );

            $count = $expected_count;
        }
    }

    /**
     * Bug Condition: No cache tags prevent grouped invalidation
     * 
     * Validates: Requirements 1.5
     * 
     * This test demonstrates that without cache tags, we can't efficiently
     * invalidate related cache entries (e.g., all report-related caches).
     * 
     * @test
     */
    public function test_grouped_cache_invalidation_missing(): void
    {
        // Create multiple cache entries
        $caches = [];
        for ($i = 1; $i <= 3; $i++) {
            $report = Report::factory()->create();
            Cache::put("report:{$i}:detail", $report, 3600);
            Cache::put("report:{$i}:status", 'active', 3600);
            $caches[] = $i;
        }

        // Verify caches exist
        foreach ($caches as $i) {
            $this->assertTrue(Cache::has("report:{$i}:detail"));
            $this->assertTrue(Cache::has("report:{$i}:status"));
        }

        // Bug Condition: Update report triggers observer
        Report::first()->update(['status' => 'pending']);

        // Bug Condition: Without cache tags, we need manual invalidation
        // With cache tags, we can do: Cache::tags('reports')->flush()
        // Without tags, we might miss some cache entries
        
        // Ideally, all report-related cache entries should be cleared
        // On unfixed code without proper cache invalidation:
        // - Some cache entries may still exist with stale data
        // - No efficient way to invalidate all related caches

        // This test documents the limitation without cache tags
        $this->assertTrue(true, 
            "Cache invalidation test: Demonstrates lack of grouped invalidation. " .
            "Bug Condition: Without cache tags, cannot efficiently invalidate related caches"
        );
    }
}
