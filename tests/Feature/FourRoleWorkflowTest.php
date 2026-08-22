<?php

namespace Tests\Feature;

use App\Models\BullyingDetail;
use App\Models\HomeroomClass;
use App\Models\Report;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class FourRoleWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_wali_kelas_is_read_only_and_only_sees_violation_reports_for_related_class(): void
    {
        $wali = User::where('email', 'wali@laporin.local')->firstOrFail();
        $waliClass = SchoolClass::firstOrFail();
        $otherClass = SchoolClass::whereKeyNot($waliClass->id)->firstOrFail();

        HomeroomClass::query()->where('homeroom_user_id', $wali->id)->delete();
        HomeroomClass::create([
            'homeroom_user_id' => $wali->id,
            'class_id' => $waliClass->id,
            'academic_year' => '2026/2027',
        ]);

        $visible = $this->report([
            'related_class_id' => $waliClass->id,
            'reporter_class_id' => $otherClass->id,
        ]);
        BullyingDetail::create(['report_id' => $visible->id]);

        $reporterOnly = $this->report([
            'report_number' => $this->number('1002'),
            'related_class_id' => $otherClass->id,
            'reporter_class_id' => $waliClass->id,
        ]);
        BullyingDetail::create(['report_id' => $reporterOnly->id]);

        $detailOnly = $this->report([
            'report_number' => $this->number('1003'),
            'related_class_id' => $otherClass->id,
            'reporter_class_id' => $otherClass->id,
        ]);
        BullyingDetail::create([
            'report_id' => $detailOnly->id,
            'victim_class_id' => $waliClass->id,
            'alleged_actor_class_id' => $waliClass->id,
        ]);

        $damage = $this->report([
            'report_number' => $this->number('1004'),
            'report_type' => 'damage',
            'assigned_to_role' => 'sarpras',
            'related_class_id' => $waliClass->id,
        ]);

        $this->actingAs($wali)->get(route('reports.show', $visible))->assertOk();
        $this->actingAs($wali)->get(route('reports.show', $reporterOnly))->assertForbidden();
        $this->actingAs($wali)->get(route('reports.show', $detailOnly))->assertForbidden();
        $this->actingAs($wali)->get(route('reports.show', $damage))->assertForbidden();

        $this->actingAs($wali)->post(route('reports.notes', $visible), [
            'note' => 'Wali tidak boleh menulis.',
            'visibility' => 'internal',
        ])->assertForbidden();
        $this->assertFalse(Route::has('reports.wali-confirm'));
    }

    public function test_wali_dashboard_is_filtered_only_by_related_class(): void
    {
        $wali = User::where('email', 'wali@laporin.local')->firstOrFail();
        $waliClass = SchoolClass::firstOrFail();
        $otherClass = SchoolClass::whereKeyNot($waliClass->id)->firstOrFail();

        HomeroomClass::query()->where('homeroom_user_id', $wali->id)->delete();
        HomeroomClass::create([
            'homeroom_user_id' => $wali->id,
            'class_id' => $waliClass->id,
            'academic_year' => '2026/2027',
        ]);

        $visible = $this->report([
            'related_class_id' => $waliClass->id,
            'reporter_class_id' => $otherClass->id,
        ]);
        $hidden = $this->report([
            'report_number' => $this->number('2002'),
            'related_class_id' => $otherClass->id,
            'reporter_class_id' => $waliClass->id,
        ]);

        $this->actingAs($wali)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee($visible->report_number)
            ->assertDontSee($hidden->report_number);

        $this->actingAs($wali)
            ->get(route('reports.show', $visible))
            ->assertOk()
            ->assertDontSee('Tambah Catatan')
            ->assertDontSee('Kirim ke Konfirmasi Pelapor');
    }

    public function test_only_kesiswaan_can_finish_internal_violation_handling(): void
    {
        $report = $this->report(['status' => 'sedang_ditangani']);
        BullyingDetail::create(['report_id' => $report->id]);
        $url = "/kesiswaan/reports/{$report->id}/complete";
        $kesiswaan = User::where('email', 'kesiswaan@laporin.local')->firstOrFail();

        $this->actingAs($kesiswaan)
            ->get(route('kesiswaan.index'))
            ->assertOk()
            ->assertSee('Selesaikan Penanganan')
            ->assertDontSee('Wali Kelas Konfirmasi');

        $this->actingAs(User::where('email', 'wali@laporin.local')->firstOrFail())
            ->post($url)
            ->assertForbidden();
        $this->actingAs(User::where('email', 'sarpras@laporin.local')->firstOrFail())
            ->post($url)
            ->assertForbidden();

        $this->actingAs(User::where('email', 'kesiswaan@laporin.local')->firstOrFail())
            ->post($url, ['note' => 'Pembinaan dan tindak lanjut telah dilaksanakan.'])
            ->assertRedirect();

        $this->assertSame('menunggu_konfirmasi', $report->refresh()->status);
        $this->assertDatabaseHas('report_status_histories', [
            'report_id' => $report->id,
            'actor_type' => 'kesiswaan',
            'previous_status' => 'sedang_ditangani',
            'new_status' => 'menunggu_konfirmasi',
        ]);
    }

    public function test_only_sarpras_can_reject_damage_reports(): void
    {
        $report = $this->report([
            'report_number' => $this->number('3001'),
            'report_type' => 'damage',
            'status' => 'menunggu_verifikasi',
            'assigned_to_role' => 'sarpras',
        ]);
        $url = "/sarpras/reports/{$report->id}/reject";
        $sarpras = User::where('email', 'sarpras@laporin.local')->firstOrFail();

        $this->actingAs($sarpras)
            ->get(route('sarpras.index'))
            ->assertOk()
            ->assertSee('Tolak Laporan');

        $this->actingAs(User::where('email', 'kesiswaan@laporin.local')->firstOrFail())
            ->post($url, ['reason' => 'Tidak valid.'])
            ->assertForbidden();
        $this->actingAs($sarpras)
            ->post($url)
            ->assertSessionHasErrors(['reason']);
        $this->actingAs($sarpras)
            ->post($url, ['reason' => 'Bukan kerusakan fasilitas sekolah.'])
            ->assertRedirect();

        $this->assertSame('ditolak', $report->refresh()->status);
        $this->assertDatabaseHas('report_status_histories', [
            'report_id' => $report->id,
            'actor_type' => 'sarpras',
            'new_status' => 'ditolak',
        ]);
    }

    public function test_public_report_requires_phone_and_violation_related_class(): void
    {
        $class = SchoolClass::firstOrFail();
        $payload = [
            'reporter_type' => 'siswa',
            'reporter_name' => 'Pelapor Validasi HP',
            'reporter_class_id' => $class->id,
            'report_type' => 'violation',
            'alleged_actor_name' => 'Pelaku Validasi HP',
            'title' => 'Laporan validasi nomor HP',
            'incident_date' => now()->toDateString(),
            'description' => 'Deskripsi lengkap untuk validasi nomor HP wajib.',
            'urgency' => 'sedang',
            'consent' => '1',
            'captcha' => '8',
        ];

        $this->get(route('public.report'))
            ->assertOk()
            ->assertSee('for="reporter_phone"', false)
            ->assertSee('id="reporter_phone"', false)
            ->assertSee('name="reporter_phone"', false)
            ->assertSee('required', false)
            ->assertSee('Nomor HP wajib', false);

        $this->withSession(['math_captcha_answer' => 8, 'report_submit_token' => 'test-submit-token'])
            ->post(route('public.report.store'), $payload)
            ->assertSessionHasErrors(['reporter_phone', 'related_class_id']);

        $this->withSession(['math_captcha_answer' => 8, 'report_submit_token' => 'test-submit-token'])
            ->post(route('public.report.store'), $payload + [
                'reporter_phone' => '1234',
                'related_class_id' => $class->id,
            ])->assertSessionHasErrors(['reporter_phone']);

        $this->withSession(['math_captcha_answer' => 8, 'report_submit_token' => 'test-submit-token'])
            ->post(route('public.report.store'), $payload + [
                'reporter_phone' => '+62 812-3456-7890',
                'related_class_id' => $class->id,
            ])->assertRedirect();

        $this->assertDatabaseHas('reports', [
            'reporter_phone' => '+62 812-3456-7890',
            'related_class_id' => $class->id,
            'assigned_to_role' => 'kesiswaan',
        ]);
    }

    public function test_dashboard_bar_chart_uses_role_scoped_monthly_data(): void
    {
        $this->travelTo(now()->setDate(2026, 7, 30)->setTime(12, 0));
        $wali = User::where('role', 'wali_kelas')->firstOrFail();
        $waliClass = $wali->homeroomClasses()->firstOrFail()->class;

        $currentViolation = $this->report([
            'report_number' => $this->number('4001'),
            'related_class_id' => $waliClass->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $previousViolation = $this->report([
            'report_number' => $this->number('4002'),
            'related_class_id' => $waliClass->id,
        ]);
        $previousViolation->timestamps = false;
        $previousViolation->forceFill([
            'created_at' => now()->subMonth(),
            'updated_at' => now()->subMonth(),
        ])->save();
        $damage = $this->report([
            'report_number' => $this->number('4003'),
            'report_type' => 'damage',
            'assigned_to_role' => 'sarpras',
            'related_class_id' => $waliClass->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->assertNotNull($currentViolation->id);
        $this->assertNotNull($previousViolation->id);
        $this->assertNotNull($damage->id);

        [$adminHtml, $adminCounts] = $this->chartFor(User::where('role', 'superadmin')->firstOrFail());
        $this->assertStringContainsString('Semua Laporan 6 Bulan Terakhir', $adminHtml);
        $this->assertSame([0, 0, 0, 0, 1, 2], $adminCounts);

        [$kesiswaanHtml, $kesiswaanCounts] = $this->chartFor(User::where('role', 'kesiswaan')->firstOrFail());
        $this->assertStringContainsString('Laporan Perundungan 6 Bulan Terakhir', $kesiswaanHtml);
        $this->assertSame([0, 0, 0, 0, 1, 1], $kesiswaanCounts);

        [$sarprasHtml, $sarprasCounts] = $this->chartFor(User::where('role', 'sarpras')->firstOrFail());
        $this->assertStringContainsString('Laporan Kerusakan 6 Bulan Terakhir', $sarprasHtml);
        $this->assertSame([0, 0, 0, 0, 0, 1], $sarprasCounts);

        [$waliHtml, $waliCounts] = $this->chartFor($wali);
        $this->assertStringContainsString('Laporan Kelas Terkait 6 Bulan Terakhir', $waliHtml);
        $this->assertSame([0, 0, 0, 0, 1, 1], $waliCounts);
    }

    private function chartFor(User $user): array
    {
        $html = $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent();

        $matched = preg_match("/data-chart-counts='([^']+)'/", $html, $matches);
        $this->assertSame(1, $matched, 'Atribut data diagram batang tidak ditemukan.');

        return [$html, json_decode(html_entity_decode($matches[1]), true, 512, JSON_THROW_ON_ERROR)];
    }

    private function report(array $overrides = []): Report
    {
        $class = SchoolClass::firstOrFail();

        return Report::create(array_merge([
            'report_number' => $this->number('1001'),
            'public_token' => (string) Str::uuid(),
            'access_code_hash' => Hash::make('123456'),
            'reporter_type' => 'siswa',
            'reporter_name' => 'Pelapor Workflow',
            'reporter_class_id' => $class->id,
            'reporter_phone' => '081234567890',
            'report_type' => 'violation',
            'title' => 'Laporan workflow empat role',
            'incident_date' => now()->toDateString(),
            'description' => 'Deskripsi laporan workflow empat role.',
            'urgency' => 'sedang',
            'status' => 'sedang_ditangani',
            'assigned_to_role' => 'kesiswaan',
            'consent_accepted_at' => now(),
        ], $overrides));
    }

    private function number(string $suffix): string
    {
        return 'LPR'.now()->format('Ym').$suffix;
    }
}
