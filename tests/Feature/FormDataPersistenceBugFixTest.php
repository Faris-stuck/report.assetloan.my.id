<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SchoolClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Wizard laporan publik kini sepenuhnya dijalankan server: setiap langkah punya
 * route sendiri (/lapor/langkah/{step}) dan draft disimpan di session, bukan di
 * sessionStorage browser.
 *
 * Versi sebelumnya berkas ini memeriksa markup Alpine (x-model="formData.step1.*",
 * saveFormState, sessionStorage.getItem('reportFormData')). Semua penanda itu
 * sudah tidak ada di view, sehingga tesnya lulus/gagal karena keberadaan string
 * HTML — bukan karena data pelapor benar-benar bertahan. Sekarang yang diuji
 * adalah perilakunya lewat request HTTP nyata.
 */
class FormDataPersistenceBugFixTest extends TestCase
{
    use RefreshDatabase;

    private function activeClass(): SchoolClass
    {
        return SchoolClass::create([
            'class_name' => 'XII RPL 1',
            'grade_level' => '12',
            'major' => 'RPL',
            'academic_year' => '2026/2027',
            'is_active' => true,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validStepOne(SchoolClass $class, string $name = 'Pelapor Satu'): array
    {
        return [
            'reporter_name' => $name,
            'reporter_class_id' => $class->id,
            'reporter_absence_number' => 12,
            'reporter_phone' => '081234567890',
            'reporter_email' => 'pelapor@example.test',
        ];
    }

    private function submitToken(): string
    {
        return (string) session('report_submit_token');
    }

    public function test_langkah_pertama_terbuka_dan_membuat_sesi_formulir(): void
    {
        $response = $this->get(route('public.report'));

        $response->assertStatus(200);
        $response->assertViewHas('subjects');
        $response->assertViewHas('staffUnits');
        $response->assertSee('data-step="1"', false);

        $this->assertNotSame('', $this->submitToken(), 'Membuka /lapor harus menerbitkan report_submit_token.');
    }

    public function test_langkah_di_luar_rentang_dikembalikan_ke_awal(): void
    {
        $this->get(route('public.report'));

        foreach ([0, 5, 99] as $step) {
            $this->get(route('public.report.step', $step))
                ->assertRedirect(route('public.report'));
        }
    }

    public function test_langkah_lanjutan_tanpa_sesi_formulir_ditolak_dengan_pesan(): void
    {
        $response = $this->get(route('public.report.step', 2));

        $response->assertRedirect(route('public.report'));
        $response->assertSessionHasErrors('form');
    }

    public function test_data_langkah_satu_bertahan_saat_kembali_dari_langkah_dua(): void
    {
        $class = $this->activeClass();
        $this->get(route('public.report'));

        $this->post(route('public.report.step.store', 1), $this->validStepOne($class) + [
            'report_submit_token' => $this->submitToken(),
        ])->assertRedirect(route('public.report.step', 2));

        // Kembali ke langkah 1: draft harus dimuat ulang ke old input sehingga
        // pelapor tidak perlu mengetik ulang apa pun.
        $this->get(route('public.report.step', 1))->assertStatus(200);

        $this->assertSame('Pelapor Satu', old('reporter_name'));
        $this->assertSame($class->id, (int) old('reporter_class_id'));
        $this->assertSame('081234567890', old('reporter_phone'));
    }

    public function test_reporter_type_dipaksa_siswa_di_sisi_server(): void
    {
        $class = $this->activeClass();
        $this->get(route('public.report'));

        // Formulir publik hanya untuk siswa. Nilai kiriman apa pun harus diabaikan.
        $this->post(route('public.report.step.store', 1), $this->validStepOne($class) + [
            'report_submit_token' => $this->submitToken(),
            'reporter_type' => 'staff',
        ])->assertRedirect(route('public.report.step', 2));

        $draft = session('report_submit_forms')[$this->submitToken()]['wizard_data'] ?? [];
        $this->assertSame('siswa', $draft['reporter_type'] ?? null);
    }

    public function test_langkah_gagal_validasi_mengembalikan_input_yang_baru_diketik(): void
    {
        $class = $this->activeClass();
        $this->get(route('public.report'));

        $response = $this->post(route('public.report.step.store', 1), [
            'report_submit_token' => $this->submitToken(),
            'reporter_name' => 'Nama Terketik',
            'reporter_class_id' => $class->id,
            'reporter_phone' => 'bukan-nomor',
        ]);

        $response->assertRedirect(route('public.report.step', 1));
        $response->assertSessionHasErrors('reporter_phone');
        $this->assertSame('Nama Terketik', old('reporter_name'));
    }

    /**
     * Regresi yang diperbaiki: wizardStep() memanggil session()->flashInput($wizardData).
     * Ketika submit gagal, withInput() sudah lebih dulu mem-flash apa yang baru
     * diketik pelapor; menimpanya dengan draft lama berarti suntingan pelapor
     * hilang tanpa jejak. Input terbaru harus menang per field, sementara field
     * yang tidak dikirim ulang tetap diambil dari draft.
     */
    public function test_suntingan_terbaru_menang_atas_draft_lama(): void
    {
        $class = $this->activeClass();
        $this->get(route('public.report'));
        $token = $this->submitToken();

        // Draft tersimpan dengan nama lama.
        $this->post(route('public.report.step.store', 1), $this->validStepOne($class, 'Nama Lama') + [
            'report_submit_token' => $token,
        ])->assertRedirect(route('public.report.step', 2));

        $this->assertSame('Nama Lama', session("report_submit_forms.$token.wizard_data.reporter_name"));

        // Pelapor kembali ke langkah 1, mengubah nama, tapi merusak nomor HP.
        $this->post(route('public.report.step.store', 1), [
            'report_submit_token' => $token,
            'reporter_name' => 'Nama Baru',
            'reporter_class_id' => $class->id,
            'reporter_phone' => '1',
        ])->assertRedirect(route('public.report.step', 1));

        $this->get(route('public.report.step', 1))->assertStatus(200);

        $this->assertSame('Nama Baru', old('reporter_name'), 'Nama yang baru diketik tidak boleh ditimpa draft lama.');
        // Field yang tidak dikirim ulang tetap harus datang dari draft.
        $this->assertSame('pelapor@example.test', old('reporter_email'));
        // Draft tersimpan belum boleh berubah karena langkahnya gagal validasi.
        $this->assertSame('Nama Lama', session("report_submit_forms.$token.wizard_data.reporter_name"));
    }

    public function test_langkah_dua_menyimpan_jenis_laporan_lalu_maju_ke_langkah_tiga(): void
    {
        $class = $this->activeClass();
        $this->get(route('public.report'));
        $token = $this->submitToken();

        $this->post(route('public.report.step.store', 1), $this->validStepOne($class) + [
            'report_submit_token' => $token,
        ]);

        $this->post(route('public.report.step.store', 2), [
            'report_submit_token' => $token,
            'report_type' => 'violation',
        ])->assertRedirect(route('public.report.step', 3));

        $this->assertSame('violation', session("report_submit_forms.$token.wizard_data.report_type"));
    }

    /**
     * @return array<string, mixed>
     */
    private function reachStepThree(SchoolClass $class): array
    {
        $this->get(route('public.report'));
        $token = $this->submitToken();

        $this->post(route('public.report.step.store', 1), $this->validStepOne($class) + [
            'report_submit_token' => $token,
        ]);
        $this->post(route('public.report.step.store', 2), [
            'report_submit_token' => $token,
            'report_type' => 'violation',
        ]);

        return ['token' => $token];
    }

    /**
     * Formulir publik TIDAK menanyakan lokasi. Fitur lokasi dihapus menyeluruh
     * atas permintaan pemilik sistem, jadi langkah 3 tidak boleh lagi memuat
     * kontrol lokasi dalam bentuk apa pun. Tempat kejadian ditulis pelapor di
     * dalam kronologi.
     */
    public function test_langkah_tiga_tidak_menanyakan_lokasi(): void
    {
        $class = $this->activeClass();
        $this->reachStepThree($class);

        $response = $this->get(route('public.report.step', 3));

        $response->assertStatus(200);
        $response->assertDontSee('name="location_id"', false);
        $response->assertDontSee('name="custom_location"', false);
        $response->assertDontSee('Lokasi kejadian', false);
    }

    /**
     * Titik langkah dulunya <button> padahal tidak menavigasi ke mana pun —
     * implementasi Alpine yang menanganinya sudah dihapus, jadi menekannya tidak
     * melakukan apa-apa. Sekarang harus berupa indikator, bukan kontrol.
     */
    public function test_titik_langkah_adalah_indikator_bukan_tombol(): void
    {
        $response = $this->get(route('public.report'));

        $response->assertStatus(200);
        $response->assertSee('aria-current="step"', false);
        $response->assertDontSee('<button type="button" class="step-dot', false);
    }

    public function test_tombol_kirim_punya_target_sentuh_yang_memadai(): void
    {
        $class = $this->activeClass();
        $this->get(route('public.report'));
        $token = $this->submitToken();

        $this->post(route('public.report.step.store', 1), $this->validStepOne($class) + [
            'report_submit_token' => $token,
        ]);
        $this->post(route('public.report.step.store', 2), [
            'report_submit_token' => $token,
            'report_type' => 'violation',
        ]);

        // Langkah 4 memuat tombol kirim; langkah 1-3 tidak.
        $response = $this->get(route('public.report.step', 4));

        $response->assertStatus(200);
        $response->assertSee('Kirim Laporan', false);
        $response->assertSee('min-height:44px', false);
    }
}
