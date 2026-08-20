<?php

namespace App\Jobs;

use App\Models\Report;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

// ShouldBeEncrypted: payload job membawa kode akses laporan, jadi seluruh payload
// (termasuk yang tersimpan di failed_jobs) harus terenkripsi dengan APP_KEY.
/** Fan-out job: email and WhatsApp retry independently. */
class SendReportNotifications implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        public int $reportId,
        public string $event,
        public ?string $accessCode = null,
    ) {
        $this->onQueue('notifications');
    }

    public function handle(): void
    {
        $report = Report::find($this->reportId);
        if (! $report) return;

        SendReportEmailNotification::dispatch($report->id, $this->event, $this->accessCode);
        SendReportWhatsAppNotification::dispatch($report->id, $this->event, $this->accessCode);
    }

    /**
     * Buang kode akses saat job gagal supaya nilainya tidak ikut dipertahankan
     * bersama job yang sudah mati.
     */
    public function failed(?Throwable $exception): void
    {
        $this->accessCode = null;
    }
}
