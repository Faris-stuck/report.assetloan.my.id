<?php

namespace Tests\Feature;

use App\Models\Report;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Middleware\ThrottleRequestsWithRedis;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Pelacakan publik terikat pada perangkat: TrackingController::deviceMatchesReport()
 * membandingkan cookie 'laporin_device_id' dengan reports.submitted_device_hash,
 * dan aksi yang mengubah status juga menuntut cookie bukti 'laporin_tracking_proof'.
 * Tidak ada test lain di suite ini yang mengirim cookie perangkat sama sekali,
 * sehingga dua gerbang tersebut tidak pernah diuji pada kondisi cocok.
 */
class TrackingDeviceBindingTest extends TestCase
{
    use RefreshDatabase;

    private const REPORT_NUMBER = 'LAP-ABC234-XYZ789';
    private const ACCESS_CODE = '123456';
    private const DEVICE_ID = 'a1b2c3d4-e5f6-4a7b-8c9d-0e1f2a3b4c5d';
    private const OTHER_DEVICE_ID = 'f1e2d3c4-b5a6-4f7e-8d9c-0b1a2f3e4d5c';

    protected function setUp(): void
    {
        parent::setUp();

        // Rute /lacak memakai throttle:public-tracking, dan bootstrap/app.php
        // memanggil throttleWithRedis() sehingga alias 'throttle' me-resolve ke
        // varian Redis. Dua-duanya dimatikan agar test tidak menuntut Redis.
        $this->withoutMiddleware([ThrottleRequests::class, ThrottleRequestsWithRedis::class]);

        // ReportObserver mengirim SendReportNotifications setiap kali status
        // berubah. QUEUE_CONNECTION=sync akan menjalankannya inline dan job
        // WhatsApp menghubungi WAHA yang tidak dikonfigurasi di test.
        Queue::fake();
    }

    public function test_tracking_search_from_the_submitting_device_returns_status_and_proof_cookie(): void
    {
        $report = $this->report();

        $response = $this->withCookie('laporin_device_id', self::DEVICE_ID)
            ->post(route('track.search'), [
                'report_number' => self::REPORT_NUMBER,
                'access_code' => self::ACCESS_CODE,
            ]);

        $response->assertOk()->assertSee($report->report_number);
        $response->assertCookie('laporin_tracking_proof', $this->trackingProof($report));
    }

    public function test_tracking_search_rejects_a_wrong_access_code(): void
    {
        $this->report();

        $this->withCookie('laporin_device_id', self::DEVICE_ID)
            ->from(route('track.form'))
            ->post(route('track.search'), [
                'report_number' => self::REPORT_NUMBER,
                'access_code' => '654321',
            ])
            ->assertRedirect(route('track.form'))
            ->assertSessionHasErrors(['report_number']);
    }

    public function test_tracking_search_rejects_a_correct_access_code_from_another_device(): void
    {
        $this->report();

        $this->withCookie('laporin_device_id', self::OTHER_DEVICE_ID)
            ->from(route('track.form'))
            ->post(route('track.search'), [
                'report_number' => self::REPORT_NUMBER,
                'access_code' => self::ACCESS_CODE,
            ])
            ->assertRedirect(route('track.form'))
            ->assertSessionHasErrors(['report_number']);

        $this->assertStringContainsString(
            'perangkat',
            (string) session('errors')->first('report_number')
        );
    }

    public function test_device_cookie_alone_cannot_add_information_to_a_report(): void
    {
        $report = $this->report(['status' => 'memerlukan_informasi']);

        $this->withCookie('laporin_device_id', self::DEVICE_ID)
            ->from(route('track.form'))
            ->post(route('track.info', $report), ['note' => 'Percobaan tanpa cookie bukti.'])
            ->assertRedirect(route('track.form'))
            ->assertSessionHasErrors(['access_code']);

        $this->assertDatabaseCount('report_notes', 0);
        $this->assertSame('memerlukan_informasi', $report->refresh()->status);
    }

    public function test_device_cookie_with_tracking_proof_can_add_information(): void
    {
        $report = $this->report(['status' => 'memerlukan_informasi']);

        $this->withCookies([
            'laporin_device_id' => self::DEVICE_ID,
            'laporin_tracking_proof' => $this->trackingProof($report),
        ])
            ->from(route('track.form'))
            ->post(route('track.info', $report), ['note' => 'Kronologi tambahan dari pelapor.'])
            ->assertRedirect(route('track.form'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('report_notes', [
            'report_id' => $report->id,
            'author_type' => 'reporter',
            'note' => 'Kronologi tambahan dari pelapor.',
        ]);
        $this->assertSame('dibuka_kembali', $report->refresh()->status);
        $this->assertDatabaseHas('report_status_histories', [
            'report_id' => $report->id,
            'actor_type' => 'reporter',
            'previous_status' => 'memerlukan_informasi',
            'new_status' => 'dibuka_kembali',
        ]);
    }

    /**
     * Nilai cookie bukti dibangun dengan formula yang sama seperti
     * TrackingController::search(): id laporan + HMAC dari access_code_hash.
     */
    private function trackingProof(Report $report): string
    {
        return $report->id.'|'.hash_hmac('sha256', (string) $report->access_code_hash, config('app.key'));
    }

    private function report(array $overrides = []): Report
    {
        return Report::create(array_merge([
            'report_number' => self::REPORT_NUMBER,
            'public_token' => (string) Str::uuid(),
            'access_code_hash' => Hash::make(self::ACCESS_CODE),
            'reporter_type' => 'staff',
            'reporter_name' => 'Pelapor Tracking Perangkat',
            'report_type' => 'violation',
            'title' => 'Laporan uji pengikatan perangkat',
            'incident_date' => now()->toDateString(),
            'description' => 'Laporan untuk menguji gerbang perangkat pada pelacakan publik.',
            'urgency' => 'sedang',
            'status' => 'menunggu_verifikasi',
            'assigned_to_role' => 'kesiswaan',
            'consent_accepted_at' => now(),
            // Nilai yang sama dihitung PublicReportService::reportData() saat laporan dibuat.
            'submitted_device_hash' => hash_hmac('sha256', self::DEVICE_ID, config('app.key')),
        ], $overrides));
    }
}
