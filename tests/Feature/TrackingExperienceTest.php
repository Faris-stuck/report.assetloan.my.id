<?php

namespace Tests\Feature;

use App\Models\Report;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class TrackingExperienceTest extends TestCase
{
    use RefreshDatabase;

    private const DEVICE_ID = 'a1b2c3d4-e5f6-4a7b-8c9d-0e1f2a3b4c5d';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    public function test_tracking_accepts_copied_report_and_access_codes_with_common_separators(): void
    {
        $report = $this->report();

        foreach ([
            [' LAP-ABC234-XYZ789 ', ' 123456 '],
            ["lap-abc234-xyz789\n", '123-456'],
            ['LAP ABC234 XYZ789', '123 456'],
        ] as [$reportNumber, $accessCode]) {
            // Cookie perangkat wajib dikirim: pelacakan terikat perangkat, jadi
            // tanpa cookie ini permintaan berhenti di gerbang perangkat dan yang
            // diuji bukan lagi normalisasi salin-tempel.
            $this->withCookie('laporin_device_id', self::DEVICE_ID)
                ->post(route('track.search'), [
                    'report_number' => $reportNumber,
                    'access_code' => $accessCode,
                ])
                ->assertRedirect(route('track.result'));
        }

        $this->withCookies([
            'laporin_device_id' => self::DEVICE_ID,
            'laporin_tracking_proof' => $report->id.'|'.hash_hmac('sha256', (string) $report->access_code_hash, config('app.key')),
        ])
            ->get(route('track.result'))
            ->assertOk()
            ->assertSee($report->report_number);
    }

    public function test_tracking_form_is_copy_paste_friendly_and_explains_the_canonical_format_without_plus_signs(): void
    {
        $this->get(route('track.form'))->assertOk()
            ->assertSee('data-normalize-report-number', false)
            ->assertSee('data-normalize-access-code', false)
            /*
             * Batas panjang HARUS ditegakkan normalizer, bukan atribut
             * maxlength. maxlength memotong hasil paste sebelum label/spasi
             * dibuang: dengan maxlength="16", menempel "Kode Akses: 123456"
             * (18 karakter) tersisa "Kode Akses: 1234" lalu menjadi "1234",
             * dan server menolak kode yang sebenarnya benar. Test ini dulu
             * justru mengunci batas yang merusak salin-tempel itu.
             */
            ->assertSee("replace(/[^0-9]/g, '').slice(0, 6)", false)
            ->assertSee("replace(/[^A-Z0-9-]/g, '').slice(0, 24)", false)
            ->assertDontSee('maxlength="16"', false)
            ->assertSee('LAP-ABC234-XYZ789')
            ->assertDontSee('LPR + tahun/bulan + 4 digit');
    }

    public function test_tracking_form_discloses_the_same_device_restriction_before_the_reporter_fails(): void
    {
        // Pesan penolakannya sengaja tidak menyebut penyebab pastinya agar kode
        // akses 6 digit tidak bisa ditebak dari perbedaan pesan. Konsekuensinya
        // pembatasan perangkat WAJIB diberitahukan lebih dulu, kalau tidak
        // pelapor yang mencoba dari ponsel lain hanya melihat "tidak cocok" dan
        // menyimpulkan laporannya hilang.
        $this->get(route('track.form'))->assertOk()
            ->assertSee('perangkat dan peramban yang sama');
    }

    public function test_success_page_has_accessible_copy_buttons_beside_both_tracking_credentials(): void
    {
        $report = $this->report();

        $this->withSession([
            'success_report_id' => $report->id,
            'access_code' => '123456',
        ])->get(route('public.report.success', $report->public_token))
            ->assertOk()
            ->assertSee('id="report-number-value"', false)
            ->assertSee('data-copy-target="report-number-value"', false)
            ->assertSee('aria-label="Salin nomor laporan"', false)
            ->assertSee('id="access-code-value"', false)
            ->assertSee('data-copy-target="access-code-value"', false)
            ->assertSee('aria-label="Salin kode akses"', false)
            ->assertSee('aria-live="polite"', false);
    }

    public function test_mobile_copy_button_keeps_both_credential_values_visible(): void
    {
        $css = file_get_contents(public_path('css/laporin.css'));

        $this->assertIsString($css);
        $this->assertStringContainsString(
            '.credential-copy-row { display: grid; grid-template-columns: minmax(0, 1fr) auto;',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/@media \(max-width: 575\.98px\)[\s\S]*?\.credential-copy-button\s*\{[^}]*width:\s*auto\s*;/',
            $css
        );
    }

    public function test_tracking_uses_roomy_responsive_layout_and_report_form_has_no_duplicate_hero_actions(): void
    {
        $this->get(route('track.form'))->assertOk()
            ->assertSee('tracking-shell', false)
            ->assertSee('tracking-overview', false)
            ->assertSee('tracking-form-panel', false);

        $this->get(route('public.report'))->assertOk()
            ->assertDontSee('Mulai Laporan')
            ->assertDontSee('class="btn btn-outline-laporin" href="'.route('track.form').'">Lacak Laporan</a>', false)
            ->assertSee('id="form-laporan"', false);
    }

    private function report(): Report
    {
        return Report::create([
            'report_number' => 'LAP-ABC234-XYZ789',
            'public_token' => (string) Str::uuid(),
            'access_code_hash' => Hash::make('123456'),
            'reporter_type' => 'staff',
            'reporter_name' => 'Pelapor Tracking QA',
            'report_type' => 'violation',
            'title' => 'Laporan tracking QA',
            'incident_date' => now()->toDateString(),
            'description' => 'Laporan untuk menguji alur salin dan tempel tracking.',
            'urgency' => 'sedang',
            'status' => 'menunggu_verifikasi',
            'assigned_to_role' => 'kesiswaan',
            'consent_accepted_at' => now(),
            // Nilai yang sama dihitung PublicReportService saat laporan dibuat.
            // Tanpa ini pelacakan berhenti di gerbang perangkat.
            'submitted_device_hash' => hash_hmac('sha256', self::DEVICE_ID, config('app.key')),
        ]);
    }
}
