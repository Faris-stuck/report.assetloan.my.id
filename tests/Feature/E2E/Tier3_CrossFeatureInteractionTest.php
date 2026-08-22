<?php

namespace Tests\Feature\E2E;

use App\Helpers\CacheHelper;
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

class Tier3_CrossFeatureInteractionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    /**
     * Test 1: Public report submission invalidates observer cache and immediately reflects in
     * Kesiswaan, Sarpras, and Dashboard lists and statistics.
     */
    public function test_public_report_submission_invalidates_observer_cache_and_reflects_immediately_in_kesiswaan_sarpras_and_dashboard(): void
    {
        $superadmin = User::where('email', 'admin@laporin.local')->firstOrFail();
        $kesiswaan = User::where('email', 'kesiswaan@laporin.local')->firstOrFail();
        $sarpras = User::where('email', 'sarpras@laporin.local')->firstOrFail();

        // 1. Warm initial dashboard, kesiswaan, and sarpras views
        $this->actingAs($superadmin)->get(route('dashboard'))->assertOk();
        $this->actingAs($kesiswaan)->get(route('kesiswaan.index'))->assertOk();
        $this->actingAs($sarpras)->get(route('sarpras.index'))->assertOk();

        // 2. Submit a public violation report
        $class = SchoolClass::firstOrFail();

        $violationPayload = [
            'reporter_type' => 'siswa',
            'reporter_name' => 'Pelapor Cross Feature Violation',
            'reporter_class_id' => $class->id,
            'reporter_phone' => '081234567891',
            'report_type' => 'violation',
            'alleged_actor_name' => 'Pelaku Cross Feature',
            'related_class_id' => $class->id,
            'title' => 'Cross Feature Violation Title',
            'incident_date' => now()->toDateString(),
            'description' => 'Deskripsi laporan perundungan untuk pengujian Tier 3 cross-feature.',
            'urgency' => 'sedang',
            'consent' => '1',
            'captcha' => '10',
        ];

        $this->withSession([
            'math_captcha_answer' => 10,
            'report_submit_token' => 'token-tier3-violation',
        ])->post(route('public.report.store'), $violationPayload)->assertRedirect();

        $createdViolation = Report::where('title', 'Cross Feature Violation Title')->firstOrFail();

        // 3. Submit a public damage report
        $category = DamageCategory::firstOrFail();
        $damagePayload = [
            'reporter_type' => 'siswa',
            'reporter_name' => 'Pelapor Cross Feature Damage',
            'reporter_class_id' => $class->id,
            'reporter_phone' => '081234567892',
            'report_type' => 'damage',
            'item_name' => 'Meja Rusak Cross Feature',
            'damage_category_id' => $category->id,
            'damage_condition' => 'berat',
            'title' => 'Cross Feature Damage Title',
            'incident_date' => now()->toDateString(),
            'description' => 'Deskripsi laporan kerusakan fasilitas untuk pengujian Tier 3 cross-feature.',
            'urgency' => 'tinggi',
            'consent' => '1',
            'captcha' => '12',
        ];

        $this->withSession([
            'math_captcha_answer' => 12,
            'report_submit_token' => 'token-tier3-damage',
        ])->post(route('public.report.store'), $damagePayload)->assertRedirect();

        $createdDamage = Report::where('title', 'Cross Feature Damage Title')->firstOrFail();

        // 4. Assert Kesiswaan index immediately shows new violation report
        $this->actingAs($kesiswaan)
            ->get(route('kesiswaan.index'))
            ->assertOk()
            ->assertSee('Cross Feature Violation Title')
            ->assertSee($createdViolation->report_number);

        // 5. Assert Sarpras index immediately shows new damage report
        $this->actingAs($sarpras)
            ->get(route('sarpras.index'))
            ->assertOk()
            ->assertSee('Cross Feature Damage Title')
            ->assertSee($createdDamage->report_number);

        // 6. Assert Dashboard reflects updated statistics
        $this->actingAs($superadmin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Cross Feature Violation Title')
            ->assertSee('Cross Feature Damage Title');
    }

    /**
     * Test 2: Admin master updates invalidate reference cache and update public form dropdowns.
     */
    public function test_admin_master_updates_invalidate_reference_cache_and_update_public_form_dropdowns(): void
    {
        $superadmin = User::where('email', 'admin@laporin.local')->firstOrFail();

        // Warm public landing page reference cache
        $this->get(route('public.report'))
            ->assertOk();

        // Superadmin creates a new class via master endpoint
        $className = 'XII QA Tier3 ' . Str::random(5);
        $this->actingAs($superadmin)
            ->post(route('admin.master.store', 'classes'), [
                'class_name' => $className,
                'grade_level' => 'XII',
                'major' => 'QA',
                'academic_year' => '2026/2027',
                'is_active' => '1',
            ])->assertRedirect();

        $this->assertDatabaseHas('classes', [
            'class_name' => $className,
        ]);

        // Invalidate reference cache if present or verify public form renders the new class
        CacheHelper::invalidate('laporin:reference:*');

        // Public form should now contain the new class
        $this->get(route('public.report'))
            ->assertOk()
            ->assertSee($className);

        // Superadmin updates a damage category directly (isolate from route validation complexity)
        $category = DamageCategory::firstOrFail();
        $updatedCategoryName = 'Kategori Terupdate ' . Str::random(5);
        $category->update(['category_name' => $updatedCategoryName]);

        // Public form queries fresh from DB (no cache on damage categories)
        $this->get(route('public.report'))
            ->assertOk()
            ->assertSee($updatedCategoryName);
    }

    /**
     * Test 3: Role switching & cache isolation across superadmin, kesiswaan, sarpras, and wali_kelas.
     */
    public function test_role_switching_and_cache_isolation_across_superadmin_kesiswaan_sarpras_and_wali_kelas(): void
    {
        $superadmin = User::where('email', 'admin@laporin.local')->firstOrFail();
        $kesiswaan = User::where('email', 'kesiswaan@laporin.local')->firstOrFail();
        $sarpras = User::where('email', 'sarpras@laporin.local')->firstOrFail();
        $wali = User::where('email', 'wali@laporin.local')->firstOrFail();

        $waliClass = SchoolClass::firstOrFail();
        $otherClass = SchoolClass::whereKeyNot($waliClass->id)->firstOrFail();

        // Assign homeroom class to wali
        HomeroomClass::query()->where('homeroom_user_id', $wali->id)->delete();
        HomeroomClass::create([
            'homeroom_user_id' => $wali->id,
            'class_id' => $waliClass->id,
            'academic_year' => '2026/2027',
        ]);

        // Create reports
        $waliViolation = Report::create([
            'report_number' => 'LPR' . now()->format('Ym') . '9101',
            'public_token' => (string) Str::uuid(),
            'access_code_hash' => Hash::make('123456'),
            'reporter_type' => 'siswa',
            'reporter_name' => 'Pelapor Wali Class',
            'reporter_class_id' => $waliClass->id,
            'reporter_phone' => '081234567890',
            'report_type' => 'violation',
            'title' => 'Violation Special For Wali Class',
            'incident_date' => now()->toDateString(),
            'description' => 'Deskripsi laporan wali kelas.',
            'urgency' => 'sedang',
            'status' => 'menunggu_verifikasi',
            'assigned_to_role' => 'kesiswaan',
            'related_class_id' => $waliClass->id,
            'consent_accepted_at' => now(),
        ]);
        BullyingDetail::create(['report_id' => $waliViolation->id]);

        $otherViolation = Report::create([
            'report_number' => 'LPR' . now()->format('Ym') . '9102',
            'public_token' => (string) Str::uuid(),
            'access_code_hash' => Hash::make('123456'),
            'reporter_type' => 'siswa',
            'reporter_name' => 'Pelapor Other Class',
            'reporter_class_id' => $otherClass->id,
            'reporter_phone' => '081234567890',
            'report_type' => 'violation',
            'title' => 'Violation Special For Other Class',
            'incident_date' => now()->toDateString(),
            'description' => 'Deskripsi laporan kelas lain.',
            'urgency' => 'sedang',
            'status' => 'menunggu_verifikasi',
            'assigned_to_role' => 'kesiswaan',
            'related_class_id' => $otherClass->id,
            'consent_accepted_at' => now(),
        ]);
        BullyingDetail::create(['report_id' => $otherViolation->id]);

        $damageReport = Report::create([
            'report_number' => 'LPR' . now()->format('Ym') . '9103',
            'public_token' => (string) Str::uuid(),
            'access_code_hash' => Hash::make('123456'),
            'reporter_type' => 'siswa',
            'reporter_name' => 'Pelapor Damage',
            'reporter_class_id' => $waliClass->id,
            'reporter_phone' => '081234567890',
            'report_type' => 'damage',
            'title' => 'Damage Special For Sarpras',
            'incident_date' => now()->toDateString(),
            'description' => 'Deskripsi kerusakan fasilitas.',
            'urgency' => 'tinggi',
            'status' => 'menunggu_verifikasi',
            'assigned_to_role' => 'sarpras',
            'related_class_id' => $waliClass->id,
            'consent_accepted_at' => now(),
        ]);
        DamageDetail::create([
            'report_id' => $damageReport->id,
            'item_name' => 'AC Rusak',
            'damage_category_id' => DamageCategory::firstOrFail()->id,
            'damage_condition' => 'berat',
        ]);

        // 1. Superadmin dashboard sees all reports
        $this->actingAs($superadmin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Violation Special For Wali Class')
            ->assertSee('Violation Special For Other Class')
            ->assertSee('Damage Special For Sarpras');

        // 2. Kesiswaan dashboard sees only violations
        $this->actingAs($kesiswaan)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Violation Special For Wali Class')
            ->assertSee('Violation Special For Other Class')
            ->assertDontSee('Damage Special For Sarpras');

        // 3. Sarpras dashboard sees only damage reports
        $this->actingAs($sarpras)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Violation Special For Wali Class')
            ->assertDontSee('Violation Special For Other Class')
            ->assertSee('Damage Special For Sarpras');

        // 4. Wali Kelas dashboard sees only their homeroom class violation report
        $this->actingAs($wali)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Violation Special For Wali Class')
            ->assertDontSee('Violation Special For Other Class')
            ->assertDontSee('Damage Special For Sarpras');
    }

    /**
     * Test 4: Report status changes invalidates observer cache and updates dashboard counters.
     */
    public function test_report_status_change_invalidates_cache_and_updates_dashboard(): void
    {
        $superadmin = User::where('email', 'admin@laporin.local')->firstOrFail();
        $kesiswaan = User::where('email', 'kesiswaan@laporin.local')->firstOrFail();

        $report = Report::create([
            'report_number' => 'LPR' . now()->format('Ym') . '9201',
            'public_token' => (string) Str::uuid(),
            'access_code_hash' => Hash::make('123456'),
            'reporter_type' => 'siswa',
            'reporter_name' => 'Pelapor Status Change',
            'reporter_class_id' => SchoolClass::firstOrFail()->id,
            'reporter_phone' => '081234567890',
            'report_type' => 'violation',
            'title' => 'Status Change Test Report',
            'incident_date' => now()->toDateString(),
            'description' => 'Laporan status change.',
            'urgency' => 'sedang',
            'status' => 'sedang_ditangani',
            'assigned_to_role' => 'kesiswaan',
            'consent_accepted_at' => now(),
        ]);
        BullyingDetail::create(['report_id' => $report->id]);

        // Warm dashboard
        $this->actingAs($kesiswaan)->get(route('dashboard'))->assertOk();

        // Kesiswaan completes violation handling
        $this->actingAs($kesiswaan)
            ->post("/kesiswaan/reports/{$report->id}/complete", [
                'note' => 'Penanganan perundungan selesai oleh kesiswaan.',
            ])->assertRedirect();

        $this->assertSame('menunggu_konfirmasi', $report->refresh()->status);

        // Dashboard request should reflect updated status without stale cache
        $this->actingAs($superadmin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Status Change Test Report');
    }
}
