<?php

namespace App\Services\Role\Kesiswaan;

use App\Models\Report;
use App\Models\ReportStatusHistory;
use App\Models\Student;
use App\Models\StudentViolation;
use App\Models\ViolationType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class KesiswaanProcessor
{
    private const PROCESSABLE_STATUSES = ['menunggu_verifikasi', 'memerlukan_informasi', 'dibuka_kembali'];

    public function process(Request $request, Report $report): void
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
            'violation_type_id' => [
                'required',
                'exists:violation_types,id',
            ],
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

                $existingViolation =
                    StudentViolation::where(
                        'report_id',
                        $lockedReport->id
                    )->exists();

                $student = Student::lockForUpdate()
                    ->findOrFail(
                        $data['student_id']
                    );

                $type = ViolationType::where(
                    'is_active',
                    true
                )->findOrFail(
                    $data['violation_type_id']
                );

                if (! $existingViolation) {

                    $student->update([
                        'point' => max(
                            0,
                            $student->point
                            - $type->point_reduction
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

                $publicNote = $existingViolation
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

            }
        );

        /*
         * Notifikasi dikirim ReportObserver::updated() lewat
         * SendReportNotifications::dispatch(...)->afterCommit() begitu kolom
         * status berubah, jadi processor tidak mengirim apa pun sendiri.
         */
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
