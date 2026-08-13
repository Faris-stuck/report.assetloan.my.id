<?php

namespace Tests\Feature;

use App\Models\BullyingDetail;
use App\Models\DamageDetail;
use App\Models\Location;
use App\Models\QrCode;
use App\Models\Report;
use App\Models\ReportAttachment;
use App\Models\SchoolClass;
use App\Models\StaffUnit;
use App\Models\Student;
use App\Models\User;
use App\Models\ViolationType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class FlowAndButtonValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->withoutMiddleware(ThrottleRequests::class);
        RateLimiter::clear('127.0.0.1');
    }

    public function test_public_and_auth_navigation_pages_render_expected_menus(): void
    {
        $this->get('/')->assertOk()
            ->assertDontSee('Mulai Laporan')
            ->assertSee('Kirim Laporan');

        $this->get(route('track.form'))->assertOk()
            ->assertSee('Formulir Pelacakan')
            ->assertSee('Lacak Laporan');

        $this->get(route('login'))->assertOk()
            ->assertSee('Masuk LAPORIN')
            ->assertSee('Masuk');

        $superadmin = $this->user('admin@laporin.local');
        $this->actingAs($superadmin)->get(route('dashboard'))->assertOk()
            ->assertSee('Ringkasan LAPORIN')
            ->assertSee('Akun Pengguna')
            ->assertSee('Kode QR')
            ->assertSee('Riwayat Aktivitas')
            ->assertSee('Kesiswaan')
            ->assertSee('Sarpras');

        $this->actingAs($this->user('kesiswaan@laporin.local'))->get(route('dashboard'))->assertOk()
            ->assertSee('Kesiswaan')
            ->assertDontSee('Kode QR');

        $this->actingAs($this->user('sarpras@laporin.local'))->get(route('dashboard'))->assertOk()
            ->assertSee('Sarpras')
            ->assertDontSee('Akun Pengguna');

        $this->actingAs($this->user('wali@laporin.local'))->get(route('dashboard'))->assertOk()
            ->assertSee('Dasbor')
            ->assertDontSee('Akun Pengguna');

        $this->assertFalse(Route::has('siswa.point.pdf'));
    }

    public function test_tracking_form_accepts_copy_pasted_current_report_number_format(): void
    {
        $this->get(route('track.form'))->assertOk()
            ->assertSee('placeholder="LAP-ABC234-XYZ789"', false)
            ->assertSee('maxlength="24"', false)
            ->assertSee('maxlength="16"', false)
            ->assertSee('data-normalize-report-number', false)
            ->assertSee('data-normalize-access-code', false)
            ->assertDontSee('LAP-XXXXXX-XXXXXX');
    }

    public function test_public_violation_report_success_tracking_info_and_confirm_flow(): void
    {
        $class = SchoolClass::firstOrFail();
        $location = Location::firstOrFail();

        $response = $this->withSession(['math_captcha_answer' => 8, 'report_submit_token' => 'test-submit-token'])
            ->post(route('public.report.store'), [
                'reporter_type' => 'siswa',
                'reporter_name' => 'Pelapor Flow Violation',
                'reporter_phone' => '+6281234567890',
                'reporter_class_id' => $class->id,
                'related_class_id' => $class->id,
                'report_type' => 'violation',
                'title' => 'Laporan flow pelanggaran',
                'location_id' => $location->id,
                'incident_date' => now()->toDateString(),
                'description' => 'Deskripsi lengkap laporan pelanggaran untuk flow tracking.',
                'urgency' => 'sedang',
                'victim_name' => 'Korban Test',
                'victim_class_id' => $class->id,
                'alleged_actor_name' => 'Terduga Test',
                'alleged_actor_class_id' => $class->id,
                'consent' => '1',
                'captcha' => '8',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('access_code');

        $report = Report::query()->sole();
        $accessCode = session('access_code');

        $this->assertMatchesRegularExpression('/^LAP-[ABCDEFGHJKLMNPQRSTUVWXYZ23456789]{6}-[ABCDEFGHJKLMNPQRSTUVWXYZ23456789]{6}$/', $report->report_number);
        $this->assertDatabaseHas('bullying_details', ['report_id' => $report->id, 'victim_name' => 'Korban Test']);
        $this->assertDatabaseHas('report_status_histories', ['report_id' => $report->id, 'new_status' => 'menunggu_verifikasi']);

        $this->get(route('public.report.success', $report))->assertOk()
            ->assertSee($report->report_number)
            ->assertSee($accessCode);

        $this->post(route('track.search'), [
            'report_number' => $report->report_number,
            'access_code' => $accessCode,
        ])->assertOk()->assertSee($report->report_number);

        $report->update(['status' => 'memerlukan_informasi']);
        $this->post(route('track.info', $report), ['note' => 'Informasi tambahan dari pelapor.'])
            ->assertRedirect();
        $this->assertDatabaseHas('report_notes', [
            'report_id' => $report->id,
            'author_type' => 'reporter',
            'note' => 'Informasi tambahan dari pelapor.',
        ]);
        $this->assertSame('dibuka_kembali', $report->refresh()->status);
        $this->assertDatabaseHas('report_status_histories', [
            'report_id' => $report->id,
            'actor_type' => 'reporter',
            'previous_status' => 'memerlukan_informasi',
            'new_status' => 'dibuka_kembali',
        ]);

        $report->update(['status' => 'menunggu_konfirmasi']);
        $this->post(route('track.info', $report), ['note' => 'Laporan belum selesai, mohon dicek lagi.'])
            ->assertRedirect();
        $this->assertSame('dibuka_kembali', $report->refresh()->status);
        $this->assertDatabaseHas('report_status_histories', [
            'report_id' => $report->id,
            'actor_type' => 'reporter',
            'previous_status' => 'menunggu_konfirmasi',
            'new_status' => 'dibuka_kembali',
        ]);

        $report->update(['status' => 'menunggu_konfirmasi']);
        $this->post(route('track.confirm', $report))->assertRedirect();
        $this->assertSame('selesai', $report->refresh()->status);
    }

    public function test_public_damage_report_validation_and_creation_flow(): void
    {
        $class = SchoolClass::firstOrFail();
        $location = Location::firstOrFail();

        $this->withSession(['math_captcha_answer' => 9, 'report_submit_token' => 'test-submit-token'])
            ->from('/')
            ->post(route('public.report.store'), [
                'reporter_type' => 'siswa',
                'reporter_name' => 'Pelapor Damage Invalid',
                'reporter_phone' => '+6281234567891',
                'reporter_class_id' => $class->id,
                'report_type' => 'damage',
                'title' => 'Laporan damage invalid',
                'location_id' => $location->id,
                'incident_date' => now()->toDateString(),
                'description' => 'Deskripsi lengkap laporan kerusakan invalid.',
                'urgency' => 'sedang',
                'consent' => '1',
                'captcha' => '9',
            ])->assertRedirect('/')->assertSessionHasErrors(['item_name', 'damage_condition']);

        $this->assertDatabaseCount('reports', 0);

        $this->withSession(['math_captcha_answer' => 6, 'report_submit_token' => 'test-submit-token'])
            ->post(route('public.report.store'), [
                'reporter_type' => 'staff',
                'reporter_name' => 'Staf Pelapor Damage',
                'reporter_phone' => '+6281234567892',
                'reporter_staff_unit_id' => StaffUnit::firstOrFail()->id,
                'report_type' => 'damage',
                'title' => 'Proyektor ruang audit rusak',
                'custom_location' => 'Ruang Audit QA',
                'incident_date' => now()->toDateString(),
                'description' => 'Proyektor tidak menyala saat flow QA dijalankan.',
                'urgency' => 'tinggi',
                'item_name' => 'Proyektor Audit',
                'item_category' => 'Elektronik',
                'priority' => 'tinggi',
                'damage_condition' => 'Tidak menyala total.',
                'consent' => '1',
                'captcha' => '6',
            ])->assertRedirect();

        $report = Report::query()->sole();
        $this->assertSame('damage', $report->report_type);
        $this->assertSame('sarpras', $report->assigned_to_role);
        $this->assertDatabaseHas('damage_details', [
            'report_id' => $report->id,
            'item_name' => 'Proyektor Audit',
            'priority' => null,
        ]);
    }

    public function test_admin_buttons_forms_and_master_data_validation_flow(): void
    {
        $admin = $this->user('admin@laporin.local');
        $class = SchoolClass::firstOrFail();
        $location = Location::firstOrFail();

        $this->actingAs($admin)->get(route('admin.users.index'))->assertOk()
            ->assertSee('Tambah')
            ->assertSee('Manajemen Pengguna');

        $this->actingAs($admin)->from(route('admin.users.index'))->post(route('admin.users.store'), [
            'name' => 'User Password Lemah',
            'email' => 'weak@example.test',
            'password' => 'lemah',
            'role' => 'wali_kelas',
            'is_active' => '1',
        ])->assertRedirect(route('admin.users.index'))->assertSessionHasErrors(['password']);

        $this->actingAs($admin)->from(route('admin.users.index'))->post(route('admin.users.store'), [
            'name' => 'Wali Kelas Flow QA',
            'email' => 'wali.flow@example.test',
            'password' => 'password123',
            'role' => 'wali_kelas',
            'phone' => '+628123456789',
            'is_active' => '1',
        ])->assertRedirect(route('admin.users.index'))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['email' => 'wali.flow@example.test', 'role' => 'wali_kelas', 'is_active' => true]);

        $this->actingAs($admin)
            ->get(route('admin.qrcodes.index'))
            ->assertOk();

        $this->actingAs($admin)
            ->from(route('admin.qrcodes.index'))
            ->post(route('admin.qrcodes.store'), [
                'qr_name' => 'QR Flow QA',
                'qr_type' => 'general',
            ])
            ->assertRedirect(route('admin.qrcodes.index'))
            ->assertSessionHasNoErrors();

        $qr = QrCode::where('qr_name', 'QR Flow QA')
            ->firstOrFail();

        $this->assertSame('general', $qr->qr_type);

        $this->actingAs($admin)
            ->get(route('admin.qrcodes.download', $qr))
            ->assertOk();

        $this->actingAs($admin)
            ->post(route('admin.qrcodes.deactivate', $qr))
            ->assertRedirect();

        $this->assertFalse($qr->refresh()->is_active);

        foreach ($this->validMasterPayloads($class) as $resource => [$requiredField, $payload]) {
            $this->actingAs($admin)->get(route('admin.master.index', $resource))->assertOk()->assertSee('Tambah');
            $this->actingAs($admin)->from(route('admin.master.index', $resource))->post(route('admin.master.store', $resource), [])
                ->assertRedirect(route('admin.master.index', $resource))
                ->assertSessionHasErrors([$requiredField]);
            $this->actingAs($admin)->from(route('admin.master.index', $resource))->post(route('admin.master.store', $resource), $payload)
                ->assertRedirect(route('admin.master.index', $resource))
                ->assertSessionHasNoErrors();
            $this->actingAs($admin)->get(route('admin.master.index', $resource))->assertOk()
                ->assertSee('Update')
                ->assertSee('Hapus');
        }
    }

    public function test_kesiswaan_process_and_reject_buttons_work_with_validation(): void
    {
        $kesiswaan = $this->user('kesiswaan@laporin.local');
        $student = Student::firstOrFail();
        $type = ViolationType::firstOrFail();

        $processReport = $this->report(['report_type' => 'violation', 'assigned_to_role' => 'kesiswaan']);
        BullyingDetail::create(['report_id' => $processReport->id, 'victim_name' => 'Korban Kesiswaan']);

        $this->actingAs($kesiswaan)->get(route('kesiswaan.index'))->assertOk()
            ->assertSee('Proses')
            ->assertSee('Tolak');

        $this->actingAs($kesiswaan)->from(route('kesiswaan.index'))->post(route('kesiswaan.process', $processReport), [
            'student_id' => $student->id,
            'violation_type_id' => $type->id,
            'note' => 'Pembinaan flow QA.',
        ])->assertRedirect(route('kesiswaan.index'))->assertSessionHasNoErrors();
        $this->assertSame(max(0, 100 - $type->point_reduction), $student->refresh()->point);
        $this->assertSame('sedang_ditangani', $processReport->refresh()->status);
        $this->assertDatabaseHas('student_violations', [
            'report_id' => $processReport->id,
            'student_id' => $student->id,
            'point_reduced' => $type->point_reduction,
        ]);

        $rejectReport = $this->report([
            'report_number' => 'LPR'.now()->format('Ym').'9876',
            'report_type' => 'violation',
            'assigned_to_role' => 'kesiswaan',
        ]);
        BullyingDetail::create(['report_id' => $rejectReport->id]);

        $this->actingAs($kesiswaan)->from(route('kesiswaan.index'))->post(route('kesiswaan.reject', $rejectReport), [])
            ->assertRedirect(route('kesiswaan.index'))
            ->assertSessionHasErrors(['reason']);

        $this->actingAs($kesiswaan)->from(route('kesiswaan.index'))->post(route('kesiswaan.reject', $rejectReport), [
            'reason' => 'Bukti tidak cukup setelah diverifikasi.',
        ])->assertRedirect(route('kesiswaan.index'))->assertSessionHasNoErrors();
        $this->assertSame('ditolak', $rejectReport->refresh()->status);
        $this->assertSame('Bukti tidak cukup setelah diverifikasi.', $rejectReport->rejection_reason);
    }

    public function test_sarpras_schedule_and_completion_buttons_work_with_validation(): void
    {
        Storage::fake('private');
        $sarpras = $this->user('sarpras@laporin.local');
        $report = $this->report([
            'report_type' => 'damage',
            'assigned_to_role' => 'sarpras',
            'report_number' => 'LPR'.now()->format('Ym').'1111',
        ]);
        DamageDetail::create([
            'report_id' => $report->id,
            'item_name' => 'Kursi Sarpras',
            'damage_condition' => 'Kaki kursi patah.',
            'priority' => 'sedang',
        ]);

        $this->actingAs($sarpras)->get(route('sarpras.index'))->assertOk()
            ->assertSee('Simpan')
            ->assertSee('Foto setelah diperbaiki')
            ->assertSee('Waktu Perbaikan');

        $this->actingAs($sarpras)->from(route('sarpras.index'))->post(route('sarpras.process', $report), [
            'priority' => 'bukan-prioritas',
        ])->assertRedirect(route('sarpras.index'))->assertSessionHasErrors(['priority']);

        $this->actingAs($sarpras)->from(route('sarpras.index'))->post(route('sarpras.process', $report), [
            'priority' => 'tinggi',
            'scheduled_repair_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'note' => 'Dijadwalkan besok.',
        ])->assertRedirect(route('sarpras.index'))->assertSessionHasNoErrors();
        $this->assertSame('sedang_ditangani', $report->refresh()->status);
        $this->assertSame('tinggi', $report->damageDetail->refresh()->priority);

        $this->actingAs($sarpras)->from(route('sarpras.index'))->post(route('sarpras.process', $report), [
            'priority' => 'tinggi',
            'note' => 'Perbaikan selesai dengan foto.',
            'repair_photo' => UploadedFile::fake()->image('after-repair.jpg', 40, 40),
        ])->assertRedirect(route('sarpras.index'))->assertSessionHasNoErrors();
        $this->assertSame('selesai', $report->refresh()->status);
        $attachment = ReportAttachment::where('report_id', $report->id)->firstOrFail();
        Storage::disk('private')->assertExists($attachment->file_path);
    }

    public function test_detail_note_attachment_and_read_only_wali_access_work(): void
    {
        Storage::fake('private');
        $class = SchoolClass::firstOrFail();
        $report = $this->report([
            'report_type' => 'violation',
            'status' => 'sedang_ditangani',
            'assigned_to_role' => 'kesiswaan',
            'report_number' => 'LPR'.now()->format('Ym').'2222',
            'reporter_class_id' => $class->id,
            'related_class_id' => $class->id,
        ]);
        BullyingDetail::create(['report_id' => $report->id, 'victim_class_id' => $class->id]);
        Storage::disk('private')->put('report-attachments/'.$report->id.'/bukti.txt', 'isi bukti');
        $attachment = ReportAttachment::create([
            'report_id' => $report->id,
            'uploader_type' => 'reporter',
            'original_name' => 'bukti.txt',
            'stored_name' => 'bukti.txt',
            'file_path' => 'report-attachments/'.$report->id.'/bukti.txt',
            'mime_type' => 'text/plain',
            'file_size' => 9,
            'attachment_type' => 'initial_evidence',
        ]);

        $admin = $this->user('admin@laporin.local');
        $this->actingAs($admin)->get(route('reports.show', $report))->assertOk()
            ->assertSee('Tambah Catatan')
            ->assertSee('bukti.txt');

        $this->actingAs($admin)->from(route('reports.show', $report))->post(route('reports.notes', $report), [
            'note' => 'Catatan internal flow QA.',
            'visibility' => 'internal',
        ])->assertRedirect(route('reports.show', $report))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('report_notes', [
            'report_id' => $report->id,
            'note' => 'Catatan internal flow QA.',
            'visibility' => 'internal',
        ]);

        $this->actingAs($admin)->from(route('reports.show', $report))->post(route('reports.notes', $report), [
            'note' => '',
            'visibility' => 'publik-bebas',
        ])->assertRedirect(route('reports.show', $report))->assertSessionHasErrors(['note', 'visibility']);

        $this->actingAs($admin)->get(route('attachments.download', $attachment))->assertOk();

        $wali = $this->user('wali@laporin.local');
        $this->actingAs($wali)->get(route('reports.show', $report))->assertOk()
            ->assertDontSee('Tambah Catatan')
            ->assertDontSee('Kirim ke Konfirmasi Pelapor');
        $this->assertFalse(Route::has('reports.wali-confirm'));

        $kesiswaan = $this->user('kesiswaan@laporin.local');
        $this->actingAs($kesiswaan)->from(route('kesiswaan.index'))->post(route('kesiswaan.complete', $report))
            ->assertRedirect(route('kesiswaan.index'))->assertSessionHasNoErrors();
        $this->assertSame('menunggu_konfirmasi', $report->refresh()->status);
    }

    public function test_role_protected_routes_block_wrong_roles_but_allow_superadmin_operator_menus(): void
    {
        $wali = $this->user('wali@laporin.local');
        $kesiswaan = $this->user('kesiswaan@laporin.local');
        $superadmin = $this->user('admin@laporin.local');

        $this->actingAs($wali)->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($wali)->get(route('kesiswaan.index'))->assertForbidden();
        $this->actingAs($kesiswaan)->get(route('sarpras.index'))->assertForbidden();
        $this->actingAs($superadmin)->get(route('kesiswaan.index'))->assertOk();
        $this->actingAs($superadmin)->get(route('sarpras.index'))->assertOk();
    }

    private function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    private function report(array $overrides = []): Report
    {
        $class = SchoolClass::firstOrFail();
        $location = Location::firstOrFail();

        $number = 'LPR'.now()->format('Ym').str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);

        return Report::create(array_merge([
            'report_number' => $number,
            'public_token' => (string) Str::uuid(),
            'access_code_hash' => Hash::make('123456'),
            'reporter_type' => 'siswa',
            'reporter_name' => 'Pelapor Flow QA',
            'reporter_class_id' => $class->id,
            'report_type' => 'violation',
            'title' => 'Judul laporan flow QA',
            'location_id' => $location->id,
            'incident_date' => now()->toDateString(),
            'description' => 'Deskripsi laporan flow QA.',
            'urgency' => 'sedang',
            'status' => 'menunggu_verifikasi',
            'assigned_to_role' => 'kesiswaan',
            'consent_accepted_at' => now(),
        ], $overrides));
    }

    private function validMasterPayloads(SchoolClass $class): array
    {
        return [
            'classes' => ['class_name', [
                'class_name' => 'XI QA 1',
                'grade_level' => 'XI',
                'major' => 'QA',
                'academic_year' => '2026/2027',
                'room_name' => 'QA-1',
                'is_active' => '1',
            ]],
            'subjects' => ['subject_name', [
                'subject_name' => 'Quality Assurance',
                'is_active' => '1',
            ]],
            'staff-units' => ['unit_name', [
                'unit_name' => 'Unit QA',
                'is_active' => '1',
            ]],
            'locations' => ['location_name', [
                'location_name' => 'Ruang QA',
                'location_type' => 'ruang',
                'class_id' => $class->id,
                'is_active' => '1',
            ]],
            'violation-types' => ['violation_name', [
                'violation_name' => 'Pelanggaran QA',
                'point_reduction' => 7,
                'description' => 'Jenis pelanggaran untuk flow QA.',
                'is_active' => '1',
            ]],
            'damage-categories' => ['category_name', [
                'category_name' => 'Kategori QA',
                'is_active' => '1',
            ]],
        ];
    }
}
