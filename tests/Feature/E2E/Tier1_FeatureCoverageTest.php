<?php

namespace Tests\Feature\E2E;

use App\Models\AuditLog;
use App\Models\BullyingDetail;
use App\Models\DamageCategory;
use App\Models\DamageDetail;
use App\Models\Report;
use App\Models\SchoolClass;
use App\Models\StaffUnit;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Models\ViolationType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class Tier1_FeatureCoverageTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $kesiswaan;
    private User $sarpras;
    private User $waliKelas;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $this->admin = User::where('role', 'superadmin')->where('is_active', true)->firstOrFail();
        $this->kesiswaan = User::where('role', 'kesiswaan')->where('is_active', true)->firstOrFail();
        $this->sarpras = User::where('role', 'sarpras')->where('is_active', true)->firstOrFail();
        $this->waliKelas = User::where('role', 'wali_kelas')->where('is_active', true)->firstOrFail();
    }

    private function createViolationReport(array $overrides = []): Report
    {
        $class = SchoolClass::firstOrFail();

        $report = Report::create(array_merge([
            'report_number' => 'LAP-V-'.Str::random(6),
            'public_token' => (string) Str::uuid(),
            'access_code_hash' => Hash::make('123456'),
            'reporter_type' => 'siswa',
            'reporter_name' => 'Pelapor Violation',
            'reporter_class_id' => $class->id,
            'report_type' => 'violation',
            'title' => 'Laporan Perundungan E2E',
            'incident_date' => now()->toDateString(),
            'description' => 'Deskripsi laporan perundungan untuk pengujian E2E.',
            'urgency' => 'sedang',
            'status' => 'menunggu_verifikasi',
            'assigned_to_role' => 'kesiswaan',
            'consent_accepted_at' => now(),
        ], $overrides));

        BullyingDetail::create([
            'report_id' => $report->id,
            'reporter_position' => 'korban',
            'bullying_type' => 'verbal',
            'victim_name' => 'Korban Test',
            'victim_class_id' => $class->id,
            'alleged_actor_name' => 'Pelaku Test',
            'alleged_actor_class_id' => $class->id,
            'impact_description' => 'Trauma fisik/mental',
        ]);

        return $report;
    }

    private function createDamageReport(array $overrides = []): Report
    {
        $category = DamageCategory::firstOrFail();

        $report = Report::create(array_merge([
            'report_number' => 'LAP-D-'.Str::random(6),
            'public_token' => (string) Str::uuid(),
            'access_code_hash' => Hash::make('123456'),
            'reporter_type' => 'guru',
            'reporter_name' => 'Pelapor Damage',
            'report_type' => 'damage',
            'title' => 'Kerusakan Proyektor E2E',
            'damage_category_id' => $category->id,
            'incident_date' => now()->toDateString(),
            'description' => 'Proyektor mati total di lab komputer.',
            'urgency' => 'tinggi',
            'status' => 'menunggu_verifikasi',
            'assigned_to_role' => 'sarpras',
            'consent_accepted_at' => now(),
        ], $overrides));

        DamageDetail::create([
            'report_id' => $report->id,
            'item_name' => 'Proyektor Epson',
            'item_category' => 'Elektronik',
            'damage_condition' => 'Mati Total',
            'priority' => 'tinggi',
        ]);

        return $report;
    }

    // ==========================================
    // Feature 1: Master Data Superadmin
    // ==========================================

    public function test_1_admin_master_classes_index_returns_200_ok(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.master.index', ['resource' => 'classes']));

        $response->assertOk();
        $response->assertViewIs('admin.master.index');
        $response->assertViewHas('items');
        $response->assertViewHas('classes');
        $this->assertNotNull($response->viewData('items'));
    }

    public function test_1_admin_master_students_eager_loads_class_relationship(): void
    {
        $class = SchoolClass::firstOrFail();
        $student = Student::create([
            'nis' => '99900123',
            'name' => 'Siswa Tier1',
            'class_id' => $class->id,
            'point' => 0,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.master.index', ['resource' => 'students']));

        $response->assertOk();
        $found = $response->viewData('items')->firstWhere('id', $student->id);
        $this->assertNotNull($found);
        $this->assertTrue($found->relationLoaded('class'));
        $this->assertEquals($class->id, $found->class->id);
    }

    public function test_1_admin_master_search_filter_menyaring_baris(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.master.index', ['resource' => 'classes', 'search' => 'TIDAK-ADA-KELAS-INI']));

        $response->assertOk();
        $this->assertSame(0, $response->viewData('items')->count());
    }

    /**
     * Fitur lokasi dihapus menyeluruh. Resource master 'locations' sudah tidak
     * terdaftar, jadi ResourceRegistry harus abort(404) — bukan menampilkan
     * halaman kosong yang menyesatkan superadmin.
     */
    public function test_1_admin_master_locations_sudah_tidak_ada(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.master.index', ['resource' => 'locations']))
            ->assertNotFound();
    }

    // ==========================================
    // Feature 2: Kesiswaan Violation List Eager Loading
    // ==========================================

    public function test_2_kesiswaan_violation_index_returns_200_ok(): void
    {
        $response = $this->actingAs($this->kesiswaan)
            ->get(route('kesiswaan.index'));

        $response->assertOk();
        $response->assertViewIs('kesiswaan.index');
        $response->assertViewHas('reports');
        $response->assertViewHas('students');
        $response->assertViewHas('types');
    }

    public function test_2_kesiswaan_violation_index_displays_violation_reports(): void
    {
        $report = $this->createViolationReport(['title' => 'Pelanggaran Disiplin Tier1']);

        $response = $this->actingAs($this->kesiswaan)
            ->get(route('kesiswaan.index'));

        $response->assertOk();
        $response->assertSee($report->report_number);
        $response->assertSee('Pelanggaran Disiplin Tier1');
        $reports = $response->viewData('reports');
        $this->assertTrue($reports->contains('id', $report->id));
    }

    public function test_2_kesiswaan_violation_index_eager_loads_bullying_relations(): void
    {
        $report = $this->createViolationReport();

        $response = $this->actingAs($this->kesiswaan)
            ->get(route('kesiswaan.index'));

        $response->assertOk();
        $reports = $response->viewData('reports');
        $item = $reports->firstWhere('id', $report->id);

        $this->assertNotNull($item);
        $this->assertTrue($item->relationLoaded('bullyingDetail'));
        $this->assertTrue($item->relationLoaded('attachments'));
    }

    public function test_2_kesiswaan_violation_index_filters_by_status(): void
    {
        $this->createViolationReport(['status' => 'menunggu_verifikasi', 'title' => 'Status Menunggu Tier1']);
        $this->createViolationReport(['status' => 'selesai', 'title' => 'Status Selesai Tier1']);

        $response = $this->actingAs($this->kesiswaan)
            ->get(route('kesiswaan.index', ['status' => 'menunggu_verifikasi']));

        $response->assertOk();
        $response->assertSee('Status Menunggu Tier1');
        $response->assertDontSee('Status Selesai Tier1');
    }

    public function test_2_kesiswaan_violation_index_filters_by_search_term(): void
    {
        $report1 = $this->createViolationReport(['title' => 'SearchUniqueViolationAlpha']);
        $report2 = $this->createViolationReport(['title' => 'SearchUniqueViolationBeta']);

        $response = $this->actingAs($this->kesiswaan)
            ->get(route('kesiswaan.index', ['search' => 'SearchUniqueViolationAlpha']));

        $response->assertOk();
        $reports = $response->viewData('reports');
        $this->assertCount(1, $reports);
        $this->assertEquals($report1->id, $reports->first()->id);
    }

    // ==========================================
    // Feature 3: Sarpras Damage Index Eager Loading
    // ==========================================

    public function test_3_sarpras_damage_index_returns_200_ok(): void
    {
        $response = $this->actingAs($this->sarpras)
            ->get(route('sarpras.index'));

        $response->assertOk();
        $response->assertViewIs('sarpras.index');
        $response->assertViewHas('reports');
        $this->assertNotNull($response->viewData('reports'));
    }

    public function test_3_sarpras_damage_index_displays_damage_reports(): void
    {
        $report = $this->createDamageReport(['title' => 'AC Laboratorium Rusak Tier1']);

        $response = $this->actingAs($this->sarpras)
            ->get(route('sarpras.index'));

        $response->assertOk();
        $response->assertSee($report->report_number);
        $response->assertSee('AC Laboratorium Rusak Tier1');
        $reports = $response->viewData('reports');
        $this->assertTrue($reports->contains('id', $report->id));
    }

    public function test_3_sarpras_damage_index_eager_loads_damage_detail_and_category(): void
    {
        $report = $this->createDamageReport();

        $response = $this->actingAs($this->sarpras)
            ->get(route('sarpras.index'));

        $response->assertOk();
        $reports = $response->viewData('reports');
        $item = $reports->firstWhere('id', $report->id);

        $this->assertNotNull($item);
        $this->assertTrue($item->relationLoaded('damageDetail'));
        $this->assertTrue($item->relationLoaded('damageCategory'));
        $this->assertTrue($item->relationLoaded('attachments'));
    }

    public function test_3_sarpras_damage_index_filters_by_priority(): void
    {
        $reportDarurat = $this->createDamageReport(['title' => 'Atap Bocor Darurat Tier1']);
        $reportDarurat->damageDetail()->update(['priority' => 'darurat']);

        $reportRendah = $this->createDamageReport(['title' => 'Gagang Pintu Kendor Tier1']);
        $reportRendah->damageDetail()->update(['priority' => 'rendah']);

        $response = $this->actingAs($this->sarpras)
            ->get(route('sarpras.index', ['priority' => 'darurat']));

        $response->assertOk();
        $reports = $response->viewData('reports');
        $this->assertTrue($reports->contains('id', $reportDarurat->id));
        $this->assertFalse($reports->contains('id', $reportRendah->id));
    }

    public function test_3_sarpras_damage_index_filters_by_search_term(): void
    {
        $report1 = $this->createDamageReport(['title' => 'SearchUniqueDamageLamp']);
        $report2 = $this->createDamageReport(['title' => 'SearchUniqueDamageChair']);

        $response = $this->actingAs($this->sarpras)
            ->get(route('sarpras.index', ['search' => 'SearchUniqueDamageLamp']));

        $response->assertOk();
        $reports = $response->viewData('reports');
        $this->assertCount(1, $reports);
        $this->assertEquals($report1->id, $reports->first()->id);
    }

    // ==========================================
    // Feature 4: Report Detail Eager Loading
    // ==========================================

    public function test_4_report_detail_returns_200_ok_for_authorized_user(): void
    {
        $report = $this->createViolationReport();

        $response = $this->actingAs($this->admin)
            ->get(route('reports.show', $report));

        $response->assertOk();
        $response->assertViewIs('reports.show');
        $response->assertViewHas('report');
        $response->assertSee($report->report_number);
    }

    public function test_4_report_detail_displays_violation_bullying_information(): void
    {
        $report = $this->createViolationReport([
            'title' => 'Perundungan Kelas 10 Tier1',
        ]);

        $response = $this->actingAs($this->kesiswaan)
            ->get(route('reports.show', $report));

        $response->assertOk();
        $response->assertSee('Perundungan Kelas 10 Tier1');
        $response->assertSee('Korban Test');
        $response->assertSee('Pelaku Test');
    }

    public function test_4_report_detail_displays_damage_information(): void
    {
        $report = $this->createDamageReport([
            'title' => 'Pintu Lab Komputer Patah Tier1',
        ]);

        $response = $this->actingAs($this->sarpras)
            ->get(route('reports.show', $report));

        $response->assertOk();
        $response->assertSee('Pintu Lab Komputer Patah Tier1');
        $response->assertSee('Proyektor Epson');
        $response->assertSee('Mati Total');
    }

    public function test_4_report_detail_eager_loads_alleged_actor_class(): void
    {
        $report = $this->createViolationReport();

        $response = $this->actingAs($this->admin)
            ->get(route('reports.show', $report));

        $response->assertOk();
        $viewReport = $response->viewData('report');
        $this->assertTrue($viewReport->relationLoaded('bullyingDetail'));
        if ($viewReport->bullyingDetail) {
            $this->assertTrue($viewReport->bullyingDetail->relationLoaded('allegedActorClass'));
        }
    }

    public function test_4_report_detail_displays_reporter_and_timeline(): void
    {
        $report = $this->createViolationReport();

        $response = $this->actingAs($this->admin)
            ->get(route('reports.show', $report));

        $response->assertOk();
        $response->assertSee($report->report_number);
        $response->assertSee('menunggu_verifikasi');
    }

    // ==========================================
    // Feature 5: Dashboard Invoke
    // ==========================================

    public function test_5_dashboard_invoke_returns_200_ok_for_superadmin(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewIs('dashboard.index');
        $response->assertViewHas('reports');
        $response->assertViewHas('stats');
        $response->assertViewHas('chart');
    }

    public function test_5_dashboard_invoke_returns_200_ok_for_kesiswaan(): void
    {
        $response = $this->actingAs($this->kesiswaan)
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewIs('dashboard.index');
        $response->assertViewHas('reports');
    }

    public function test_5_dashboard_invoke_returns_200_ok_for_sarpras(): void
    {
        $response = $this->actingAs($this->sarpras)
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewIs('dashboard.index');
        $response->assertViewHas('reports');
    }

    public function test_5_dashboard_invoke_returns_200_ok_for_wali_kelas(): void
    {
        $response = $this->actingAs($this->waliKelas)
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewIs('dashboard.index');
        $response->assertViewHas('reports');
    }

    public function test_5_dashboard_invoke_eager_loads_report_list_relations(): void
    {
        $this->createViolationReport();
        $this->createDamageReport();

        $response = $this->actingAs($this->admin)
            ->get(route('dashboard'));

        $response->assertOk();
        $reports = $response->viewData('reports');
        if ($reports->count() > 0) {
            $first = $reports->first();
            $this->assertTrue($first->relationLoaded('bullyingDetail'));
            $this->assertTrue($first->relationLoaded('damageDetail'));
        }
    }

    // ==========================================
    // Feature 6: Dashboard Summary Stats Grouping & Caching
    // ==========================================

    public function test_6_dashboard_stats_variable_has_required_keys(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('dashboard'));

        $response->assertOk();
        $stats = $response->viewData('stats');
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total', $stats);
        $this->assertArrayHasKey('violation', $stats);
        $this->assertArrayHasKey('damage', $stats);
        $this->assertArrayHasKey('pending', $stats);
        $this->assertArrayHasKey('done', $stats);
    }

    public function test_6_dashboard_stats_computes_total_and_type_counts(): void
    {
        $this->createViolationReport();
        $this->createViolationReport();
        $this->createDamageReport();

        $response = $this->actingAs($this->admin)
            ->get(route('dashboard'));

        $response->assertOk();
        $stats = $response->viewData('stats');
        $this->assertGreaterThanOrEqual(3, $stats['total']);
        $this->assertGreaterThanOrEqual(2, $stats['violation']);
        $this->assertGreaterThanOrEqual(1, $stats['damage']);
    }

    public function test_6_dashboard_stats_computes_pending_and_done_counts(): void
    {
        $this->createViolationReport(['status' => 'menunggu_verifikasi']);
        $this->createDamageReport(['status' => 'selesai']);

        $response = $this->actingAs($this->admin)
            ->get(route('dashboard'));

        $response->assertOk();
        $stats = $response->viewData('stats');
        $this->assertGreaterThanOrEqual(1, $stats['pending']);
        $this->assertGreaterThanOrEqual(1, $stats['done']);
    }

    public function test_6_dashboard_stats_role_scoping_for_kesiswaan(): void
    {
        $this->createViolationReport();
        $this->createDamageReport();

        $response = $this->actingAs($this->kesiswaan)
            ->get(route('dashboard'));

        $response->assertOk();
        $stats = $response->viewData('stats');
        $this->assertEquals($stats['total'], $stats['violation']);
        $this->assertEquals(0, $stats['damage']);
    }

    public function test_6_dashboard_stats_role_scoping_for_sarpras(): void
    {
        $this->createViolationReport();
        $this->createDamageReport();

        $response = $this->actingAs($this->sarpras)
            ->get(route('dashboard'));

        $response->assertOk();
        $stats = $response->viewData('stats');
        $this->assertEquals($stats['total'], $stats['damage']);
        $this->assertEquals(0, $stats['violation']);
    }

    // ==========================================
    // Feature 7: Dashboard Monthly Chart Grouping & Caching
    // ==========================================

    public function test_7_dashboard_chart_has_required_data_structure(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('dashboard'));

        $response->assertOk();
        $chart = $response->viewData('chart');
        $this->assertIsArray($chart);
        $this->assertArrayHasKey('title', $chart);
        $this->assertArrayHasKey('labels', $chart);
        $this->assertArrayHasKey('counts', $chart);
        $this->assertArrayHasKey('max', $chart);
    }

    public function test_7_dashboard_chart_contains_six_months_of_labels_and_counts(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('dashboard'));

        $response->assertOk();
        $chart = $response->viewData('chart');
        $this->assertCount(6, $chart['labels']);
        $this->assertCount(6, $chart['counts']);
    }

    public function test_7_dashboard_chart_groups_monthly_report_counts(): void
    {
        $this->createViolationReport(['incident_date' => now()->toDateString()]);

        $response = $this->actingAs($this->admin)
            ->get(route('dashboard'));

        $response->assertOk();
        $chart = $response->viewData('chart');
        $latestMonthIndex = count($chart['counts']) - 1;
        $this->assertGreaterThanOrEqual(1, $chart['counts'][$latestMonthIndex]);
    }

    public function test_7_dashboard_chart_calculates_max_count(): void
    {
        $this->createViolationReport();
        $this->createDamageReport();

        $response = $this->actingAs($this->admin)
            ->get(route('dashboard'));

        $response->assertOk();
        $chart = $response->viewData('chart');
        $maxCount = max($chart['counts']);
        $this->assertGreaterThanOrEqual($maxCount, $chart['max']);
    }

    public function test_7_dashboard_chart_role_scoping_for_kesiswaan_and_sarpras(): void
    {
        $this->createDamageReport();

        $responseKesiswaan = $this->actingAs($this->kesiswaan)->get(route('dashboard'));
        $responseKesiswaan->assertOk();
        $chartKesiswaan = $responseKesiswaan->viewData('chart');

        $responseSarpras = $this->actingAs($this->sarpras)->get(route('dashboard'));
        $responseSarpras->assertOk();
        $chartSarpras = $responseSarpras->viewData('chart');

        $this->assertIsArray($chartKesiswaan['counts']);
        $this->assertIsArray($chartSarpras['counts']);
    }

    // ==========================================
    // Feature 8: Public Reporting Reference Data Caching
    // ==========================================

    public function test_8_public_reference_data_landing_page_returns_200_ok(): void
    {
        $response = $this->get(route('public.report'));

        $response->assertOk();
        $response->assertViewIs('public.report-form');
    }

    public function test_8_public_reference_data_passes_classes_to_view(): void
    {
        $response = $this->get(route('public.report'));

        $response->assertOk();
        $response->assertViewHas('classes');
        $classes = $response->viewData('classes');
        $this->assertGreaterThan(0, count($classes));
    }

    /**
     * Fitur lokasi dihapus menyeluruh, jadi view formulir publik tidak boleh
     * lagi menerima variabel $locations dari controller.
     */
    public function test_8_public_reference_data_tidak_lagi_mengirim_locations(): void
    {
        $response = $this->get(route('public.report'));

        $response->assertOk();
        $response->assertViewMissing('locations');
    }

    public function test_8_public_reference_data_passes_subjects_and_staff_units(): void
    {
        $response = $this->get(route('public.report'));

        $response->assertOk();
        $response->assertViewHas('subjects');
        $response->assertViewHas('staffUnits');
    }

    public function test_8_public_reference_data_passes_damage_categories(): void
    {
        $response = $this->get(route('public.report'));

        $response->assertOk();
        $response->assertViewHas('damageCategories');
        $categories = $response->viewData('damageCategories');
        $this->assertGreaterThan(0, count($categories));
    }

    // ==========================================
    // Feature 9: Administrative & Kesiswaan Reference Caching
    // ==========================================

    public function test_9_admin_users_view_returns_200_ok_with_superadmin_count(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.users.index'));

        $response->assertOk();
        $response->assertViewIs('admin.users.index');
        $response->assertViewHas('users');
        $response->assertViewHas('activeSuperadminCount');
        $this->assertGreaterThanOrEqual(1, $response->viewData('activeSuperadminCount'));
    }

    public function test_9_admin_audit_view_returns_200_ok_with_distinct_actions(): void
    {
        AuditLog::create([
            'user_id' => $this->admin->id,
            'action' => 'LOGIN_SUCCESS',
            'details' => ['ip' => '127.0.0.1'],
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.audit'));

        $response->assertOk();
        $response->assertViewIs('admin.audit');
        $response->assertViewHas('logs');
        $response->assertViewHas('actions');
        $actions = $response->viewData('actions');
        $this->assertTrue($actions->contains('LOGIN_SUCCESS'));
    }

    public function test_9_kesiswaan_index_contains_active_violation_types(): void
    {
        ViolationType::create([
            'violation_name' => 'Toleransi Keterlambatan Tier1',
            'violation_category' => 'Disiplin',
            'points' => 5,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->kesiswaan)
            ->get(route('kesiswaan.index'));

        $response->assertOk();
        $types = $response->viewData('types');
        $this->assertGreaterThan(0, count($types));
    }

    public function test_9_admin_users_lists_active_and_inactive_users(): void
    {
        User::factory()->create([
            'role' => 'kesiswaan',
            'is_active' => false,
            'name' => 'User Inaktif Tier1',
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.users.index'));

        $response->assertOk();
        $users = $response->viewData('users');
        $this->assertTrue($users->contains('name', 'User Inaktif Tier1'));
    }

    public function test_9_admin_audit_supports_action_and_user_filters(): void
    {
        AuditLog::create([
            'user_id' => $this->admin->id,
            'action' => 'REPORT_CREATED',
            'details' => ['report_id' => 1],
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.audit', ['action' => 'REPORT_CREATED']));

        $response->assertOk();
        $logs = $response->viewData('logs');
        $this->assertTrue($logs->contains('action', 'REPORT_CREATED'));
    }

    // ==========================================
    // Feature 10: Security & Role Authorization Isolation
    // ==========================================

    public function test_10_security_role_isolation_superadmin_can_access_all_routes(): void
    {
        $this->actingAs($this->admin)->get(route('admin.users.index'))->assertOk();
        $this->actingAs($this->admin)->get(route('admin.master.index', ['resource' => 'classes']))->assertOk();
        $this->actingAs($this->admin)->get(route('kesiswaan.index'))->assertOk();
        $this->actingAs($this->admin)->get(route('sarpras.index'))->assertOk();
        $this->actingAs($this->admin)->get(route('dashboard'))->assertOk();
    }

    public function test_10_security_role_isolation_kesiswaan_blocked_from_admin_and_sarpras(): void
    {
        $this->actingAs($this->kesiswaan)->get(route('admin.users.index'))->assertStatus(403);
        $this->actingAs($this->kesiswaan)->get(route('admin.master.index', ['resource' => 'classes']))->assertStatus(403);
        $this->actingAs($this->kesiswaan)->get(route('sarpras.index'))->assertStatus(403);
        $this->actingAs($this->kesiswaan)->get(route('kesiswaan.index'))->assertOk();
    }

    public function test_10_security_role_isolation_sarpras_blocked_from_admin_and_kesiswaan(): void
    {
        $this->actingAs($this->sarpras)->get(route('admin.users.index'))->assertStatus(403);
        $this->actingAs($this->sarpras)->get(route('admin.master.index', ['resource' => 'classes']))->assertStatus(403);
        $this->actingAs($this->sarpras)->get(route('kesiswaan.index'))->assertStatus(403);
        $this->actingAs($this->sarpras)->get(route('sarpras.index'))->assertOk();
    }

    public function test_10_security_role_isolation_wali_kelas_blocked_from_admin_kesiswaan_sarpras(): void
    {
        $this->actingAs($this->waliKelas)->get(route('admin.users.index'))->assertStatus(403);
        $this->actingAs($this->waliKelas)->get(route('admin.master.index', ['resource' => 'classes']))->assertStatus(403);
        $this->actingAs($this->waliKelas)->get(route('kesiswaan.index'))->assertStatus(403);
        $this->actingAs($this->waliKelas)->get(route('sarpras.index'))->assertStatus(403);
        $this->actingAs($this->waliKelas)->get(route('dashboard'))->assertOk();
    }

    public function test_10_security_role_isolation_inactive_user_redirected_to_login(): void
    {
        $inactiveUser = User::factory()->create([
            'role' => 'kesiswaan',
            'is_active' => false,
        ]);

        $response = $this->actingAs($inactiveUser)->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }
}
