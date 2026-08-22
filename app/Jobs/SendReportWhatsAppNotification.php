<?php

namespace App\Jobs;

use App\Jobs\Concerns\LabelsReportStatus;
use App\Models\Report;
use App\Services\WahaService;
use App\Support\PhoneNumber;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
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
        if (! $report) {
            Log::warning('WhatsApp notification skipped: report no longer exists.', [
                'report_id' => $this->reportId,
                'event' => $this->event,
            ]);

            return;
        }

        $phone = $this->normalizePhone($report->reporter_phone);
        if ($phone === null) {
            // Nomor kosong atau tidak bisa dinormalkan ke format 62xxxxxxxxx.
            // Kondisi ini permanen, tapi sebelumnya job hanya `return` tanpa
            // jejak apa pun sehingga operator tidak punya cara tahu kenapa
            // pelapor tidak menerima WhatsApp. Catat dengan handle HMAC.
            Log::warning('WhatsApp notification skipped: reporter phone is not a valid Indonesian mobile number.', [
                'report_id' => $this->reportId,
                'event' => $this->event,
                'phone_handle' => $this->phoneHandle((string) $report->reporter_phone),
            ]);

            return;
        }

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
            // Nomor yang tidak terdaftar di WhatsApp adalah kondisi PERMANEN.
            // Sebelumnya ini dilempar sebagai exception biasa sehingga job
            // dicoba 3x dengan backoff 30/120/300 detik: 6 panggilan WAHA
            // terbuang dan failed_jobs penuh noise yang menutupi kegagalan
            // nyata (mis. sesi mati). Gagalkan sekali, tanpa retry.
            //
            // Jangan pernah menulis nomor pelapor ke log atau
            // failed_jobs.exception. Pakai handle HMAC yang tidak bisa
            // dibalik, sama seperti submitted_ip_hash pada tabel reports.
            $this->failPermanently(new RuntimeException(
                'The reporter WhatsApp number is not registered. Report: '.$this->reportId
                .', phone handle: '.$this->phoneHandle($phone)
            ));

            return;
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

    /**
     * Tandai job gagal permanen tanpa menyisakan attempt.
     *
     * InteractsWithQueue::fail() hanya bekerja saat ada job antrean nyata; pada
     * eksekusi sinkron ($this->job === null) fail() diam saja, jadi exception
     * tetap dilempar supaya kegagalan tidak hilang tanpa jejak.
     */
    private function failPermanently(Throwable $exception): void
    {
        if ($this->job === null) {
            throw $exception;
        }

        $this->fail($exception);
    }

    private function normalizePhone(?string $phone): ?string
    {
        // Aturannya kini tinggal satu, dipakai bersama PublicReportRequest
        // supaya formulir tidak pernah lagi menerima nomor yang job ini
        // sendiri tolak. WAHA meminta digit tanpa "+", sedangkan kolomnya
        // menyimpan E.164 lengkap.
        return PhoneNumber::toWhatsAppNumber($phone);
    }

    /**
     * Handle korelasi non-reversible untuk nomor pelapor, aman untuk log.
     */
    private function phoneHandle(string $phone): string
    {
        return substr(hash_hmac('sha256', $phone, (string) config('app.key')), 0, 12);
    }
}
