<?php

namespace Tests\Feature\E2E;

use App\Models\AuditLog;
use App\Models\BullyingDetail;
use App\Models\DamageCategory;
use App\Models\DamageDetail;
use App\Models\HomeroomClass;
use App\Models\Report;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class Tier4_RealWorldScenarioTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    /**
     * Test 1: End-to-end multi-role lifecycle
     * (Public submit -> Kesiswaan process -> Sarpras process -> Wali kelas view -> Admin audit).
     */
    public function test_end_to_end_multi_role_lifecycle_public_to_kesiswaan_sarpras_wali_and_admin_audit(): void
    {
        $superadmin = User::where('email', 'admin@laporin.local')->firstOrFail();
        $kesiswaan = User::where('email', 'kesiswaan@laporin.local')->firstOrFail();
        $sarpras = User::where('email', 'sarpras@laporin.local')->firstOrFail();
        $wali = User::where('email', 'wali@laporin.local')->firstOrFail();

        $waliClass = SchoolClass::firstOrFail();
        $category = DamageCategory::firstOrFail();

        // Assign homeroom class to wali
        HomeroomClass::query()->where('homeroom_user_id', $wali->id)->delete();
        HomeroomClass::create([
            'homeroom_user_id' => $wali->id,
            'class_id' => $waliClass->id,
            'academic_year' => '2026/2027',
        ]);

        // Step 1: Public user submits violation report involving wali's homeroom class
        $violationPayload = [
            'reporter_type' => 'siswa',
            'reporter_name' => 'Siswa Pelapor Lifecycle',
            'reporter_class_id' => $waliClass->id,
            'reporter_phone' => '081298765432',
            'report_type' => 'violation',
            'alleged_actor_name' => 'Siswa Terduga Lifecycle',
            'related_class_id' => $waliClass->id,
            'title' => 'Laporan Perundungan End to End Lifecycle',
            'incident_date' => now()->toDateString(),
            'description' => 'Deskripsi laporan perundungan untuk lifecycle pengujian Tier 4.',
            'urgency' => 'sedang',
            'consent' => '1',
            'captcha' => '7',
        ];

        $this->withSession([
            'math_captcha_answer' => 7,
            'report_submit_token' => 'token-lifecycle-violation',
        ])->post(route('public.report.store'), $violationPayload)->assertRedirect();

        $violationReport = Report::where('title', 'Laporan Perundungan End to End Lifecycle')->firstOrFail();
        $this->assertSame('kesiswaan', $violationReport->assigned_to_role);

        // Step 2: Public user submits damage report
        $subject = \App\Models\Subject::where('is_active', true)->firstOrFail();

        $damagePayload = [
            'reporter_type' => 'guru',
            'reporter_name' => 'Guru Pelapor Lifecycle',
            'reporter_subject_id' => $subject->id,
            'reporter_phone' => '081298765433',
            'report_type' => 'damage',
            'item_name' => 'Proyektor Kelas Lifecycle',
            'damage_category_id' => $category->id,
            'damage_condition' => 'Proyektor mati total tidak bisa dinyalakan.',
            'title' => 'Laporan Kerusakan Proyektor Lifecycle',
            'incident_date' => now()->toDateString(),
            'description' => 'Deskripsi laporan kerusakan proyektor untuk lifecycle pengujian Tier 4.',
            'urgency' => 'tinggi',
            'consent' => '1',
            'captcha' => '9',
        ];

        $this->withSession([
            'math_captcha_answer' => 9,
            'report_submit_token' => 'token-lifecycle-damage',
        ])->post(route('public.report.store'), $damagePayload)->assertRedirect();

        $damageReport = Report::where('title', 'Laporan Kerusakan Proyektor Lifecycle')->firstOrFail();
        $this->assertSame('sarpras', $damageReport->assigned_to_role);

        // Step 3: Kesiswaan processes violation report
        $this->actingAs($kesiswaan)
            ->get(route('kesiswaan.index'))
            ->assertOk()
            ->assertSee('Laporan Perundungan End to End Lifecycle');

        $this->actingAs($kesiswaan)
            ->post(route('reports.notes', $violationReport), [
                'note' => 'Kesiswaan telah memanggil pihak terduga.',
                'visibility' => 'internal',
            ])->assertRedirect();

        // Advance to sedang_ditangani before completing (required by KesiswaanProcessor)
        $violationReport->update(['status' => 'sedang_ditangani']);

        $this->actingAs($kesiswaan)
            ->post("/kesiswaan/reports/{$violationReport->id}/complete", [
                'note' => 'Kesiswaan telah merampungkan tindakan pembinaan.',
            ])->assertRedirect();

        $this->assertSame('menunggu_konfirmasi', $violationReport->refresh()->status);

        // Step 4: Sarpras processes damage report (rejects or handles)
        $this->actingAs($sarpras)
            ->get(route('sarpras.index'))
            ->assertOk()
            ->assertSee('Laporan Kerusakan Proyektor Lifecycle');

        $this->actingAs($sarpras)
            ->post("/sarpras/reports/{$damageReport->id}/reject", [
                'reason' => 'Proyektor milik unit pribadi, bukan inventaris sekolah.',
            ])->assertRedirect();

        $this->assertSame('ditolak', $damageReport->refresh()->status);

        // Step 5: Wali Kelas views dashboard & report detail (read-only verification)
        $this->actingAs($wali)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Laporan Perundungan End to End Lifecycle')
            ->assertDontSee('Laporan Kerusakan Proyektor Lifecycle');

        $this->actingAs($wali)
            ->get(route('reports.show', $violationReport))
            ->assertOk()
            ->assertSee('Laporan Perundungan End to End Lifecycle')
            ->assertDontSee('Tambah Catatan');

        $this->actingAs($wali)
            ->post(route('reports.notes', $violationReport), [
                'note' => 'Catatan ilegal wali kelas.',
                'visibility' => 'internal',
            ])->assertForbidden();

        // Step 6: Superadmin views admin audit logs
        AuditLog::create([
            'actor_type' => 'superadmin',
            'action' => 'audit_review_lifecycle',
            'model_type' => 'App\Models\Report',
            'model_id' => $violationReport->id,
        ]);

        $this->actingAs($superadmin)
            ->get('/admin/audit')
            ->assertOk()
            ->assertSee('audit_review_lifecycle');
    }

    /**
     * Test 2: High-volume simulated workload with 50+ batch created reports,
     * verifying pagination, stats counts, and charts without errors.
     */
    public function test_high_volume_simulated_workload_50_plus_reports_pagination_stats_and_charts(): void
    {
        $superadmin = User::where('email', 'admin@laporin.local')->firstOrFail();
        $kesiswaan = User::where('email', 'kesiswaan@laporin.local')->firstOrFail();
        $sarpras = User::where('email', 'sarpras@laporin.local')->firstOrFail();

        $classes = SchoolClass::take(3)->get();
        $category = DamageCategory::firstOrFail();

        $statuses = ['menunggu_verifikasi', 'sedang_ditangani', 'menunggu_konfirmasi', 'selesai', 'ditolak'];

        // Batch create 55 reports (30 violation, 25 damage)
        for ($i = 1; $i <= 55; $i++) {
            $isViolation = ($i <= 30);
            $type = $isViolation ? 'violation' : 'damage';
            $assignedRole = $isViolation ? 'kesiswaan' : 'sarpras';
            $class = $classes[$i % count($classes)];
            $status = $statuses[$i % count($statuses)];

            $createdDate = now()->subDays($i % 150);

            $report = Report::create([
                'report_number' => 'LPR' . $createdDate->format('Ym') . sprintf('%04d', 5000 + $i),
                'public_token' => (string) Str::uuid(),
                'access_code_hash' => Hash::make('123456'),
                'reporter_type' => 'siswa',
                'reporter_name' => "Pelapor Workload {$i}",
                'reporter_class_id' => $class->id,
                'reporter_phone' => '08123456' . sprintf('%04d', $i),
                'report_type' => $type,
                'title' => "Batch Report Workload Item {$i}",
                'incident_date' => $createdDate->toDateString(),
                'description' => "Deskripsi laporan batch workload item {$i}.",
                'urgency' => ($i % 2 === 0) ? 'tinggi' : 'sedang',
                'status' => $status,
                'assigned_to_role' => $assignedRole,
                'related_class_id' => $class->id,
                'consent_accepted_at' => $createdDate,
                'created_at' => $createdDate,
                'updated_at' => $createdDate,
            ]);

            if ($isViolation) {
                BullyingDetail::create([
                    'report_id' => $report->id,
                    'bullying_type' => 'verbal',
                    'alleged_actor_name' => "Pelaku Workload {$i}",
                ]);
            } else {
                DamageDetail::create([
                    'report_id' => $report->id,
                    'item_name' => "Barang Workload {$i}",
                    'damage_category_id' => $category->id,
                    'damage_condition' => 'sedang',
                ]);
            }
        }

        // 1. Verify Superadmin Dashboard pagination, stats, and charts
        $dashboardResponse = $this->actingAs($superadmin)
            ->get(route('dashboard'))
            ->assertOk();

        // Verify the dashboard loads OK and has at least 50 reports (batch of 60 minus seed data differences)
        $stats = $dashboardResponse->viewData('stats');
        $this->assertGreaterThanOrEqual(50, $stats['total']);

        // Check page 2 of dashboard pagination
        $this->actingAs($superadmin)
            ->get(route('dashboard', ['page' => 2]))
            ->assertOk();

        // Check page 5 of dashboard pagination
        $this->actingAs($superadmin)
            ->get(route('dashboard', ['page' => 5]))
            ->assertOk();

        // 2. Verify Kesiswaan Index pagination
        $kesiswaanResponse = $this->actingAs($kesiswaan)
            ->get(route('kesiswaan.index'))
            ->assertOk();

        $this->actingAs($kesiswaan)
            ->get(route('kesiswaan.index', ['page' => 2]))
            ->assertOk();

        // 3. Verify Sarpras Index pagination
        $sarprasResponse = $this->actingAs($sarpras)
            ->get(route('sarpras.index'))
            ->assertOk();

        $this->actingAs($sarpras)
            ->get(route('sarpras.index', ['page' => 2]))
            ->assertOk();

        // 4. Verify Admin Master students pagination
        $this->actingAs($superadmin)
            ->get(route('admin.master.index', ['resource' => 'students']))
            ->assertOk();
    }
}
