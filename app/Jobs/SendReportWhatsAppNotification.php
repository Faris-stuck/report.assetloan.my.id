<?php

namespace App\Jobs;

use App\Models\Report;
use App\Services\WahaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;

class SendReportWhatsAppNotification implements ShouldQueue
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
            throw new RuntimeException('The reporter WhatsApp number is not registered: '.$phone);
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

    private function normalizePhone(?string $phone): ?string
    {
        if (! is_string($phone) || trim($phone) === '') return null;
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if ($digits === '') return null;
        if (str_starts_with($digits, '0')) $digits = '62'.substr($digits, 1);
        return str_starts_with($digits, '62') && strlen($digits) >= 10 && strlen($digits) <= 15 ? $digits : null;
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
