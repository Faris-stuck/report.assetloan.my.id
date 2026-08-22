<?php

namespace App\Services\Role\Kesiswaan;

use App\Models\Report;
use App\Models\ReportStatusHistory;
use App\Models\Student;
use App\Models\StudentViolation;
use App\Models\ViolationType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class KesiswaanProcessor
{
    private const PROCESSABLE_STATUSES = ['menunggu_verifikasi', 'memerlukan_informasi', 'dibuka_kembali'];

    /**
     * @return bool true bila poin siswa benar-benar dipotong pada pemanggilan ini.
     *              Laporan yang dibuka kembali hanya berubah status, jadi pemanggil
     *              butuh nilai ini agar pesan sukses tidak mengaku memotong poin
     *              yang sebenarnya tidak berubah.
     */
    public function process(Request $request, Report $report): bool
    {
        if ($report->report_type !== 'violation') {
            throw ValidationException::withMessages([
                'report' => 'Menu Kesiswaan hanya dapat memproses laporan pelanggaran siswa.',
            ]);
        }

        if (! in_array(
            $report->status,
            self::PROCESSABLE_STATUSES,
            true
        )) {
            throw ValidationException::withMessages([
                'report' => 'Laporan ini sudah pernah diproses atau tidak bisa diproses ulang.',
            ]);
        }

        if (
            StudentViolation::where(
                'report_id',
                $report->id
            )->exists()
            && ! in_array(
                $report->status,
                [
                    'dibuka_kembali',
                    'memerlukan_informasi',
                ],
                true
            )
        ) {
            throw ValidationException::withMessages([
                'report' => 'Poin untuk laporan ini sudah pernah diproses.',
            ]);
        }

        $data = $request->validate([
            'student_id' => [
                'required',
                'exists:students,id',
            ],
            // Dropdown di form hanya memuat jenis pelanggaran aktif, tetapi tanpa
            // syarat is_active di sini jenis yang baru dinonaktifkan Superadmin
            // masih lolos validasi dan baru gagal di dalam transaksi. Divalidasi
            // di depan supaya pemroses menerima pesan pada field, bukan halaman
            // error tanpa penjelasan.
            'violation_type_id' => [
                'required',
                Rule::exists('violation_types', 'id')
                    ->where('is_active', true),
            ],
            'note' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ], [
            'student_id.required' => 'Pilih siswa yang terbukti melakukan pelanggaran.',
            'student_id.exists' => 'Siswa yang dipilih tidak ada di data master. Muat ulang halaman lalu pilih siswa lain.',
            'violation_type_id.required' => 'Pilih jenis pelanggaran yang dikenakan.',
            'violation_type_id.exists' => 'Jenis pelanggaran itu sudah tidak aktif. Muat ulang halaman lalu pilih jenis yang masih aktif.',
        ]);

        $pointsDeducted = DB::transaction(
            function () use (
                $request,
                $report,
                $data
            ): bool {

                $lockedReport = Report::whereKey(
                    $report->id
                )
                    ->lockForUpdate()
                    ->firstOrFail();

                if (
                    $lockedReport->report_type
                    !== 'violation'
                ) {
                    throw ValidationException::withMessages([
                        'report' => 'Menu Kesiswaan hanya dapat memproses laporan pelanggaran siswa.',
                    ]);
                }

                if (! in_array(
                    $lockedReport->status,
                    self::PROCESSABLE_STATUSES,
                    true
                )) {
                    throw ValidationException::withMessages([
                        'report' => 'Laporan ini sudah pernah diproses atau tidak bisa diproses ulang.',
                    ]);
                }

                // Diambil sebagai model (bukan exists()) karena baris yang sudah ada
                // menentukan siswa dan jenis pelanggaran mana yang poinnya sudah
                // dipotong, dan itu harus tetap konsisten dengan kolom di laporan.
                $recordedViolation =
                    StudentViolation::where(
                        'report_id',
                        $lockedReport->id
                    )->first();

                // findOrFail() di sini melempar ModelNotFoundException yang tidak
                // ditangkap KesiswaanService, jadi baris master yang hilang di
                // tengah proses hanya tampil sebagai halaman 404 tanpa petunjuk.
                // Diperiksa manual supaya operator dapat pesan pada field.
                $student = Student::lockForUpdate()
                    ->find(
                        $data['student_id']
                    );

                if ($student === null) {
                    throw ValidationException::withMessages([
                        'student_id' => 'Siswa yang dipilih baru saja dihapus dari data master. Muat ulang halaman lalu pilih siswa lain.',
                    ]);
                }

                $type = ViolationType::where(
                    'is_active',
                    true
                )->find(
                    $data['violation_type_id']
                );

                if ($type === null) {
                    throw ValidationException::withMessages([
                        'violation_type_id' => 'Jenis pelanggaran itu baru saja dinonaktifkan. Muat ulang halaman lalu pilih jenis yang masih aktif.',
                    ]);
                }

                if ($recordedViolation !== null) {
                    $this->ensureMatchesRecordedViolation(
                        $recordedViolation,
                        $student,
                        $type
                    );
                }

                if ($recordedViolation === null) {

                    $student->update([
                        'point' => max(
                            0,
                            (int) $student->point
                            - (int) $type->point_reduction
                        ),
                    ]);

                    StudentViolation::create([
                        'student_id' => $student->id,
                        'report_id' =>
                            $lockedReport->id,
                        'violation_type_id' =>
                            $type->id,
                        'point_reduced' =>
                            $type->point_reduction,
                        'note' =>
                            $data['note'] ?? null,
                        'processed_by_user_id' =>
                            $request->user()->id,
                    ]);
                }

                $old = $lockedReport->status;

                $lockedReport->update([
                    'status' => 'sedang_ditangani',
                    'violation_type_id' =>
                        $type->id,
                    'verified_by' =>
                        $request->user()->id,
                    'verified_at' => now(),
                    'assigned_to_role' =>
                        'kesiswaan',
                ]);

                $publicNote = $recordedViolation !== null
                    ? 'Laporan pelanggaran ditindaklanjuti kembali tanpa memotong poin dua kali.'
                    : 'Laporan pelanggaran diverifikasi dan sedang ditangani.';

                ReportStatusHistory::create([
                    'report_id' =>
                        $lockedReport->id,
                    'changed_by_user_id' =>
                        $request->user()->id,
                    'actor_type' =>
                        'kesiswaan',
                    'previous_status' => $old,
                    'new_status' =>
                        'sedang_ditangani',
                    'public_note' =>
                        $publicNote,
                    'internal_note' =>
                        $data['note'] ?? null,
                ]);

                return $recordedViolation === null;
            }
        );

        /*
         * Notifikasi dikirim ReportObserver::updated() lewat
         * SendReportNotifications::dispatch(...)->afterCommit() begitu kolom
         * status berubah, jadi processor tidak mengirim apa pun sendiri.
         */

        return $pointsDeducted;
    }

    /**
     * Laporan yang dibuka kembali tidak memotong poin untuk kedua kali, jadi baris
     * student_violations yang sudah ada tetap dipakai. Kalau pemroses mengirim
     * siswa atau jenis pelanggaran yang berbeda, kolom violation_type_id di
     * laporan akan bercerita lain daripada poin yang benar-benar dipotong, dan
     * siswa yang salah bisa terlihat sebagai pelakunya. Lebih aman menolak sambil
     * menyebut kombinasi yang sudah tercatat daripada menyimpan dua versi.
     */
    private function ensureMatchesRecordedViolation(
        StudentViolation $recorded,
        Student $student,
        ViolationType $type
    ): void {
        $messages = [];

        if ((int) $recorded->student_id !== (int) $student->id) {
            $recordedStudentName = Student::whereKey($recorded->student_id)
                ->value('name');

            $messages['student_id'] = 'Poin laporan ini sudah dipotong untuk '
                .($recordedStudentName ?? 'siswa lain')
                .'. Pilih siswa yang sama, atau minta Superadmin memperbaiki data pelanggaran lebih dahulu.';
        }

        if ((int) $recorded->violation_type_id !== (int) $type->id) {
            $recordedTypeName = ViolationType::whereKey($recorded->violation_type_id)
                ->value('violation_name');

            $messages['violation_type_id'] = 'Poin laporan ini sudah dipotong memakai jenis "'
                .($recordedTypeName ?? 'lain')
                .'". Pilih jenis yang sama agar catatan poin dan laporan tidak berbeda.';
        }

        if ($messages !== []) {
            throw ValidationException::withMessages($messages);
        }
    }

    public function reject(Request $request, Report $report): void
    {
        $data = $request->validate([
            'reason' => [
                'required',
                'string',
                'max:2000',
            ],
        ]);

        DB::transaction(
            function () use (
                $request,
                $report,
                $data
            ): void {
                $lockedReport = Report::whereKey(
                    $report->id
                )
                    ->lockForUpdate()
                    ->firstOrFail();

                if (
                    $lockedReport->report_type
                    !== 'violation'
                ) {
                    throw ValidationException::withMessages([
                        'report' => 'Menu Kesiswaan hanya dapat menolak laporan pelanggaran siswa.',
                    ]);
                }

                if (! in_array(
                    $lockedReport->status,
                    self::PROCESSABLE_STATUSES,
                    true
                )) {
                    throw ValidationException::withMessages([
                        'report' => 'Laporan ini tidak bisa ditolak pada status saat ini.',
                    ]);
                }

                $old = $lockedReport->status;

                $lockedReport->update([
                    'status' => 'ditolak',
                    'rejection_reason' =>
                        $data['reason'],
                ]);

                $publicNote =
                    'Laporan ditolak.';

                ReportStatusHistory::create([
                    'report_id' =>
                        $lockedReport->id,
                    'changed_by_user_id' =>
                        $request->user()->id,
                    'actor_type' =>
                        'kesiswaan',
                    'previous_status' => $old,
                    'new_status' => 'ditolak',
                    'public_note' => $publicNote,
                    'internal_note' =>
                        $data['reason'],
                ]);

            }
        );
    }

    public function complete(Request $request, Report $report): void
    {
        $data = $request->validate([
            'note' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        DB::transaction(
            function () use (
                $request,
                $report,
                $data
            ): void {
                $lockedReport = Report::whereKey(
                    $report->id
                )
                    ->lockForUpdate()
                    ->firstOrFail();

                if (
                    $lockedReport->report_type
                    !== 'violation'
                ) {
                    throw ValidationException::withMessages([
                        'report' => 'Kesiswaan hanya dapat menyelesaikan penanganan laporan perundungan atau pelanggaran.',
                    ]);
                }

                if (
                    $lockedReport->status
                    !== 'sedang_ditangani'
                ) {
                    throw ValidationException::withMessages([
                        'report' => 'Laporan harus berstatus sedang ditangani sebelum dikirim ke konfirmasi pelapor.',
                    ]);
                }

                $lockedReport->update([
                    'status' =>
                        'menunggu_konfirmasi',
                ]);

                $publicNote =
                    'Kesiswaan menyelesaikan tindak lanjut dan meminta konfirmasi pelapor.';

                ReportStatusHistory::create([
                    'report_id' =>
                        $lockedReport->id,
                    'changed_by_user_id' =>
                        $request->user()->id,
                    'actor_type' =>
                        'kesiswaan',
                    'previous_status' =>
                        'sedang_ditangani',
                    'new_status' =>
                        'menunggu_konfirmasi',
                    'public_note' =>
                        $publicNote,
                    'internal_note' =>
                        $data['note'] ?? null,
                ]);

            }
        );
    }

}
