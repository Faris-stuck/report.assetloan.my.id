<?php

namespace Tests\Feature\Performance;

use App\Helpers\CacheHelper;
use App\Models\BullyingDetail;
use App\Models\DamageCategory;
use App\Models\DamageDetail;
use App\Models\Location;
use App\Models\Report;
use App\Models\SchoolClass;
use App\Models\StaffUnit;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class PerformanceQueryCountAssertionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    /**
     * Test 1: Constant O(1) query count for Kesiswaan violation list view regardless of item count.
     */
    public function test_kesiswaan_list_view_has_constant_O1_query_count(): void
    {
        $kesiswaan = User::where('email', 'kesiswaan@laporin.local')->firstOrFail();
        $class = SchoolClass::firstOrFail();
        $location = Location::firstOrFail();

        // 1. Measure query count with 5 reports
        $this->createBatchViolationReports(5, $class, $location);

        DB::enableQueryLog();
        DB::flushQueryLog();

        $this->actingAs($kesiswaan)->get(route('kesiswaan.index'))->assertOk();

        $queries5 = DB::getQueryLog();
        $count5 = count($queries5);

        // 2. Measure query count with 50 reports
        $this->createBatchViolationReports(45, $class, $location);

        DB::flushQueryLog();

        $this->actingAs($kesiswaan)->get(route('kesiswaan.index'))->assertOk();

        $queries50 = DB::getQueryLog();
        $count50 = count($queries50);

        // Assert query count is constant (O(1)) and does not scale linearly with 50 items
        $this->assertLessThanOrEqual(
            20,
            $count50,
            "Kesiswaan list view query count ({$count50}) exceeded O(1) threshold."
        );
        $this->assertLessThanOrEqual(
            $count5 + 3,
            $count50,
            "Query count grew with item count (5 items: {$count5}, 50 items: {$count50}). N+1 regression detected!"
        );
    }

    /**
     * Test 2: Constant O(1) query count for Sarpras damage list view regardless of item count.
     */
    public function test_sarpras_list_view_has_constant_O1_query_count(): void
    {
        $sarpras = User::where('email', 'sarpras@laporin.local')->firstOrFail();
        $class = SchoolClass::firstOrFail();
        $location = Location::firstOrFail();
        $category = DamageCategory::firstOrFail();

        // 1. Measure query count with 5 damage reports
        $this->createBatchDamageReports(5, $class, $location, $category);

        DB::enableQueryLog();
        DB::flushQueryLog();

        $this->actingAs($sarpras)->get(route('sarpras.index'))->assertOk();

        $count5 = count(DB::getQueryLog());

        // 2. Measure query count with 50 damage reports
        $this->createBatchDamageReports(45, $class, $location, $category);

        DB::flushQueryLog();

        $this->actingAs($sarpras)->get(route('sarpras.index'))->assertOk();

        $count50 = count(DB::getQueryLog());

        // Assert query count is constant (O(1)) and <= threshold
        $this->assertLessThanOrEqual(
            20,
            $count50,
            "Sarpras list view query count ({$count50}) exceeded O(1) threshold."
        );
        $this->assertLessThanOrEqual(
            $count5 + 3,
            $count50,
            "Sarpras query count grew with item count (5 items: {$count5}, 50 items: {$count50}). N+1 regression detected!"
        );
    }

    /**
     * Test 3: Constant O(1) query count for Dashboard report list view regardless of item count.
     */
    public function test_dashboard_list_view_has_constant_O1_query_count(): void
    {
        $superadmin = User::where('email', 'admin@laporin.local')->firstOrFail();
        $class = SchoolClass::firstOrFail();
        $location = Location::firstOrFail();

        $this->createBatchViolationReports(5, $class, $location);

        DB::enableQueryLog();
        DB::flushQueryLog();

        $this->actingAs($superadmin)->get(route('dashboard'))->assertOk();
        $count5 = count(DB::getQueryLog());

        $this->createBatchViolationReports(45, $class, $location);

        DB::flushQueryLog();

        $this->actingAs($superadmin)->get(route('dashboard'))->assertOk();
        $count50 = count(DB::getQueryLog());

        $this->assertLessThanOrEqual(
            25,
            $count50,
            "Dashboard query count ({$count50}) exceeded O(1) threshold."
        );
        $this->assertLessThanOrEqual(
            $count5 + 5,
            $count50,
            "Dashboard query count grew with item count (5 items: {$count5}, 50 items: {$count50}). N+1 regression detected!"
        );
    }

    /**
     * Test 4: Constant O(1) query count for Admin master locations list view.
     */
    public function test_admin_master_locations_has_constant_O1_query_count(): void
    {
        $superadmin = User::where('email', 'admin@laporin.local')->firstOrFail();
        $class = SchoolClass::firstOrFail();

        // Create 20 locations attached to class
        for ($i = 1; $i <= 20; $i++) {
            Location::create([
                'location_name' => "Location Perf Test {$i}",
                'location_type' => 'kelas',
                'class_id' => $class->id,
                'is_active' => true,
            ]);
        }

        DB::enableQueryLog();
        DB::flushQueryLog();

        $this->actingAs($superadmin)->get(route('admin.master.index', ['resource' => 'locations']))->assertOk();

        $queries = DB::getQueryLog();
        $queryCount = count($queries);

        $this->assertLessThanOrEqual(
            20,
            $queryCount,
            "Admin master locations query count ({$queryCount}) exceeded threshold. Check eager loading of class relation."
        );
    }

    /**
     * Test 5: Warm cache hit executes ZERO database queries for dashboard summary stats and monthly chart.
     */
    public function test_warm_cache_hit_executes_zero_database_queries_for_dashboard_stats_and_charts(): void
    {
        $superadmin = User::where('email', 'admin@laporin.local')->firstOrFail();
        $userKey = $superadmin->id . '_' . $superadmin->role;

        $statsKey = "laporin:dashboard:stats:{$userKey}";
        $chartKey = "laporin:dashboard:chart:{$userKey}";

        // Cold call: populate cache
        CacheHelper::remember($statsKey, 300, fn () => [
            'total' => Report::count(),
            'violation' => Report::where('report_type', 'violation')->count(),
            'damage' => Report::where('report_type', 'damage')->count(),
            'pending' => Report::where('status', 'menunggu_verifikasi')->count(),
            'done' => Report::where('status', 'selesai')->count(),
        ]);

        CacheHelper::remember($chartKey, 300, fn () => [
            'title' => 'Semua Laporan 6 Bulan Terakhir',
            'labels' => ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun'],
            'counts' => [0, 0, 0, 0, 0, 0],
            'max' => 1,
        ]);

        // Warm call: DB query count MUST be 0
        DB::enableQueryLog();
        DB::flushQueryLog();

        $cachedStats = CacheHelper::remember($statsKey, 300, fn () => [
            'total' => Report::count(),
        ]);

        $cachedChart = CacheHelper::remember($chartKey, 300, fn () => [
            'title' => 'Fallback DB Query',
        ]);

        $queries = DB::getQueryLog();

        $this->assertCount(
            0,
            $queries,
            'Warm cache hit for dashboard stats & chart MUST execute 0 database queries!'
        );
        $this->assertIsArray($cachedStats);
        $this->assertIsArray($cachedChart);
    }

    /**
     * Test 6: Warm cache hit executes ZERO database queries for public report reference data.
     */
    public function test_warm_cache_hit_executes_zero_database_queries_for_public_reference_data(): void
    {
        $classesKey = 'laporin:reference:classes';
        $locationsKey = 'laporin:reference:locations';
        $subjectsKey = 'laporin:reference:subjects';
        $staffUnitsKey = 'laporin:reference:staff_units';
        $damageCategoriesKey = 'laporin:reference:damage_categories';

        // Warm up cache
        CacheHelper::remember($classesKey, 3600, fn () => SchoolClass::where('is_active', true)->get());
        CacheHelper::remember($locationsKey, 3600, fn () => Location::where('is_active', true)->get());
        CacheHelper::remember($subjectsKey, 3600, fn () => Subject::where('is_active', true)->get());
        CacheHelper::remember($staffUnitsKey, 3600, fn () => StaffUnit::where('is_active', true)->get());
        CacheHelper::remember($damageCategoriesKey, 3600, fn () => DamageCategory::where('is_active', true)->get());

        // Warm call assertion
        DB::enableQueryLog();
        DB::flushQueryLog();

        $cachedClasses = CacheHelper::remember($classesKey, 3600, fn () => SchoolClass::all());
        $cachedLocations = CacheHelper::remember($locationsKey, 3600, fn () => Location::all());
        $cachedSubjects = CacheHelper::remember($subjectsKey, 3600, fn () => Subject::all());
        $cachedStaffUnits = CacheHelper::remember($staffUnitsKey, 3600, fn () => StaffUnit::all());
        $cachedCategories = CacheHelper::remember($damageCategoriesKey, 3600, fn () => DamageCategory::all());

        $queries = DB::getQueryLog();

        $this->assertCount(
            0,
            $queries,
            'Warm cache hit for public reference data MUST execute 0 database queries!'
        );
        $this->assertNotEmpty($cachedClasses);
        $this->assertNotEmpty($cachedLocations);
        $this->assertNotEmpty($cachedSubjects);
        $this->assertNotEmpty($cachedStaffUnits);
        $this->assertNotEmpty($cachedCategories);
    }

    private function createBatchViolationReports(int $count, SchoolClass $class, Location $location): void
    {
        for ($i = 1; $i <= $count; $i++) {
            $report = Report::create([
                'report_number' => 'LPR' . now()->format('Ym') . substr(str_replace('-', '', (string) Str::uuid()), 0, 8),
                'public_token' => (string) Str::uuid(),
                'access_code_hash' => Hash::make('123456'),
                'reporter_type' => 'siswa',
                'reporter_name' => "Pelapor Perf Violation {$i}",
                'reporter_class_id' => $class->id,
                'reporter_phone' => '081234567890',
                'report_type' => 'violation',
                'title' => "Title Perf Violation {$i}",
                'location_id' => $location->id,
                'incident_date' => now()->toDateString(),
                'description' => "Deskripsi perf violation {$i}.",
                'urgency' => 'sedang',
                'status' => 'menunggu_verifikasi',
                'assigned_to_role' => 'kesiswaan',
                'related_class_id' => $class->id,
                'consent_accepted_at' => now(),
            ]);

            BullyingDetail::create([
                'report_id' => $report->id,
                'alleged_actor_class_id' => $class->id,
                'bullying_type' => 'verbal',
                'alleged_actor_name' => "Pelaku Perf {$i}",
            ]);
        }
    }

    private function createBatchDamageReports(int $count, SchoolClass $class, Location $location, DamageCategory $category): void
    {
        for ($i = 1; $i <= $count; $i++) {
            $report = Report::create([
                'report_number' => 'LPR' . now()->format('Ym') . substr(str_replace('-', '', (string) Str::uuid()), 0, 8),
                'public_token' => (string) Str::uuid(),
                'access_code_hash' => Hash::make('123456'),
                'reporter_type' => 'siswa',
                'reporter_name' => "Pelapor Perf Damage {$i}",
                'reporter_class_id' => $class->id,
                'reporter_phone' => '081234567890',
                'report_type' => 'damage',
                'title' => "Title Perf Damage {$i}",
                'location_id' => $location->id,
                'incident_date' => now()->toDateString(),
                'description' => "Deskripsi perf damage {$i}.",
                'urgency' => 'tinggi',
                'status' => 'menunggu_verifikasi',
                'assigned_to_role' => 'sarpras',
                'related_class_id' => $class->id,
                'consent_accepted_at' => now(),
            ]);

            DamageDetail::create([
                'report_id' => $report->id,
                'item_name' => "Meja Perf {$i}",
                'damage_category_id' => $category->id,
                'damage_condition' => 'sedang',
            ]);
        }
    }
}
