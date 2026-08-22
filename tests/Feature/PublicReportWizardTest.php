<?php

namespace Tests\Feature;

use App\Jobs\SendReportNotifications;
use App\Models\Report;
use App\Models\SchoolClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Wizard publik empat langkah (`POST /lapor/langkah/{step}`) adalah satu-satunya
 * jalur kirim laporan yang dipakai UI: resources/views/public/report-form.blade.php
 * mem-post ke route('public.report.step.store'). Semua test laporan lain di suite
 * ini mem-post ke route('public.report.store') (PublicReportController::store)
 * dengan session 'math_captcha_answer', yaitu cabang CAPTCHA legacy dari endpoint
 * yang tidak pernah dipanggil browser. Kelas ini menutup celah tersebut.
 */
class PublicReportWizardTest extends TestCase
{
    use RefreshDatabase;

    private const CAPTCHA_A = 3;
    private const CAPTCHA_B = 5;
    private const CAPTCHA_ANSWER = 8;

    private const ITEM_NAME = 'AC Lab Komputer';
    private const DESCRIPTION = 'AC di lab komputer mengeluarkan air dan tidak dingin.';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        // Rute wizard memakai throttle:public-wizard. Middleware-nya dimatikan
        // supaya kuota tidak menumpuk antar test di kelas ini.
        $this->withoutMiddleware([ThrottleRequests::class]);

        // QUEUE_CONNECTION=sync menjalankan SendReportWhatsAppNotification inline;
        // tanpa WAHA_API_KEY job itu melempar RuntimeException dan menggagalkan
        // request. Antrean difake agar yang diuji tetap controller wizard.
        Queue::fake();
    }

    public function test_report_start_page_creates_per_tab_wizard_form_state(): void
    {
        $this->get(route('public.report'))->assertOk();

        $token = session('report_submit_token');
        $forms = session('report_submit_forms');

        $this->assertIsString($token);
        $this->assertNotSame('', $token);
        $this->assertIsArray($forms);
        $this->assertArrayHasKey($token, $forms);

        $state = $forms[$token];

        $this->assertSame([], $state['wizard_data']);
        $this->assertNull($state['qr_code_id']);
        $this->assertSame(
            (int) $state['captcha_a'] + (int) $state['captcha_b'],
            (int) $state['captcha_answer']
        );
    }

    public function test_wizard_step_without_form_state_redirects_to_report_start(): void
    {
        $this->get(route('public.report.step', 2))
            ->assertRedirect(route('public.report'))
            ->assertSessionHasErrors(['form']);
    }

    public function test_public_wizard_creates_exactly_one_damage_report_end_to_end(): void
    {
        $token = $this->startWizard();
        $this->completeDamageStepsOneToThree($token);

        $response = $this->post(route('public.report.step.store', 4), [
            'report_submit_token' => $token,
            'consent' => '1',
            'captcha' => (string) self::CAPTCHA_ANSWER,
        ]);

        $this->assertDatabaseCount('reports', 1);
        $report = Report::query()->sole();

        $response->assertRedirect(route('public.report.success', $report->public_token));

        $this->assertMatchesRegularExpression('/^LAP-[A-Z2-9]{6}-[A-Z2-9]{6}$/', $report->report_number);
        $this->assertSame('sarpras', $report->assigned_to_role);
        $this->assertSame('menunggu_verifikasi', $report->status);

        // Keduanya diturunkan server-side oleh wizardStoreStep(): title dari
        // item_name dan reporter_type dipaksa 'siswa'. Tidak satu pun
        // dikirim oleh formulir publik.
        $this->assertSame(self::ITEM_NAME, $report->title);
        $this->assertSame('siswa', $report->reporter_type);

        $this->assertDatabaseHas('damage_details', [
            'report_id' => $report->id,
            'item_name' => self::ITEM_NAME,
            'damage_condition' => self::DESCRIPTION,
        ]);

        Queue::assertPushed(
            SendReportNotifications::class,
            fn (SendReportNotifications $job): bool => $job->reportId === $report->id
                && $job->event === 'created'
        );

        // State form dibuang setelah sukses supaya token tidak dapat dipakai lagi.
        $forms = session('report_submit_forms');
        $this->assertIsArray($forms);
        $this->assertArrayNotHasKey($token, $forms);
    }

    public function test_public_wizard_rejects_wrong_captcha_without_creating_report(): void
    {
        $token = $this->startWizard();
        $this->completeDamageStepsOneToThree($token);

        $this->post(route('public.report.step.store', 4), [
            'report_submit_token' => $token,
            'consent' => '1',
            'captcha' => (string) (self::CAPTCHA_ANSWER + 1),
        ])
            ->assertRedirect(route('public.report.step', 4))
            ->assertSessionHasErrors(['captcha']);

        $this->assertDatabaseCount('reports', 0);
        Queue::assertNothingPushed();
    }

    public function test_replayed_final_step_cannot_create_a_second_report(): void
    {
        $token = $this->startWizard();
        $this->completeDamageStepsOneToThree($token);

        $forms = session('report_submit_forms');
        $this->assertIsArray($forms);
        $stateWithDraft = $forms[$token];

        $payload = [
            'report_submit_token' => $token,
            'consent' => '1',
            'captcha' => (string) self::CAPTCHA_ANSWER,
        ];

        $this->post(route('public.report.step.store', 4), $payload)->assertRedirect();
        $this->assertDatabaseCount('reports', 1);

        // Replay pertama: state form sudah dibuang dari session oleh submit sukses.
        $this->post(route('public.report.step.store', 4), $payload)
            ->assertRedirect(route('public.report'))
            ->assertSessionHasErrors(['form']);
        $this->assertDatabaseCount('reports', 1);

        // Replay kedua dengan state form dipulihkan, mensimulasikan session yang
        // belum ter-update (tab lain / tombol kirim ditekan berulang). Yang harus
        // menahan di sini adalah consume key di Cache, bukan efek samping
        // pembersihan session.
        $forms = session('report_submit_forms');
        $this->assertIsArray($forms);
        $forms[$token] = $stateWithDraft;
        $this->withSession([
            'report_submit_token' => $token,
            'report_submit_forms' => $forms,
        ]);

        $this->post(route('public.report.step.store', 4), $payload)
            ->assertRedirect(route('public.report.step', 4))
            ->assertSessionHasErrors(['form']);
        $this->assertDatabaseCount('reports', 1);
    }

    public function test_public_wizard_stores_attachment_on_private_disk(): void
    {
        Storage::fake('private');

        $token = $this->startWizard();
        $this->completeDamageStepsOneToThree($token);

        $this->post(route('public.report.step.store', 4), [
            'report_submit_token' => $token,
            'consent' => '1',
            'captcha' => (string) self::CAPTCHA_ANSWER,
            'attachments' => [UploadedFile::fake()->image('bukti.jpg', 100, 100)],
        ])->assertRedirect();

        $report = Report::query()->sole();
        $attachment = $report->attachments()->sole();

        $this->assertSame('reporter', $attachment->uploader_type);
        $this->assertSame('bukti.jpg', $attachment->original_name);
        $this->assertSame('image/jpeg', $attachment->mime_type);
        Storage::disk('private')->assertExists($attachment->file_path);
    }

    /**
     * Menyiapkan state form seperti PublicReportController::create(), tetapi
     * dengan CAPTCHA tetap supaya test tidak bergantung pada random_int().
     */
    private function startWizard(): string
    {
        $token = (string) Str::uuid();

        $this->withSession([
            'report_submit_token' => $token,
            'report_submit_forms' => [
                $token => [
                    'captcha_answer' => self::CAPTCHA_ANSWER,
                    'captcha_a' => self::CAPTCHA_A,
                    'captcha_b' => self::CAPTCHA_B,
                    'wizard_data' => [],
                    'created_at' => now()->timestamp,
                    'qr_code_id' => null,
                ],
            ],
        ]);

        return $token;
    }

    private function completeDamageStepsOneToThree(string $token): void
    {
        $this->post(route('public.report.step.store', 1), [
            'report_submit_token' => $token,
            'reporter_name' => 'Siswa Pelapor Wizard',
            'reporter_class_id' => SchoolClass::where('is_active', true)->firstOrFail()->id,
            'reporter_absence_number' => '12',
            'reporter_phone' => '081234567890',
        ])->assertRedirect(route('public.report.step', 2));

        $this->post(route('public.report.step.store', 2), [
            'report_submit_token' => $token,
            'report_type' => 'damage',
        ])->assertRedirect(route('public.report.step', 3));

        $this->post(route('public.report.step.store', 3), [
            'report_submit_token' => $token,
            'item_name' => self::ITEM_NAME,
            'item_category' => 'Elektronik',
            'description' => self::DESCRIPTION,
            'urgency' => 'tinggi',
            'incident_date' => now()->toDateString(),
            'incident_time' => '08:30',
        ])->assertRedirect(route('public.report.step', 4));
    }
}
