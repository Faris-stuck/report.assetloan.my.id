<?php

namespace App\Services\Role\Sarpras;

use App\Models\Report;
use App\Models\ReportAttachment;
use App\Models\ReportStatusHistory;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class SarprasProcessor
{
    private const PROCESSABLE_STATUSES = ['menunggu_verifikasi', 'memerlukan_informasi', 'dibuka_kembali', 'sedang_ditangani'];

    public function process(Request $request, Report $report): void
    {
        if ($report->report_type !== 'damage') {
            throw ValidationException::withMessages([
                'report' => 'Menu Sarpras hanya dapat memproses laporan kerusakan fasilitas.',
            ]);
        }

        if (! in_array(
            $report->status,
            self::PROCESSABLE_STATUSES,
            true
        )) {
            throw ValidationException::withMessages([
                'report' => 'Laporan ini sudah selesai/ditolak dan tidak bisa diproses ulang.',
            ]);
        }

        $data = $request->validate([
            'priority' => [
                'required',
                'in:rendah,sedang,tinggi,darurat',
            ],
            'scheduled_repair_at' => $this->scheduleRules($request, $report),
            'note' => [
                'nullable',
                'string',
                'max:2000',
            ],
            'repair_photo' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'mimetypes:image/jpeg,image/png,image/webp',
                'max:4096',
            ],
        ]);

        $storedPath = null;

        try {

            DB::transaction(
                function () use (
                    $request,
                    $report,
                    $data,
                    &$storedPath
                ): void {
                    $lockedReport =
                        Report::whereKey(
                            $report->id
                        )
                            ->lockForUpdate()
                            ->firstOrFail();

                    if (
                        $lockedReport->report_type
                        !== 'damage'
                    ) {
                        throw ValidationException::withMessages([
                            'report' => 'Menu Sarpras hanya dapat memproses laporan kerusakan fasilitas.',
                        ]);
                    }

                    if (! in_array(
                        $lockedReport->status,
                        self::PROCESSABLE_STATUSES,
                        true
                    )) {
                        throw ValidationException::withMessages([
                            'report' => 'Laporan ini sudah selesai/ditolak dan tidak bisa diproses ulang.',
                        ]);
                    }

                    $old =
                        $lockedReport->status;

                    $detail =
                        $lockedReport->damageDetail;

                    if (! $detail) {
                        throw ValidationException::withMessages([
                            'report' => 'Detail kerusakan tidak lengkap. Minta pelapor melengkapi laporan terlebih dahulu.',
                        ]);
                    }

                    $done = $request->hasFile(
                        'repair_photo'
                    );

                    /*
                     * Field yang tidak dikirim tidak boleh menghapus data yang
                     * sudah ada. Sebelumnya `?? null` menulis null setiap kali
                     * form disubmit, sehingga memproses ulang laporan yang
                     * sedang ditangani diam-diam menghapus jadwal
                     * perbaikannya. Pengosongan tetap bisa dilakukan secara
                     * sadar: input dikirim kosong -> aturan `nullable`
                     * mengubahnya menjadi null.
                     */
                    $detail->update([
                        'priority' =>
                            $data['priority'],
                        'scheduled_repair_at' =>
                            $request->has('scheduled_repair_at')
                                ? ($data['scheduled_repair_at'] ?? null)
                                : $detail->scheduled_repair_at,
                        // Tanggal selesai hanya diisi saat foto perbaikan
                        // diunggah, dan tidak pernah dikosongkan ulang oleh
                        // submit berikutnya.
                        'repaired_at' =>
                            $done ? now() : $detail->repaired_at,
                    ]);

                    if ($done) {

                        $file = $request->file(
                            'repair_photo'
                        );

                        $storedPath = $file->store(
                            'report-attachments/'
                            .$lockedReport->id,
                            'private'
                        );

                        ReportAttachment::create([
                            'report_id' =>
                                $lockedReport->id,
                            'uploaded_by_user_id' =>
                                $request->user()->id,
                            'uploader_type' =>
                                'sarpras',
                            'original_name' =>
                                $this->safeOriginalName(
                                    $file->getClientOriginalName()
                                ),
                            'stored_name' =>
                                basename($storedPath),
                            'file_path' =>
                                $storedPath,
                            'mime_type' =>
                                $file->getMimeType(),
                            'file_size' =>
                                $file->getSize(),
                            'attachment_type' =>
                                'repair_after',
                        ]);
                    }

                    $new = $done
                        ? 'selesai'
                        : 'sedang_ditangani';

                    $lockedReport->update([
                        'status' => $new,
                        'verified_by' =>
                            $request->user()->id,
                        'verified_at' => now(),
                        'assigned_to_role' =>
                            'sarpras',
                    ]);

                    $publicNote = $done
                        ? 'Perbaikan selesai.'
                        : 'Perbaikan dijadwalkan.';

                    ReportStatusHistory::create([
                        'report_id' =>
                            $lockedReport->id,
                        'changed_by_user_id' =>
                            $request->user()->id,
                        'actor_type' =>
                            'sarpras',
                        'previous_status' =>
                            $old,
                        'new_status' =>
                            $new,
                        'public_note' =>
                            $publicNote,
                        'internal_note' =>
                            $data['note'] ?? null,
                    ]);

                }
            );

        } catch (Throwable $exception) {

            if (
                is_string($storedPath)
                && $storedPath !== ''
            ) {
                try {
                    Storage::disk('private')
                        ->delete($storedPath);
                } catch (Throwable) {
                    // Jangan menutupi exception asli.
                }
            }

            throw $exception;
        }

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
                $lockedReport =
                    Report::whereKey(
                        $report->id
                    )
                        ->lockForUpdate()
                        ->firstOrFail();

                if (
                    $lockedReport->report_type
                    !== 'damage'
                ) {
                    throw ValidationException::withMessages([
                        'report' => 'Sarpras hanya dapat menolak laporan kerusakan fasilitas.',
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

                $old =
                    $lockedReport->status;

                $lockedReport->update([
                    'status' => 'ditolak',
                    'rejection_reason' =>
                        $data['reason'],
                ]);

                $publicNote =
                    'Laporan kerusakan ditolak oleh Sarpras.';

                ReportStatusHistory::create([
                    'report_id' =>
                        $lockedReport->id,
                    'changed_by_user_id' =>
                        $request->user()->id,
                    'actor_type' =>
                        'sarpras',
                    'previous_status' =>
                        $old,
                    'new_status' =>
                        'ditolak',
                    'public_note' =>
                        $publicNote,
                    'internal_note' =>
                        $data['reason'],
                ]);

            }
        );
    }

    /**
     * Aturan validasi kolom jadwal perbaikan.
     *
     * `after_or_equal:now` hanya dipasang untuk jadwal yang benar-benar baru.
     * Sebelumnya aturan itu selalu aktif, padahal view mengisi ulang input
     * dengan jadwal yang sudah tersimpan — begitu jadwal itu terlewat waktu,
     * tombol "Simpan Perbaikan" selalu menolak submit petugas dan laporan
     * tidak bisa diproses maupun diselesaikan lagi.
     *
     * @return list<string>
     */
    private function scheduleRules(Request $request, Report $report): array
    {
        $rules = ['nullable', 'date'];

        if (! $this->scheduleIsUnchanged(
            $request->input('scheduled_repair_at'),
            $report->damageDetail?->scheduled_repair_at
        )) {
            $rules[] = 'after_or_equal:now';
        }

        return $rules;
    }

    private function scheduleIsUnchanged(mixed $submitted, ?CarbonInterface $stored): bool
    {
        if ($stored === null || ! is_string($submitted) || trim($submitted) === '') {
            return false;
        }

        try {
            // Input `datetime-local` tidak mengirim detik, jadi perbandingan
            // dilakukan pada presisi menit.
            return Carbon::parse($submitted)->format('Y-m-d H:i') === $stored->format('Y-m-d H:i');
        } catch (Throwable) {
            return false;
        }
    }

    private function safeOriginalName(string $name): string
    {
        $name = preg_replace('/[^A-Za-z0-9._ -]/', '_', $name) ?: 'attachment';

        return Str::limit($name, 120, '');
    }
}
