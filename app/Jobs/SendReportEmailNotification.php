<?php

namespace App\Jobs;

use App\Models\Report;
use App\Models\ReportStatusHistory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendReportEmailNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [30, 120, 300];
    public int $timeout = 120;

    public function __construct(
        public int $reportId,
        public string $event,
        public ?string $accessCode = null,
    ) {}

    public function handle(): void
    {
        $report = Report::find($this->reportId);
        if (! $report || ! $report->reporter_email) return;

        $isCreated = $this->event === 'created';
        $statusLabel = $this->statusLabel($report->status);
        $publicNote = null;

        if (! $isCreated) {
            $publicNote = ReportStatusHistory::query()
                ->where('report_id', $report->id)
                ->where('new_status', $report->status)
                ->whereNotNull('public_note')
                ->latest('id')
                ->value('public_note');
        }

        $html = $isCreated ? 'emails.laporan-diterima' : 'emails.status-perubahan';
        $text = $isCreated ? 'emails.laporan-diterima-text' : 'emails.status-perubahan-text';
        $subject = $isCreated
            ? "LAPORIN — Laporan {$report->report_number} berhasil diterima"
            : "LAPORIN — Laporan {$report->report_number}: status {$statusLabel}";

        Mail::send(['html' => $html, 'text' => $text], [
            'report' => $report,
            'event' => $this->event,
            'accessCode' => $this->accessCode,
            'statusLabel' => $statusLabel,
            'reportNumber' => $report->report_number,
            'reportTypeLabel' => $this->reportTypeLabel($report),
            'catatan' => $publicNote,
        ], function ($message) use ($report, $subject): void {
            $from = config('mail.from.address', 'notifikasi@report.assetloan.my.id');
            $name = config('mail.from.name', 'LAPORIN');
            $message->to($report->reporter_email)
                ->replyTo($from, $name)
                ->from($from, $name)
                ->subject($subject)
                ->getHeaders()
                ->addTextHeader('Auto-Submitted', 'auto-generated')
                ->addTextHeader('X-Auto-Response-Suppress', 'All');
        });
    }

    private function reportTypeLabel(Report $report): string
    {
        return $report->report_type === 'violation' ? 'Perundungan / Pelanggaran' : 'Kerusakan Fasilitas';
    }

    private function statusLabel(string $status): string
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
