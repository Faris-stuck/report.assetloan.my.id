<?php

namespace App\Jobs;

use App\Models\Report;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Fan-out job: email and WhatsApp retry independently. */
class SendReportNotifications implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        public int $reportId,
        public string $event,
        public ?string $accessCode = null,
    ) {}

    public function handle(): void
    {
        $report = Report::find($this->reportId);
        if (! $report) return;

        SendReportEmailNotification::dispatch($report->id, $this->event, $this->accessCode);
        SendReportWhatsAppNotification::dispatch($report->id, $this->event, $this->accessCode);
    }
}
