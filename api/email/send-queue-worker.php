<?php
/**
 * ============================================================
 * EMAIL QUEUE WORKER (CLI)
 * ============================================================
 *
 * Processes queued email payloads written by queue-first delivery.
 * This worker is intended to run in detached/background mode.
 *
 * Usage:
 *   php send-queue-worker.php --max=25
 *   php send-queue-worker.php --max=25 --json
 * ============================================================
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo 'Forbidden';
    exit(1);
}

require_once __DIR__ . '/email-functions.php';

$maxPerRun = max(1, (int) (getenv('EMAIL_QUEUE_MAX_PER_RUN') ?: 25));
$jsonOutput = false;

if (!empty($argv)) {
    foreach ($argv as $arg) {
        if (!is_string($arg)) {
            continue;
        }
        if (preg_match('/^--max=(\d+)$/', $arg, $m)) {
            $maxPerRun = max(1, (int) $m[1]);
            continue;
        }
        if ($arg === '--json') {
            $jsonOutput = true;
        }
    }
}

$summary = processEmailQueue($maxPerRun);
$summary['max_per_run'] = $maxPerRun;
$summary['executed_at'] = date('c');

if ($jsonOutput) {
    echo json_encode($summary, JSON_UNESCAPED_SLASHES) . PHP_EOL;
} else {
    echo "[EMAIL QUEUE WORKER] processed={$summary['processed']} sent={$summary['sent']} ";
    echo "requeued={$summary['requeued']} failed={$summary['failed']} deferred={$summary['deferred']}" . PHP_EOL;
}

error_log("[EMAIL QUEUE WORKER] processed={$summary['processed']} sent={$summary['sent']} requeued={$summary['requeued']} failed={$summary['failed']} deferred={$summary['deferred']}");
exit(0);
