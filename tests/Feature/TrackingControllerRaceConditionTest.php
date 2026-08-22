<?php

namespace Tests\Feature;

use App\Models\Report;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Properti yang harus tetap berlaku pada aksi pelapor di TrackingController.
 *
 * CATATAN SEJARAH — kenapa file ini ditulis ulang:
 *
 * Versi sebelumnya menguji mekanisme berbasis session (`track_report_id`,
 * `track_access_ok`, `track_verified_at`, TTL 1800 detik) beserta satu skenario
 * race condition pada `hasTrackingAccess()` yang memanggil `session()->forget()`
 * di tengah validasi. Mekanisme itu sudah TIDAK ADA lagi: akses pelacakan kini
 * ditentukan dua cookie — `laporin_device_id` (dicocokkan ke
 * reports.submitted_device_hash) dan `laporin_tracking_proof` (HMAC dari
 * access_code_hash, berlaku 15 menit) — sehingga tidak ada lagi state session
 * yang bisa saling menimpa antar permintaan bersamaan.
 *
 * Akibatnya seluruh test di file ini gagal bukan karena aplikasinya rusak,
 * melainkan karena setup-nya menyiapkan session untuk gerbang yang sudah
 * dibongkar, tanpa pernah mengirim satu cookie pun. Yang dipertahankan di sini
 * adalah PROPERTI yang masih nyata dan masih melindungi pelapor: gerbang bukti,
 * kepemilikan laporan, penjagaan status, transisi status, dan jejak audit.
 */
class TrackingControllerRaceConditionTest extends TestCase
{
    use RefreshDatabase;

    private const DEVICE_ID = 'a1b2c3d4-e5f6-4a7b-8c9d-0e1f2a3b4c5d';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);

        // ReportObserver mengirim SendReportNotifications pada setiap perubahan
        // status. Dengan QUEUE_CONNECTION=sync, job WhatsApp-nya menghubungi
        // WAHA yang tidak dikonfigurasi di lingkungan test.
        Queue::fake();
    }

    /**
     * Dua aksi berurutan dari perangkat yang sama, dengan bukti pelacakan yang
     * sama, harus SAMA-SAMA berhasil.
     *
     * Ini pengganti langsung test race condition lama: yang dulu dikhawatirkan
     * adalah satu permintaan mencabut akses permintaan lain. Pada model cookie,
     * bukti pelacakan bersifat idempoten dan tidak pernah dihapus oleh aksi
     * pelapor, jadi properti yang benar untuk diuji adalah tidak ada aksi valid
     * yang dipulangkan ke formulir.
     */
    public function testConsecutiveReporterActionsWithTheSameProofBothSucceed(): void
    {
        $report = $this->createReport(['status' => 'memerlukan_informasi']);

        foreach (['Catatan pertama dari pelapor.', 'Catatan kedua dari pelapor.'] as $note) {
            $response = $this->withReporterCookies($report)
                ->post(route('track.info', $report), ['note' => $note]);

            // assertRedirect membandingkan URL secara utuh, jadi mendarat di
            // formulir /lacak sudah otomatis gagal di sini. Yang perlu ditambah
            // adalah bukti aksinya benar-benar dijalankan, bukan hanya dialihkan.
            $response->assertRedirect(route('track.result'))
                ->assertSessionHas('status')
                ->assertSessionHasNoErrors();
        }

        $this->assertDatabaseCount('report_notes', 2);
    }

    /**
     * Pencarian yang berhasil menerbitkan cookie bukti pelacakan, dan halaman
     * hasil hanya bisa dibuka dengan bukti itu.
     */
    public function testSuccessfulSearchIssuesTrackingProofUsableOnTheResultPage(): void
    {
        $report = $this->createReport();

        $response = $this->withCookie('laporin_device_id', self::DEVICE_ID)
            ->post(route('track.search'), [
                'report_number' => $report->report_number,
                'access_code' => '123456',
            ]);

        $response->assertRedirect(route('track.result'));
        $response->assertCookie('laporin_tracking_proof', $this->trackingProof($report));

        $this->withReporterCookies($report)
            ->get(route('track.result'))
            ->assertOk()
            ->assertSee($report->report_number);
    }

    /**
     * Tanpa bukti pelacakan yang sah, aksi pelapor ditolak dan pelapor
     * dipulangkan ke formulir dengan pesan yang bisa ditindaklanjuti sendiri.
     *
     * Pengganti test TTL lama. Batas waktunya kini melekat pada masa berlaku
     * cookie (15 menit), sehingga yang bisa dan perlu diuji dari sisi aplikasi
     * adalah perilaku saat buktinya tidak ada atau tidak cocok.
     */
    public function testMissingTrackingProofBlocksTheActionAndExplainsHowToRecover(): void
    {
        $report = $this->createReport(['status' => 'memerlukan_informasi']);

        $response = $this->withCookie('laporin_device_id', self::DEVICE_ID)
            ->post(route('track.info', $report), ['note' => 'Catatan tanpa bukti pelacakan.']);

        $response->assertRedirect(route('track.form'))->assertSessionHas('errors');

        $errors = session('errors');
        foreach (['report_number', 'access_code'] as $field) {
            $this->assertStringContainsString(
                'sudah berakhir',
                strtolower((string) $errors->first($field)),
                "Pesan pemulihan harus menempel pada field {$field} karena pelapor memang harus mengisi ulang keduanya."
            );
        }

        $this->assertDatabaseCount('report_notes', 0);
        $this->assertSame('memerlukan_informasi', $report->fresh()->status);
    }

    /**
     * Tulisan pelapor tidak boleh hilang ketika bukti pelacakan sudah hangus.
     *
     * Textarea-nya menampung 3000 karakter dan jendelanya hanya 15 menit;
     * sebelumnya seluruh tulisan itu hilang tanpa sisa tepat pada saat pelapor
     * menekan Kirim, dan tidak ada jalan untuk mengembalikannya.
     */
    public function testNoteDraftSurvivesAnExpiredTrackingProofAndReappearsOnTheResultPage(): void
    {
        $report = $this->createReport(['status' => 'memerlukan_informasi']);
        $draft = 'Kronologi panjang yang tidak boleh hilang hanya karena sesi 15 menit habis.';

        $this->withCookie('laporin_device_id', self::DEVICE_ID)
            ->post(route('track.info', $report), ['note' => $draft])
            ->assertRedirect(route('track.form'));

        $this->withReporterCookies($report)
            ->get(route('track.result'))
            ->assertOk()
            ->assertSee($draft);
    }

    /**
     * Bukti pelacakan laporan A tidak boleh membuka atau mengubah laporan B.
     */
    public function testTrackingProofIsBoundToASingleReport(): void
    {
        $reportA = $this->createReport(['status' => 'memerlukan_informasi']);
        $reportB = $this->createReport(['status' => 'memerlukan_informasi']);

        $this->withReporterCookies($reportA)
            ->post(route('track.info', $reportB), ['note' => 'Percobaan akses lintas laporan.'])
            ->assertRedirect(route('track.form'))
            ->assertSessionHas('errors');

        $this->assertDatabaseCount('report_notes', 0);
        $this->assertSame('memerlukan_informasi', $reportB->fresh()->status);
    }

    /**
     * addInfo hanya berlaku untuk status memerlukan_informasi, dibuka_kembali,
     * dan menunggu_konfirmasi.
     */
    public function testStatusValidationForAddInfo(): void
    {
        $report = $this->createReport(['status' => 'selesai']);

        $response = $this->withReporterCookies($report)
            ->post(route('track.info', $report), ['note' => 'Menambah info ke laporan yang sudah selesai.']);

        $response->assertRedirect(route('track.result'))->assertSessionHas('errors');

        $this->assertStringContainsString('tidak tersedia', (string) session('errors')->first('report'));
        $this->assertSame('selesai', $report->fresh()->status);
    }

    /**
     * confirmComplete hanya berlaku saat status tepat menunggu_konfirmasi.
     */
    public function testStatusValidationForConfirmComplete(): void
    {
        $report = $this->createReport(['status' => 'dibuka_kembali']);

        $response = $this->withReporterCookies($report)
            ->post(route('track.confirm', $report));

        $response->assertRedirect(route('track.result'))->assertSessionHas('errors');

        $this->assertStringContainsString('menunggu konfirmasi', (string) session('errors')->first('report'));
        $this->assertSame('dibuka_kembali', $report->fresh()->status);
    }

    /**
     * memerlukan_informasi -> dibuka_kembali, lalu dibuka_kembali tetap
     * dibuka_kembali, dan menunggu_konfirmasi -> selesai.
     */
    public function testStatusTransitions(): void
    {
        $report = $this->createReport(['status' => 'memerlukan_informasi']);

        $this->withReporterCookies($report)
            ->post(route('track.info', $report), ['note' => 'Menambah informasi.']);
        $this->assertSame('dibuka_kembali', $report->fresh()->status);

        $this->withReporterCookies($report)
            ->post(route('track.info', $report), ['note' => 'Menambah informasi lagi.']);
        $this->assertSame('dibuka_kembali', $report->fresh()->status);

        $awaiting = $this->createReport(['status' => 'menunggu_konfirmasi']);
        $this->withReporterCookies($awaiting)->post(route('track.confirm', $awaiting));
        $this->assertSame('selesai', $awaiting->fresh()->status);
    }

    /**
     * Setiap transisi status meninggalkan ReportStatusHistory yang lengkap.
     */
    public function testAuditTrailRecording(): void
    {
        $report = $this->createReport(['status' => 'memerlukan_informasi']);
        $before = $report->histories()->count();

        $this->withReporterCookies($report)
            ->post(route('track.info', $report), ['note' => 'Menambah informasi.']);

        $this->assertGreaterThan($before, $report->fresh()->histories()->count());

        $latest = $report->fresh()->histories()->latest()->first();
        $this->assertSame('memerlukan_informasi', $latest->previous_status);
        $this->assertSame('dibuka_kembali', $latest->new_status);
        $this->assertSame('reporter', $latest->actor_type);
        $this->assertNotEmpty($latest->public_note);
    }

    // ========== Helper ==========

    /**
     * Kedua cookie yang dituntut aksi pelapor: pengikatan perangkat dan bukti
     * pelacakan. Nilai buktinya dibangun dengan formula yang sama seperti
     * TrackingController::trackingProofCookie().
     */
    private function withReporterCookies(Report $report): static
    {
        return $this->withCookies([
            'laporin_device_id' => self::DEVICE_ID,
            'laporin_tracking_proof' => $this->trackingProof($report),
        ]);
    }

    private function trackingProof(Report $report): string
    {
        return $report->id.'|'.hash_hmac('sha256', (string) $report->access_code_hash, config('app.key'));
    }

    private function createReport(array $overrides = []): Report
    {
        return Report::create(array_merge([
            'report_number' => 'LPR'.str_pad((string) random_int(1, 9999999999), 10, '0', STR_PAD_LEFT),
            'public_token' => (string) Str::uuid(),
            'access_code_hash' => Hash::make('123456'),
            'reporter_type' => 'staff',
            'reporter_name' => 'Pelapor Test',
            'report_type' => 'violation',
            'title' => 'Laporan untuk preservation test',
            'incident_date' => now()->toDateString(),
            'description' => 'Test description untuk preservation test.',
            'urgency' => 'sedang',
            'status' => 'memerlukan_informasi',
            'assigned_to_role' => 'kesiswaan',
            'consent_accepted_at' => now(),
            'reporter_email' => 'reporter@test.local',
            // Nilai yang sama dihitung PublicReportService saat laporan dibuat.
            // Tanpa ini setiap aksi pelapor berhenti di gerbang perangkat.
            'submitted_device_hash' => hash_hmac('sha256', self::DEVICE_ID, config('app.key')),
        ], $overrides));
    }
}
