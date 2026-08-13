<?php

namespace App\Traits;

use App\Models\Report;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

trait ReportNotificationTrait
{
    protected function getReportTypeLabel(Report $report): string
    {
        return $report->report_type === 'violation'
            ? 'Perundungan / Pelanggaran'
            : 'Kerusakan Fasilitas';
    }

    protected function statusLabel(string $status): string
    {
        return match ($status) {
            'menunggu_verifikasi' => 'Menunggu Verifikasi',
            'memerlukan_informasi' => 'Perlu Informasi Tambahan',
            'dibuka_kembali' => 'Dibuka Kembali',
            'sedang_ditangani' => 'Sedang Ditangani',
            'menunggu_konfirmasi' => 'Menunggu Konfirmasi Pelapor',
            'selesai' => 'Selesai',
            'ditolak' => 'Ditolak',
            default => str_replace('_', ' ', $status),
        };
    }

    protected function kirimNotifikasiEmail(Report $report, string $accessCode): bool
    {
        $email = $report->reporter_email;
        if (! $email) {
            return false;
        }

        try {
            Mail::send(
                'emails.laporan-diterima',
                [
                    'report' => $report,
                    'accessCode' => $accessCode,
                    'reportNumber' => $report->report_number,
                    'reportTypeLabel' => $this->getReportTypeLabel($report),
                    'statusLabel' => $this->statusLabel('menunggu_verifikasi'),
                ],
                function ($message) use ($email, $report) {
                    $message->to($email)
                        ->subject("[LAPORIN] Laporan {$report->report_number} berhasil diterima")
                        ->from(config('mail.from.address', 'noreply@laporin.sch.id'), config('mail.from.name', 'LAPORIN'));
                }
            );

            return true;
        } catch (\Throwable $e) {
            Log::warning(
                'Gagal kirim email notifikasi laporan.',
                [
                    'report_id' => $report->id,
                    'exception' => $e::class,
                ]
            );

            return false;
        }
    }

    protected function kirimNotifikasiStatus(Report $report, string $statusLabel, ?string $catatan = null): bool
    {
        $email = $report->reporter_email;
        if (! $email) {
            return false;
        }

        try {
            Mail::send(
                'emails.status-perubahan',
                [
                    'report' => $report,
                    'statusLabel' => $statusLabel,
                    'catatan' => $catatan,
                    'reportNumber' => $report->report_number,
                    'reportTypeLabel' => $this->getReportTypeLabel($report),
                ],
                function ($message) use ($email, $report, $statusLabel) {
                    $message->to($email)
                        ->subject("[LAPORIN] Laporan {$report->report_number}: status berubah menjadi {$statusLabel}")
                        ->from(config('mail.from.address', 'noreply@laporin.sch.id'), config('mail.from.name', 'LAPORIN'));
                }
            );

            return true;
        } catch (\Throwable $e) {
            Log::warning(
                'Gagal kirim email notifikasi status.',
                [
                    'report_id' => $report->id,
                    'exception' => $e::class,
                ]
            );

            return false;
        }
    }
}
