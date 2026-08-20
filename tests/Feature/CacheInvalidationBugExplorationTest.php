<?php

namespace Tests\Feature;

use App\Helpers\CacheHelper;
use App\Models\DamageDetail;
use App\Models\Report;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CacheInvalidationBugExplorationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_stale_data_when_new_report_created(): void
    {
        Report::factory()->count(5)->create();

        $cacheKey = Report::cacheKey(
            'list',
            'all'
        );

        $cachedReports = Cache::tags([
            Report::cacheTag(),
        ])->remember(
            $cacheKey,
            3600,
            fn () => Report::query()->get()
        );

        $this->assertCount(
            5,
            $cachedReports
        );

        $newReport = Report::factory()->create([
            'report_type' => 'damage',
            'assigned_to_role' => 'sarpras',
        ]);

        $updatedReports = Cache::tags([
            Report::cacheTag(),
        ])->remember(
            $cacheKey,
            3600,
            fn () => Report::query()->get()
        );

        $this->assertCount(
            6,
            $updatedReports
        );

        $this->assertContains(
            $newReport->id,
            $updatedReports
                ->pluck('id')
                ->all()
        );
    }

    public function test_stale_data_when_report_updated(): void
    {
        $report = Report::factory()->create([
            'title' => 'Original Title',
            'report_type' => 'damage',
        ]);

        $cacheKey = Report::cacheKey(
            'detail',
            $report->id
        );

        $cachedReport = Cache::tags([
            Report::cacheTag(),
        ])->remember(
            $cacheKey,
            3600,
            fn () => Report::query()
                ->findOrFail($report->id)
        );

        $this->assertSame(
            'Original Title',
            $cachedReport->title
        );

        $report->update([
            'title' => 'Updated Title',
        ]);

        $updatedReport = Cache::tags([
            Report::cacheTag(),
        ])->remember(
            $cacheKey,
            3600,
            fn () => Report::query()
                ->findOrFail($report->id)
        );

        $this->assertSame(
            'Updated Title',
            $updatedReport->title
        );
    }

    public function test_stale_data_when_report_deleted(): void
    {
        $report1 = Report::factory()->create();
        Report::factory()->count(2)->create();

        $cacheKey = Report::cacheKey(
            'list',
            'all'
        );

        $cachedReports = Cache::tags([
            Report::cacheTag(),
        ])->remember(
            $cacheKey,
            3600,
            fn () => Report::query()->get()
        );

        $this->assertCount(
            3,
            $cachedReports
        );

        $report1->forceDelete();

        $updatedReports = Cache::tags([
            Report::cacheTag(),
        ])->remember(
            $cacheKey,
            3600,
            fn () => Report::query()->get()
        );

        $this->assertCount(
            2,
            $updatedReports
        );

        $this->assertNotContains(
            $report1->id,
            $updatedReports
                ->pluck('id')
                ->all()
        );
    }

    public function test_consistency_issue_when_data_changes(): void
    {
        $cacheKey = Report::cacheKey(
            'list',
            'all'
        );

        $initialReports = Cache::tags([
            Report::cacheTag(),
        ])->remember(
            $cacheKey,
            3600,
            fn () => Report::query()->get()
        );

        $initialCount = $initialReports->count();

        Report::factory()->create();

        $updatedReports = Cache::tags([
            Report::cacheTag(),
        ])->remember(
            $cacheKey,
            3600,
            fn () => Report::query()->get()
        );

        $this->assertSame(
            $initialCount + 1,
            $updatedReports->count()
        );
    }

    public function test_related_cache_not_invalidated_on_detail_changes(): void
    {
        $report = Report::factory()->create([
            'report_type' => 'damage',
        ]);

        $cacheKey = Report::cacheKey(
            'detail',
            $report->id
        );

        $cachedReport = Cache::tags([
            Report::cacheTag(),
        ])->remember(
            $cacheKey,
            3600,
            fn () => Report::query()
                ->with('damageDetail')
                ->findOrFail($report->id)
        );

        $this->assertNull(
            $cachedReport->damageDetail
        );

        DamageDetail::create([
            'report_id' => $report->id,
            'item_name' => 'AC Ruang Kelas',
            'item_category' => 'Elektronik',
            'damage_condition' => 'Tidak berfungsi dengan baik.',
            'suspected_cause' => 'Kerusakan komponen.',
            'priority' => 'sedang',
        ]);

        $updatedReport = Cache::tags([
            Report::cacheTag(),
        ])->remember(
            $cacheKey,
            3600,
            fn () => Report::query()
                ->with('damageDetail')
                ->findOrFail($report->id)
        );

        $this->assertNotNull(
            $updatedReport->damageDetail
        );
    }

    public function test_cache_invalidation_timing_rapid_operations(): void
    {
        Report::factory()->count(5)->create();

        $cacheKey = Report::cacheKey(
            'list',
            'all'
        );

        $initialReports = Cache::tags([
            Report::cacheTag(),
        ])->remember(
            $cacheKey,
            3600,
            fn () => Report::query()->get()
        );

        $this->assertCount(
            5,
            $initialReports
        );

        for ($i = 1; $i <= 5; $i++) {
            Report::factory()->create();

            $currentReports = Cache::tags([
                Report::cacheTag(),
            ])->remember(
                $cacheKey,
                3600,
                fn () => Report::query()->get()
            );

            $this->assertCount(
                5 + $i,
                $currentReports
            );
        }
    }

    public function test_grouped_cache_invalidation_missing(): void
    {
        $report = Report::factory()->create();

        $detailKey = Report::cacheKey(
            'detail',
            $report->id
        );

        $statusKey = Report::cacheKey(
            'status',
            $report->id
        );

        Cache::tags([
            Report::cacheTag(),
        ])->put(
            $detailKey,
            $report,
            3600
        );

        Cache::tags([
            Report::cacheTag(),
        ])->put(
            $statusKey,
            'active',
            3600
        );

        $this->assertTrue(
            Cache::tags([
                Report::cacheTag(),
            ])->has($detailKey)
        );

        $this->assertTrue(
            Cache::tags([
                Report::cacheTag(),
            ])->has($statusKey)
        );

        $report->update([
            'status' => 'sedang_ditangani',
        ]);

        $this->assertFalse(
            Cache::tags([
                Report::cacheTag(),
            ])->has($detailKey)
        );

        $this->assertFalse(
            Cache::tags([
                Report::cacheTag(),
            ])->has($statusKey)
        );
    }
}
