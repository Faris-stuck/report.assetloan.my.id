<?php

namespace App\Jobs;

use App\Jobs\Concerns\LabelsReportStatus;
use App\Models\Report;
use App\Services\WahaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;
use Throwable;

// ShouldBeEncrypted: payload job membawa kode akses laporan, jadi seluruh payload
// (termasuk yang tersimpan di failed_jobs) harus terenkripsi dengan APP_KEY.
class SendReportWhatsAppNotification implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, LabelsReportStatus, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [30, 120, 300];
    public int $timeout = 120;

    public function __construct(
        public int $reportId,
        public string $event,
        public ?string $accessCode = null,
    ) {
        $this->onQueue('whatsapp');
    }

    public function handle(WahaService $waha): void
    {
        $report = Report::find($this->reportId);
        if (! $report) return;

        $phone = $this->normalizePhone($report->reporter_phone);
        if ($phone === null) return;

        // WAHA may briefly expose the session while it is reconnecting.
        // Fail the job explicitly so Laravel retries instead of silently losing
        // the notification during a transient session state.
        $session = (string) config('services.waha.session', 'default');
        $sessionInfo = $waha->session($session);
        if (($sessionInfo['status'] ?? null) !== 'WORKING') {
            throw new RuntimeException('WAHA session is not ready: '.($sessionInfo['status'] ?? 'UNKNOWN'));
        }

        // Resolve the phone number through WAHA first. This prevents sending to
        // an unintended LID/contact and lets us fail safely when the number is
        // not registered on WhatsApp.
        $exists = $waha->checkNumberExists($phone, $session);
        if (($exists['numberExists'] ?? false) !== true) {
            // Jangan pernah menulis nomor pelapor ke log atau failed_jobs.exception.
            // Pakai handle HMAC yang tidak bisa dibalik, sama seperti
            // submitted_ip_hash / submitted_device_hash pada tabel reports.
            throw new RuntimeException(
                'The reporter WhatsApp number is not registered. Report: '.$this->reportId
                .', phone handle: '.$this->phoneHandle($phone)
            );
        }
        $chatId = (string) ($exists['chatId'] ?? ($phone.'@c.us'));
        if ($chatId === '') {
            throw new RuntimeException('WAHA returned no chatId for reporter number.');
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

        // Kirim logo SMK Taruna Bangsa sebagai media WhatsApp dengan detail laporan sebagai caption.
        // Gunakan BASE64 dari file lokal supaya WAHA tidak perlu mengambil asset
        // melalui domain publik/Cloudflare yang bisa gagal di sisi server.
        $logoPath = public_path('images/branding/logo tb.png');
        try {
            $result = $waha->sendImageFile($chatId, $logoPath, implode("\n", $lines), $session);
            if (is_array($result) && $result !== []) {
                return;
            }
        } catch (Throwable $exception) {
            report($exception);
        }

        // Never lose the notification just because the remote media URL cannot
        // be fetched by WAHA. Fall back to a plain-text WhatsApp message.
        $textResult = $waha->sendText($chatId, implode("\n", $lines), $session);
        if (! is_array($textResult) || $textResult === []) {
            throw new RuntimeException('WAHA could not send image or text notification.');
        }
    }

    /**
     * Buang kode akses saat job gagal supaya nilainya tidak ikut dipertahankan
     * bersama job yang sudah mati.
     */
    public function failed(?Throwable $exception): void
    {
        $this->accessCode = null;
    }

    private function normalizePhone(?string $phone): ?string
    {
        if (! is_string($phone) || trim($phone) === '') return null;
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if ($digits === '') return null;
        if (str_starts_with($digits, '0')) $digits = '62'.substr($digits, 1);
        return str_starts_with($digits, '62') && strlen($digits) >= 10 && strlen($digits) <= 15 ? $digits : null;
    }

    /**
     * Handle korelasi non-reversible untuk nomor pelapor, aman untuk log.
     */
    private function phoneHandle(string $phone): string
    {
        return substr(hash_hmac('sha256', $phone, (string) config('app.key')), 0, 12);
    }
}
