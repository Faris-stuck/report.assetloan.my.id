<?php

namespace Tests\Feature\E2E;

use App\Models\BullyingDetail;
use App\Models\DamageCategory;
use App\Models\DamageDetail;
use App\Models\Report;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class Tier2_BoundaryCornerCasesTest extends TestCase
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

    // ==========================================
    // Category 1: Empty Lists & Datasets
    // ==========================================

    public function test_c1_empty_dataset_kesiswaan_violation_index_renders_zero_state(): void
    {
        Report::query()->forceDelete();

        $response = $this->actingAs($this->kesiswaan)
            ->get(route('kesiswaan.index'));

        $response->assertOk();
        $response->assertViewHas('reports');
        $reports = $response->viewData('reports');
        $this->assertCount(0, $reports);
    }

    public function test_c1_empty_dataset_sarpras_damage_index_renders_zero_state(): void
    {
        Report::query()->forceDelete();

        $response = $this->actingAs($this->sarpras)
            ->get(route('sarpras.index'));

        $response->assertOk();
        $response->assertViewHas('reports');
        $reports = $response->viewData('reports');
        $this->assertCount(0, $reports);
    }

    public function test_c1_empty_dataset_admin_master_renders_empty_table(): void
    {
        Subject::query()->delete();

        $response = $this->actingAs($this->admin)
            ->get(route('admin.master.index', ['resource' => 'subjects']));

        $response->assertOk();
        $response->assertViewHas('items');
        $this->assertCount(0, $response->viewData('items'));
    }

    public function test_c1_empty_dataset_dashboard_report_list_and_zero_stats(): void
    {
        Report::query()->forceDelete();

        $response = $this->actingAs($this->admin)
            ->get(route('dashboard'));

        $response->assertOk();
        $stats = $response->viewData('stats');
        $reports = $response->viewData('reports');

        $this->assertCount(0, $reports);
        $this->assertEquals(0, $stats['total']);
        $this->assertEquals(0, $stats['violation']);
        $this->assertEquals(0, $stats['damage']);
        $this->assertEquals(0, $stats['pending']);
        $this->assertEquals(0, $stats['done']);
    }

    public function test_c1_empty_dataset_dashboard_monthly_chart_returns_zero_array(): void
    {
        Report::query()->forceDelete();

        $response = $this->actingAs($this->admin)
            ->get(route('dashboard'));

        $response->assertOk();
        $chart = $response->viewData('chart');

        $this->assertIsArray($chart['counts']);
        $this->assertCount(6, $chart['counts']);
        foreach ($chart['counts'] as $count) {
            $this->assertEquals(0, $count);
        }
        $this->assertEquals(0, $chart['max']);
    }

    // ==========================================
    // Category 2: Missing Optional Relations
    // ==========================================

    public function test_c2_report_with_null_bullying_detail_and_null_damage_detail_renders_safely(): void
    {
        $report = Report::create([
            'report_number' => 'LAP-NULL-'.Str::random(4),
            'public_token' => (string) Str::uuid(),
            'access_code_hash' => Hash::make('123456'),
            'reporter_type' => 'umum',
            'reporter_name' => 'Anonim Null Detail',
            'report_type' => 'violation',
            'title' => 'Laporan Tanpa Detail Relasi',
            'incident_date' => now()->toDateString(),
            'description' => 'Deskripsi tanpa detail perundungan maupun kerusakan.',
            'status' => 'menunggu_verifikasi',
            'urgency' => 'rendah',
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('reports.show', $report));

        $response->assertOk();
        $response->assertSee('Laporan Tanpa Detail Relasi');
        $this->assertNull($report->bullyingDetail);
        $this->assertNull($report->damageDetail);
    }

    /**
     * Fitur lokasi dihapus, jadi tempat kejadian sekarang hidup di dalam
     * kronologi. Halaman detail harus tetap menampilkan deskripsinya utuh
     * tanpa kolom lokasi apa pun.
     */
    public function test_c2_report_without_related_class_renders_safely_in_views(): void
    {
        $report = Report::create([
            'report_number' => 'LAP-NOREL-'.Str::random(4),
            'public_token' => (string) Str::uuid(),
            'access_code_hash' => Hash::make('123456'),
            'reporter_type' => 'siswa',
            'reporter_name' => 'Pelapor Tanpa Kelas Terkait',
            'report_type' => 'violation',
            'title' => 'Laporan Tanpa Kelas Terkait',
            'related_class_id' => null,
            'incident_date' => now()->toDateString(),
            'description' => 'Kejadian di lapangan luar sekolah saat jam istirahat.',
            'status' => 'menunggu_verifikasi',
            'urgency' => 'sedang',
        ]);

        $response = $this->actingAs($this->kesiswaan)
            ->get(route('reports.show', $report));

        $response->assertOk();
        $response->assertSee('Kejadian di lapangan luar sekolah saat jam istirahat.');
        $this->assertNull($report->relatedClass);
    }

    public function test_c2_bullying_detail_with_null_alleged_actor_class_id_renders_safely(): void
    {
        $report = Report::create([
            'report_number' => 'LAP-BULLY-NOCLASS-'.Str::random(4),
            'public_token' => (string) Str::uuid(),
            'access_code_hash' => Hash::make('123456'),
            'reporter_type' => 'siswa',
            'reporter_name' => 'Korban Anonim',
            'report_type' => 'violation',
            'title' => 'Pelanggaran Pelaku Tidak Diketahui Kelasnya',
            'incident_date' => now()->toDateString(),
            'description' => 'Perundungan oleh alumni / pihak luar.',
            'status' => 'menunggu_verifikasi',
            'urgency' => 'sedang',
        ]);

        $detail = BullyingDetail::create([
            'report_id' => $report->id,
            'reporter_position' => 'korban',
            'bullying_type' => 'relasionsional',
            'victim_name' => 'Siswa A',
            'alleged_actor_name' => 'Pelaku Luar',
            'alleged_actor_class_id' => null,
        ]);

        $response = $this->actingAs($this->kesiswaan)
            ->get(route('reports.show', $report));

        $response->assertOk();
        $response->assertSee('Pelaku Luar');
        $this->assertNull($detail->allegedActorClass);
    }

    public function test_c2_report_with_null_reporter_class_id_renders_safely(): void
    {
        $report = Report::create([
            'report_number' => 'LAP-GURU-'.Str::random(4),
            'public_token' => (string) Str::uuid(),
            'access_code_hash' => Hash::make('123456'),
            'reporter_type' => 'guru',
            'reporter_name' => 'Bapak Guru Pelapor',
            'reporter_class_id' => null,
            'report_type' => 'damage',
            'title' => 'Laporan dari Guru Tanpa Kelas',
            'incident_date' => now()->toDateString(),
            'description' => 'Kerusakan meja di ruang guru.',
            'status' => 'menunggu_verifikasi',
            'urgency' => 'rendah',
        ]);

        $response = $this->actingAs($this->sarpras)
            ->get(route('reports.show', $report));

        $response->assertOk();
        $response->assertSee('Bapak Guru Pelapor');
        $this->assertNull($report->reporterClass);
    }

    // ==========================================
    // Category 3: Unauthenticated Redirects
    // ==========================================

    public function test_c3_unauthenticated_guest_redirected_from_dashboard(): void
    {
        $response = $this->get(route('dashboard'));

        $response->assertStatus(302);
        $response->assertRedirect(route('login'));
    }

    public function test_c3_unauthenticated_guest_redirected_from_admin_master(): void
    {
        $response = $this->get(route('admin.master.index', ['resource' => 'classes']));

        $response->assertStatus(302);
        $response->assertRedirect(route('login'));
    }

    public function test_c3_unauthenticated_guest_redirected_from_kesiswaan_index(): void
    {
        $response = $this->get(route('kesiswaan.index'));

        $response->assertStatus(302);
        $response->assertRedirect(route('login'));
    }

    public function test_c3_unauthenticated_guest_redirected_from_sarpras_index(): void
    {
        $response = $this->get(route('sarpras.index'));

        $response->assertStatus(302);
        $response->assertRedirect(route('login'));
    }

    public function test_c3_unauthenticated_guest_redirected_from_report_show(): void
    {
        $report = Report::create([
            'report_number' => 'LAP-GUEST-'.Str::random(4),
            'public_token' => (string) Str::uuid(),
            'access_code_hash' => Hash::make('123456'),
            'reporter_type' => 'siswa',
            'reporter_name' => 'Siswa Test',
            'report_type' => 'violation',
            'title' => 'Laporan Rahasia Internal',
            'incident_date' => now()->toDateString(),
            'description' => 'Deskripsi rahasia.',
            'status' => 'menunggu_verifikasi',
            'urgency' => 'sedang',
        ]);

        $response = $this->get(route('reports.show', $report));

        $response->assertStatus(302);
        $response->assertRedirect(route('login'));
    }

    // ==========================================
    // Category 4: Unauthorized Role 403 Access
    // ==========================================

    public function test_c4_kesiswaan_user_forbidden_from_admin_users(): void
    {
        $response = $this->actingAs($this->kesiswaan)
            ->get(route('admin.users.index'));

        $response->assertStatus(403);
    }

    public function test_c4_sarpras_user_forbidden_from_kesiswaan_index(): void
    {
        $response = $this->actingAs($this->sarpras)
            ->get(route('kesiswaan.index'));

        $response->assertStatus(403);
    }

    public function test_c4_kesiswaan_user_forbidden_from_sarpras_index(): void
    {
        $response = $this->actingAs($this->kesiswaan)
            ->get(route('sarpras.index'));

        $response->assertStatus(403);
    }

    public function test_c4_wali_kelas_user_forbidden_from_admin_master(): void
    {
        $response = $this->actingAs($this->waliKelas)
            ->get(route('admin.master.index', ['resource' => 'classes']));

        $response->assertStatus(403);
    }

    public function test_c4_wali_kelas_user_forbidden_from_kesiswaan_index(): void
    {
        $response = $this->actingAs($this->waliKelas)
            ->get(route('kesiswaan.index'));

        $response->assertStatus(403);
    }

    // ==========================================
    // Category 5: Invalid Filter Query Parameters
    // ==========================================

    public function test_c5_kesiswaan_index_with_invalid_status_filter_falls_back_safely(): void
    {
        $response = $this->actingAs($this->kesiswaan)
            ->get(route('kesiswaan.index', ['status' => 'non_existent_status_xyz']));

        $response->assertOk();
        $response->assertViewHas('reports');
    }

    public function test_c5_sarpras_index_with_invalid_priority_filter_falls_back_safely(): void
    {
        $response = $this->actingAs($this->sarpras)
            ->get(route('sarpras.index', ['priority' => 'super_urgent_invalid']));

        $response->assertOk();
        $response->assertViewHas('reports');
    }

    public function test_c5_kesiswaan_index_with_malformed_date_strings_handles_gracefully(): void
    {
        $response = $this->actingAs($this->kesiswaan)
            ->get(route('kesiswaan.index', [
                'from_date' => 'not-a-valid-date',
                'to_date' => 'invalid-date-string',
            ]));

        $response->assertOk();
        $response->assertViewHas('reports');
    }

    public function test_c5_sarpras_index_with_special_characters_in_search_query(): void
    {
        $response = $this->actingAs($this->sarpras)
            ->get(route('sarpras.index', [
                'search' => "<script>alert('xss')</script>' OR '1'='1",
            ]));

        $response->assertOk();
        $response->assertViewHas('reports');
    }

    public function test_c5_dashboard_index_with_extreme_page_numbers_or_invalid_query_params(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('dashboard', [
                'page' => -99999,
                'search' => "'; DROP TABLE reports; --",
            ]));

        // Negative page numbers may return 400 (Laravel validation) or 200 with empty results.
        // Both are acceptable - we just verify the app doesn't crash with 500.
        $this->assertNotEquals(500, $response->getStatusCode());
        if ($response->isOk()) {
            $response->assertViewHas('reports');
        }
    }
}
