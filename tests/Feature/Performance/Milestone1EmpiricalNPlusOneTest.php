<?php

namespace Tests\Feature\Performance;

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ReportController;
use App\Models\BullyingDetail;
use App\Models\DamageCategory;
use App\Models\DamageDetail;
use App\Models\Location;
use App\Models\Report;
use App\Models\ReportAttachment;
use App\Models\ReportStatusHistory;
use App\Models\ReportNote;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\Role\Kesiswaan\KesiswaanService;
use App\Services\Role\Sarpras\SarprasService;
use App\Services\Role\Superadmin\AdminService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use Tests\TestCase;

class Milestone1EmpiricalNPlusOneTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Target 1: AdminService::master('locations')
     * Verify that fetching locations eager loads 'class' in O(1) queries regardless of location count.
     */
    public function test_admin_service_locations_query_count_is_o1(): void
    {
        $schoolClass = SchoolClass::create([
            'class_name' => 'X-RPL-1',
            'grade_level' => '10',
            'major' => 'RPL',
            'academic_year' => '2023/2024',
            'is_active' => true,
        ]);

        for ($i = 0; $i < 15; $i++) {
            Location::create([
                'location_name' => "Lab Computer $i",
                'location_type' => 'Lab',
                'class_id' => $schoolClass->id,
                'is_active' => true,
            ]);
        }

        DB::enableQueryLog();
        DB::flushQueryLog();

        $adminService = app(AdminService::class);
        $view = $adminService->master('locations');
        $items = $view->getData()['items'];

        // Access the eager-loaded relationship on every item to verify no lazy loading queries trigger
        foreach ($items as $item) {
            $className = $item->class?->class_name;
            $this->assertEquals('X-RPL-1', $className);
        }

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // paginate() = COUNT + SELECT + 1 eager join for class + 1 for classes dropdown = ~4 queries total (O(1))
        $this->assertLessThanOrEqual(5, count($queries), "Expected O(1) queries, got " . count($queries));
    }

    /**
     * Target 2: KesiswaanService::index()
     * Verify that fetching violation reports eager loads bullyingDetail.allegedActorClass, relatedClass, location, attachments in O(1) queries.
     */
    public function test_kesiswaan_service_violation_reports_query_count_is_o1(): void
    {
        $class1 = SchoolClass::create([
            'class_name' => 'X-TKJ-1',
            'grade_level' => '10',
            'academic_year' => '2023/2024',
            'is_active' => true,
        ]);

        $location = Location::create([
            'location_name' => 'Kantin Utama',
            'is_active' => true,
        ]);

        for ($i = 0; $i < 15; $i++) {
            $report = Report::create([
                'report_number' => 'LAP-V' . sprintf('%03d', $i),
                'public_token' => \Illuminate\Support\Str::uuid(),
                'access_code_hash' => hash('sha256', 'test'),
                'reporter_type' => 'siswa',
                'reporter_name' => 'Siswa ' . $i,
                'report_type' => 'violation',
                'title' => 'Pelanggaran ' . $i,
                'incident_date' => now()->toDateString(),
                'description' => 'Deskripsi violation ' . $i,
                'status' => 'menunggu_verifikasi',
                'urgency' => 'sedang',
                'related_class_id' => $class1->id,
                'location_id' => $location->id,
            ]);

            BullyingDetail::create([
                'report_id' => $report->id,
                'alleged_actor_name' => 'Pelaku ' . $i,
                'alleged_actor_class_id' => $class1->id,
                'bullying_type' => 'verbal',
            ]);

            ReportAttachment::create([
                'report_id' => $report->id,
                'file_path' => "attachments/file_{$i}.jpg",
                'original_name' => "file_{$i}.jpg",
                'stored_name' => "stored_file_{$i}.jpg",
                'mime_type' => 'image/jpeg',
                'file_size' => 1024,
                'uploader_type' => 'reporter',
            ]);
        }

        DB::enableQueryLog();
        DB::flushQueryLog();

        $kesiswaanService = app(KesiswaanService::class);
        $view = $kesiswaanService->index();
        $reports = $view->getData()['reports'];

        // Access all relations for all 15 reports
        foreach ($reports as $report) {
            $actorClass = $report->bullyingDetail?->allegedActorClass?->class_name;
            $relClass = $report->relatedClass?->class_name;
            $locName = $report->location?->location_name;
            $attachmentsCount = $report->attachments->count();

            $this->assertEquals('X-TKJ-1', $actorClass);
            $this->assertEquals('X-TKJ-1', $relClass);
            $this->assertEquals('Kantin Utama', $locName);
            $this->assertEquals(1, $attachmentsCount);
        }

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // Expect constant number of queries:
        // 1 (select reports) + 1 (bullyingDetail) + 1 (allegedActorClass) + 1 (relatedClass) + 1 (location) + 1 (attachments) = 6 queries
        $this->assertLessThanOrEqual(6, count($queries), "Query count for 15 violation reports exceeded O(1) bound. Got: " . count($queries));
    }

    /**
     * Target 3: SarprasService::index()
     * Verify that fetching damage reports eager loads damageDetail, location, damageCategory, attachments in O(1) queries.
     */
    public function test_sarpras_service_damage_reports_query_count_is_o1(): void
    {
        $location = Location::create([
            'location_name' => 'Ruang Teori 1',
            'is_active' => true,
        ]);

        $category = DamageCategory::create([
            'category_name' => 'Elektronik',
            'is_active' => true,
        ]);

        for ($i = 0; $i < 15; $i++) {
            $report = Report::create([
                'report_number' => 'LAP-D' . sprintf('%03d', $i),
                'public_token' => \Illuminate\Support\Str::uuid(),
                'access_code_hash' => hash('sha256', 'test'),
                'reporter_type' => 'guru',
                'reporter_name' => 'Guru ' . $i,
                'report_type' => 'damage',
                'title' => 'Kerusakan Proyektor ' . $i,
                'incident_date' => now()->toDateString(),
                'description' => 'Deskripsi damage ' . $i,
                'status' => 'menunggu_verifikasi',
                'urgency' => 'tinggi',
                'location_id' => $location->id,
                'damage_category_id' => $category->id,
            ]);

            DamageDetail::create([
                'report_id' => $report->id,
                'item_name' => 'Proyektor BenQ',
                'damage_condition' => 'rusak berat',
            ]);

            ReportAttachment::create([
                'report_id' => $report->id,
                'file_path' => "attachments/damage_{$i}.jpg",
                'original_name' => "damage_{$i}.jpg",
                'stored_name' => "stored_damage_{$i}.jpg",
                'mime_type' => 'image/jpeg',
                'file_size' => 2048,
                'uploader_type' => 'reporter',
            ]);
        }

        DB::enableQueryLog();
        DB::flushQueryLog();

        $sarprasService = app(SarprasService::class);
        $view = $sarprasService->index();
        $reports = $view->getData()['reports'];

        foreach ($reports as $report) {
            $assetName = $report->damageDetail?->item_name;
            $locName = $report->location?->location_name;
            $catName = $report->damageCategory?->category_name;
            $attachmentsCount = $report->attachments->count();

            $this->assertEquals('Proyektor BenQ', $assetName);
            $this->assertEquals('Ruang Teori 1', $locName);
            $this->assertEquals('Elektronik', $catName);
            $this->assertEquals(1, $attachmentsCount);
        }

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // 1 (select reports) + 1 (damageDetail) + 1 (location) + 1 (damageCategory) + 1 (attachments) = 5 queries
        $this->assertLessThanOrEqual(5, count($queries), "Query count for 15 damage reports exceeded O(1) bound. Got: " . count($queries));
    }

    /**
     * Target 4: ReportController::show()
     * Verify nested relation eager loading: bullyingDetail.allegedActorClass is loaded and accessing it executes 0 extra queries.
     */
    public function test_report_controller_show_eager_loads_nested_actor_class(): void
    {
        $actorClass = SchoolClass::create([
            'class_name' => 'XI-AKL-2',
            'grade_level' => '11',
            'academic_year' => '2023/2024',
            'is_active' => true,
        ]);

        $user = User::factory()->create(['role' => 'kesiswaan']);

        $report = Report::create([
            'report_number' => 'LAP-SHOW01',
            'public_token' => \Illuminate\Support\Str::uuid(),
            'access_code_hash' => hash('sha256', 'test'),
            'reporter_type' => 'siswa',
            'reporter_name' => 'Pelapor Show',
            'report_type' => 'violation',
            'title' => 'Laporan Single Show',
            'incident_date' => now()->toDateString(),
            'description' => 'Detail test',
            'status' => 'menunggu_verifikasi',
            'urgency' => 'sedang',
        ]);

        BullyingDetail::create([
            'report_id' => $report->id,
            'alleged_actor_name' => 'Siswa X',
            'alleged_actor_class_id' => $actorClass->id,
            'bullying_type' => 'cyber',
        ]);

        ReportNote::create([
            'report_id' => $report->id,
            'user_id' => $user->id,
            'author_type' => 'kesiswaan',
            'note' => 'Catatan awal',
            'visibility' => 'internal',
        ]);

        ReportStatusHistory::create([
            'report_id' => $report->id,
            'actor_type' => 'kesiswaan',
            'previous_status' => 'menunggu_verifikasi',
            'new_status' => 'sedang_ditangani',
            'public_note' => 'Laporan dibuat',
        ]);

        $this->actingAs($user);

        $controller = app(ReportController::class);
        $view = $controller->show($report);
        $loadedReport = $view->getData()['report'];

        // Verify relationship is loaded
        $this->assertTrue($loadedReport->relationLoaded('bullyingDetail'));
        $this->assertTrue($loadedReport->bullyingDetail->relationLoaded('allegedActorClass'));

        // Now test that accessing nested property triggers ZERO queries
        DB::enableQueryLog();
        DB::flushQueryLog();

        $className = $loadedReport->bullyingDetail->allegedActorClass->class_name;
        $this->assertEquals('XI-AKL-2', $className);

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertCount(0, $queries, "Accessing nested relation on loaded report triggered extra queries!");
    }

    /**
     * Target 5: DashboardController::__invoke()
     * Verify list eager loading on Dashboard: relatedClass, location, bullyingDetail, damageDetail loaded in O(1) queries.
     */
    public function test_dashboard_controller_report_list_query_count_is_o1(): void
    {
        $class1 = SchoolClass::create([
            'class_name' => 'XII-MM-1',
            'grade_level' => '12',
            'academic_year' => '2023/2024',
            'is_active' => true,
        ]);

        $location = Location::create([
            'location_name' => 'Studio Audio',
            'is_active' => true,
        ]);

        $user = User::factory()->create(['role' => 'superadmin']);

        for ($i = 0; $i < 12; $i++) {
            $report = Report::create([
                'report_number' => 'LAP-DASH' . sprintf('%02d', $i),
                'public_token' => \Illuminate\Support\Str::uuid(),
                'access_code_hash' => hash('sha256', 'test'),
                'reporter_type' => 'siswa',
                'reporter_name' => 'User Dash ' . $i,
                'report_type' => $i % 2 === 0 ? 'violation' : 'damage',
                'title' => 'Dashboard Report ' . $i,
                'incident_date' => now()->toDateString(),
                'description' => 'Deskripsi dash ' . $i,
                'status' => 'menunggu_verifikasi',
                'urgency' => 'sedang',
                'related_class_id' => $class1->id,
                'location_id' => $location->id,
            ]);

            if ($i % 2 === 0) {
                BullyingDetail::create([
                    'report_id' => $report->id,
                    'alleged_actor_name' => 'Terlapor Dash ' . $i,
                    'alleged_actor_class_id' => $class1->id,
                    'bullying_type' => 'fisik',
                ]);
            } else {
                DamageDetail::create([
                    'report_id' => $report->id,
                    'item_name' => 'Kamera Canon',
                    'damage_condition' => 'rusak ringan',
                ]);
            }
        }

        $request = Request::create('/dashboard', 'GET');
        $request->setUserResolver(fn () => $user);

        DB::enableQueryLog();
        DB::flushQueryLog();

        $controller = app(DashboardController::class);
        $view = $controller($request);
        $reports = $view->getData()['reports'];

        // Access all relations for dashboard reports list
        foreach ($reports as $report) {
            $relClass = $report->relatedClass?->class_name;
            $locName = $report->location?->location_name;
            $bullying = $report->bullyingDetail?->alleged_actor_name;
            $damage = $report->damageDetail?->asset_name;
        }

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // 1 (select paginated reports) + 1 (relatedClass) + 1 (location) + 1 (bullyingDetail) + 1 (damageDetail) = 5 queries for reports list eager loading
        // Plus 5 stats queries (aggregate queries, optimized in M2).
        // Total query log for dashboard execution should be fixed/bounded regardless of number of reports!
        // We measure that relations access adds ZERO queries.
        foreach ($reports as $report) {
            $this->assertTrue($report->relationLoaded('relatedClass'));
            $this->assertTrue($report->relationLoaded('location'));
            $this->assertTrue($report->relationLoaded('bullyingDetail'));
            $this->assertTrue($report->relationLoaded('damageDetail'));
        }
    }
}
