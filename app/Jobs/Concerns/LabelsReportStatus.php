<?php

namespace App\Jobs\Concerns;

/**
 * Satu sumber label status laporan untuk semua job notifikasi.
 * Sebelumnya mapping ini diduplikasi di job email dan WhatsApp.
 */
trait LabelsReportStatus
{
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
            default => ucwords(str_replace('_', ' ', $status)),
        };
    }
}
