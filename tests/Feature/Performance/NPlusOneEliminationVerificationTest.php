<?php

namespace Tests\Feature\Performance;

use App\Models\BullyingDetail;
use App\Models\DamageCategory;
use App\Models\DamageDetail;
use App\Models\Location;
use App\Models\Report;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\Role\Kesiswaan\KesiswaanService;
use App\Services\Role\Sarpras\SarprasService;
use App\Services\Role\Superadmin\AdminService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class NPlusOneEliminationVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    /**
     * Target 1: AdminService::master('locations') eager loads 'class'
     */
    public function test_admin_service_locations_eager_loads_class(): void
    {
        $class = SchoolClass::first();
        for ($i = 0; $i < 10; $i++) {
            Location::create([
                'location_name' => 'Ruang Test ' . $i,
                'location_type' => 'classroom',
                'class_id' => $class->id,
                'is_active' => true,
            ]);
        }

        $adminService = app(AdminService::class);
        $view = $adminService->master('locations');
        $items = $view->getData()['items'];

        DB::flushQueryLog();
        DB::enableQueryLog();

        // Accessing the relation should not trigger additional queries because it was eager-loaded
        foreach ($items as $item) {
            // Only assert class is loaded (not null) for items that have a class_id
            $className = $item->class?->class_name;
            if ($item->class_id !== null) {
                $this->assertNotNull($className);
            }
        }

        $queriesAfterAccess = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertCount(0, $queriesAfterAccess, 'Accessing location->class triggered N+1 queries instead of being preloaded!');
    }

    /**
     * Target 2: KesiswaanService::index() eager loads relations
     */
    public function test_kesiswaan_service_index_eager_loads_relations(): void
    {
        $class = SchoolClass::first();
        $location = Location::first();

        for ($i = 0; $i < 10; $i++) {
            $report = Report::create([
                'report_number' => 'LPR-VIO-' . Str::random(5),
                'public_token' => (string) Str::uuid(),
                'access_code_hash' => bcrypt('secret'),
                'reporter_type' => 'siswa',
                'reporter_name' => 'Siswa ' . $i,
                'report_type' => 'violation',
                'title' => 'Laporan Perundungan ' . $i,
                'related_class_id' => $class->id,
                'location_id' => $location->id,
                'incident_date' => now()->toDateString(),
                'description' => 'Deskripsi perundungan',
                'urgency' => 'sedang',
                'status' => 'menunggu_verifikasi',
            ]);

            BullyingDetail::create([
                'report_id' => $report->id,
                'alleged_actor_class_id' => $class->id,
                'bullying_type' => 'verbal',
                'alleged_actor_name' => 'Pelaku ' . $i,
            ]);
        }

        $kesiswaanService = app(KesiswaanService::class);
        $view = $kesiswaanService->index();
        $reports = $view->getData()['reports'];

        DB::flushQueryLog();
        DB::enableQueryLog();

        foreach ($reports as $r) {
            $actorClass = $r->bullyingDetail?->allegedActorClass?->class_name;
            $relatedClass = $r->relatedClass?->class_name;
            $locName = $r->location?->location_name;
            $attachmentsCount = $r->attachments->count();
        }

        $queriesAfterAccess = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertCount(0, $queriesAfterAccess, 'Accessing kesiswaan report relations triggered N+1 queries!');
    }

    /**
     * Target 3: SarprasService::index() eager loads relations
     */
    public function test_sarpras_service_index_eager_loads_relations(): void
    {
        $location = Location::first();
        $category = DamageCategory::first();

        for ($i = 0; $i < 10; $i++) {
            $report = Report::create([
                'report_number' => 'LPR-DMG-' . Str::random(5),
                'public_token' => (string) Str::uuid(),
                'access_code_hash' => bcrypt('secret'),
                'reporter_type' => 'siswa',
                'reporter_name' => 'Pelapor Sarpras ' . $i,
                'report_type' => 'damage',
                'title' => 'Kerusakan Sarpras ' . $i,
                'location_id' => $location->id,
                'damage_category_id' => $category->id,
                'incident_date' => now()->toDateString(),
                'description' => 'Kerusakan sarpras deskripsi',
                'urgency' => 'sedang',
                'status' => 'menunggu_verifikasi',
            ]);

            DamageDetail::create([
                'report_id' => $report->id,
                'item_name' => 'Meja Rusak ' . $i,
                'damage_condition' => 'rusak sedang',
            ]);
        }

        $sarprasService = app(SarprasService::class);
        $view = $sarprasService->index();
        $reports = $view->getData()['reports'];

        DB::flushQueryLog();
        DB::enableQueryLog();

        foreach ($reports as $r) {
            $detailItem = $r->damageDetail?->item_name;
            $locName = $r->location?->location_name;
            $catName = $r->damageCategory?->category_name;
            $attachCount = $r->attachments->count();
        }

        $queriesAfterAccess = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertCount(0, $queriesAfterAccess, 'Accessing sarpras report relations triggered N+1 queries!');
    }

    /**
     * Target 4: ReportController::show() eager loads bullyingDetail.allegedActorClass
     */
    public function test_report_controller_show_eager_loads_nested_alleged_actor_class(): void
    {
        $superadmin = User::where('role', 'superadmin')->first();
        $class = SchoolClass::first();
        $location = Location::first();

        $report = Report::create([
            'report_number' => 'LPR-SHOW-' . Str::random(5),
            'public_token' => (string) Str::uuid(),
            'access_code_hash' => bcrypt('secret'),
            'reporter_type' => 'siswa',
            'reporter_name' => 'Pelapor Show',
            'report_type' => 'violation',
            'title' => 'Violation Detail Test',
            'location_id' => $location->id,
            'incident_date' => now()->toDateString(),
            'description' => 'Description test',
            'urgency' => 'sedang',
            'status' => 'menunggu_verifikasi',
        ]);

        $bullying = BullyingDetail::create([
            'report_id' => $report->id,
            'alleged_actor_class_id' => $class->id,
            'category' => 'verbal',
            'alleged_actor_name' => 'Pelaku Test',
        ]);

        $response = $this->actingAs($superadmin)->get(route('reports.show', $report));
        $response->assertOk();

        /** @var Report $loadedReport */
        $loadedReport = $response->viewData('report');
        $this->assertTrue($loadedReport->relationLoaded('bullyingDetail'), 'bullyingDetail relation not loaded');
        $this->assertTrue($loadedReport->bullyingDetail->relationLoaded('allegedActorClass'), 'allegedActorClass relation not loaded in bullyingDetail');

        DB::flushQueryLog();
        DB::enableQueryLog();

        $className = $loadedReport->bullyingDetail->allegedActorClass?->class_name;
        $this->assertNotNull($className);

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertCount(0, $queries, 'Accessing allegedActorClass in report detail triggered N+1 query!');
    }

    /**
     * Target 5: DashboardController eager loads report list relations
     */
    public function test_dashboard_controller_eager_loads_report_list_relations(): void
    {
        $superadmin = User::where('role', 'superadmin')->first();
        $class = SchoolClass::first();
        $location = Location::first();

        for ($i = 0; $i < 5; $i++) {
            $r = Report::create([
                'report_number' => 'DASH-' . Str::random(5),
                'public_token' => (string) Str::uuid(),
                'access_code_hash' => bcrypt('secret'),
                'reporter_type' => 'siswa',
                'reporter_name' => 'Pelapor Dash ' . $i,
                'report_type' => 'violation',
                'title' => 'Dash Violation ' . $i,
                'related_class_id' => $class->id,
                'location_id' => $location->id,
                'incident_date' => now()->toDateString(),
                'description' => 'Description dash',
                'urgency' => 'sedang',
                'status' => 'menunggu_verifikasi',
            ]);
            BullyingDetail::create([
                'report_id' => $r->id,
                'alleged_actor_class_id' => $class->id,
                'bullying_type' => 'cyber',
                'alleged_actor_name' => 'Actor ' . $i,
            ]);
        }

        $response = $this->actingAs($superadmin)->get(route('dashboard'));
        $response->assertOk();

        $reports = $response->viewData('reports');

        DB::flushQueryLog();
        DB::enableQueryLog();

        foreach ($reports as $r) {
            $relClass = $r->relatedClass?->class_name;
            $loc = $r->location?->location_name;
            // Note: allegedActorClass is a nested relation only eager-loaded in report detail, not dashboard list
            $dmgItem = $r->damageDetail?->item_name;
        }

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(5, count($queries), 'Accessing dashboard report item relations triggered N+1 queries!');
    }
}
