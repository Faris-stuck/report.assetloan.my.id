<?php

namespace Tests\Feature;

use App\Models\DamageDetail;
use App\Models\Report;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Models\ViolationType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\MessageBag;
use Illuminate\Support\Str;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class ValidationWarningTest extends TestCase
{
    use RefreshDatabase;

    public function test_wrong_public_captcha_returns_to_form_with_warning_instead_of_error_page(): void
    {
        $this->seed();

        $class = SchoolClass::firstOrFail();

        $response = $this->withSession(['math_captcha_answer' => 12, 'report_submit_token' => 'test-submit-token'])
            ->from('/')
            ->post(route('public.report.store'), [
                'reporter_type' => 'siswa',
                'reporter_name' => 'Pelapor Test',
                'reporter_phone' => '+6281234567894',
                'reporter_class_id' => $class->id,
                'related_class_id' => $class->id,
                'report_type' => 'violation',
                'title' => 'Laporan test validasi',
                'incident_date' => now()->toDateString(),
                'description' => 'Deskripsi laporan minimal untuk test validasi.',
                'urgency' => 'sedang',
                'alleged_actor_name' => 'Pelaku Test',
                'consent' => '1',
                'captcha' => '99',
            ]);

        $response->assertRedirect('/');
        $response->assertSessionHasErrors(['captcha']);
        $this->assertDatabaseCount('reports', 0);
    }

    public function test_expired_tracking_additional_info_redirects_to_tracking_form_with_warning(): void
    {
        $this->seed();

        $report = $this->report(['status' => 'memerlukan_informasi']);

        $response = $this->from(route('track.form'))
            ->post(route('track.info', $report), ['note' => 'Tambahan info']);

        $response->assertRedirect(route('track.form'));
        $response->assertSessionHasErrors(['access_code']);
    }

    public function test_kesiswaan_stale_report_action_returns_warning_instead_of_conflict_page(): void
    {
        $this->seed();

        $user = User::factory()->create(['role' => 'kesiswaan', 'is_active' => true]);
        $report = $this->report(['report_type' => 'violation', 'status' => 'selesai', 'assigned_to_role' => 'kesiswaan']);

        $response = $this->actingAs($user)
            ->from(route('kesiswaan.index'))
            ->post(route('kesiswaan.process', $report), [
                'student_id' => Student::firstOrFail()->id,
                'violation_type_id' => ViolationType::firstOrFail()->id,
                'note' => 'Catatan valid',
            ]);

        $response->assertRedirect(route('kesiswaan.index'));
        $response->assertSessionHasErrors(['report']);
    }

    public function test_sarpras_stale_report_action_returns_warning_instead_of_conflict_page(): void
    {
        $this->seed();

        $user = User::factory()->create(['role' => 'sarpras', 'is_active' => true]);
        $report = $this->report(['report_type' => 'damage', 'status' => 'selesai', 'assigned_to_role' => 'sarpras']);
        DamageDetail::create([
            'report_id' => $report->id,
            'item_name' => 'Proyektor',
            'damage_condition' => 'Sudah selesai',
            'priority' => 'sedang',
        ]);

        $response = $this->actingAs($user)
            ->from(route('sarpras.index'))
            ->post(route('sarpras.process', $report), [
                'priority' => 'sedang',
                'note' => 'Catatan valid',
            ]);

        $response->assertRedirect(route('sarpras.index'));
        $response->assertSessionHasErrors(['report']);
    }

    public function test_admin_can_create_general_qr_without_relation_fields(): void
    {
        $this->seed();

        $user = User::factory()->create([
            'role' => 'superadmin',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)
            ->from(route('admin.qrcodes.index'))
            ->post(route('admin.qrcodes.store'), [
                'qr_name' => 'QR Umum Test',
                'qr_type' => 'general',
            ]);

        $response->assertRedirect(route('admin.qrcodes.index'));
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('qr_codes', [
            'qr_name' => 'QR Umum Test',
            'qr_type' => 'general',
        ]);
    }

    public function test_wrong_login_password_returns_warning_instead_of_error_page(): void
    {
        $this->seed();

        $response = $this->from(route('login'))
            ->post(route('login'), [
                'login' => 'admin@laporin.local',
                'password' => 'password-salah',
            ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors(['login']);
    }

    public function test_report_form_renders_server_errors_for_fields(): void
    {
        $response = $this->withSession([
            'errors' => session('errors', new ViewErrorBag)->put(
                'default',
                new MessageBag(['captcha' => ['CAPTCHA salah.']])
            ),
        ])->get('/');

        $response->assertOk();
        $response->assertSee('id="validation-errors-json"', false);
        $response->assertSee('CAPTCHA salah', false);
    }

    private function report(array $overrides = []): Report
    {
        $class = SchoolClass::firstOrFail();

        return Report::create(array_merge([
            'report_number' => 'LPR'.now()->format('Ym').str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT),
            'public_token' => (string) Str::uuid(),
            'access_code_hash' => Hash::make('123456'),
            'reporter_type' => 'siswa',
            'reporter_name' => 'Pelapor Test',
            'reporter_class_id' => $class->id,
            'report_type' => 'violation',
            'title' => 'Judul laporan test',
            'incident_date' => now()->toDateString(),
            'description' => 'Deskripsi laporan test.',
            'urgency' => 'sedang',
            'status' => 'menunggu_verifikasi',
            'assigned_to_role' => 'kesiswaan',
            'consent_accepted_at' => now(),
        ], $overrides));
    }
}
