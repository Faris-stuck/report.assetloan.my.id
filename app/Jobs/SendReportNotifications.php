<?php

namespace App\Jobs;

use App\Models\Report;
use App\Services\WahaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendReportNotifications implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public bool $afterCommit = true;

    public function __construct(
        public int $reportId,
        public string $event,
        public ?string $accessCode = null,
    ) {
    }

    public function handle(WahaService $waha): void
    {
        $report = Report::find($this->reportId);

        if (! $report) {
            return;
        }

        $this->sendEmail($report);
        $this->sendWhatsApp($report, $waha);
    }

    private function sendEmail(Report $report): void
    {
        if (! $report->reporter_email) {
            return;
        }

        try {
            Mail::send(
                'emails.laporan-status',
                [
                    'report' => $report,
                    'event' => $this->event,
                    'accessCode' => $this->accessCode,
                    'statusLabel' => $this->statusLabel($report->status),
                ],
                function ($message) use ($report) {
                    $message->to($report->reporter_email)
                        ->subject("[LAPORIN] {$report->report_number} - {$this->statusLabel($report->status)}")
                        ->from(
                            config('mail.from.address', 'noreply@laporin.sch.id'),
                            config('mail.from.name', 'LAPORIN')
                        );
                }
            );
        } catch (\Throwable $e) {
            Log::warning('Report email notification failed', [
                'report_id' => $report->id,
                'event' => $this->event,
                'exception' => $e::class,
            ]);
        }
    }

    private function sendWhatsApp(Report $report, WahaService $waha): void
    {
        $phone = $this->normalizePhone($report->reporter_phone);

        if ($phone === null) {
            return;
        }

        $lines = [
            '*LAPORIN - SMK Taruna Bangsa Bekasi*',
            '',
            "Laporan: *{$report->report_number}*",
            "Status: *{$this->statusLabel($report->status)}*",
        ];

        if ($this->event === 'created' && $this->accessCode) {
            $lines[] = "Kode akses: *{$this->accessCode}*";
        }

        $lines[] = '';
        $lines[] = 'Pantau laporan: '.url('/lacak');

        try {
            $waha->sendText($phone.'@c.us', implode("\n", $lines));
        } catch (\Throwable $e) {
            Log::warning('Report WhatsApp notification failed', [
                'report_id' => $report->id,
                'event' => $this->event,
                'exception' => $e::class,
            ]);
        }
    }

    private function normalizePhone(?string $phone): ?string
    {
        if (! is_string($phone) || trim($phone) === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        }

        if (! str_starts_with($digits, '62') || strlen($digits) < 10 || strlen($digits) > 15) {
            return null;
        }

        return $digits;
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'menunggu_verifikasi' => 'Menunggu Verifikasi',
            'diproses' => 'Sedang Diproses',
            'ditolak' => 'Ditolak',
            'selesai' => 'Selesai',
            default => ucwords(str_replace('_', ' ', $status)),
        };
    }
}
