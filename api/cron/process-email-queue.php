<?php
/**
 * ============================================================
 * CRON: PROCESS EMAIL QUEUE
 * ============================================================
 *
 * Processes queued email payloads that were persisted when
 * background dispatch or SMTP send failed in user-triggered flows.
 *
 * Access:
 *   - CLI:
 *       php api/cron/process-email-queue.php --max=50 --json
 *   - HTTP (token-protected):
 *       /PROJECT/api/cron/process-email-queue.php?token=...&max=50
 * ============================================================
 */

$isCli = php_sapi_name() === 'cli';
$secret = getenv('CRON_SECRET') ?: 'K0m4tsu_Cr0n_2026';

$jsonOutput = false;
$maxPerRun = max(1, (int) (getenv('EMAIL_QUEUE_MAX_PER_RUN') ?: 25));

if ($isCli) {
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
} else {
    $token = (string) ($_GET['token'] ?? '');
    if ($token !== $secret) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Forbidden: Invalid cron token';
        exit;
    }

    if (isset($_GET['max'])) {
        $maxPerRun = max(1, (int) $_GET['max']);
    }
    $jsonOutput = (string) ($_GET['format'] ?? '') === 'json';

    if ($jsonOutput) {
        header('Content-Type: application/json; charset=utf-8');
    } else {
        header('Content-Type: text/plain; charset=utf-8');
    }
}

require_once __DIR__ . '/../email/email-functions.php';

$summary = processEmailQueue($maxPerRun);
$pendingCount = count(glob(_getEmailQueueDir() . '/mailq_*.json') ?: []);
$failedCount = count(glob(_getEmailQueueFailedDir() . '/failed_mailq_*.json') ?: []);

$result = [
    'status' => true,
    'executed_at' => date('c'),
    'max_per_run' => $maxPerRun,
    'summary' => $summary,
    'queue_pending' => $pendingCount,
    'queue_failed' => $failedCount,
];

if ($jsonOutput) {
    echo json_encode($result, JSON_UNESCAPED_SLASHES);
    exit;
}

echo "============================================================\n";
echo "  CRON: Process Email Queue\n";
echo "  Execution time: " . date('Y-m-d H:i:s') . "\n";
echo "============================================================\n";
echo "[INFO] max_per_run: {$maxPerRun}\n";
echo "[INFO] processed   : {$summary['processed']}\n";
echo "[INFO] sent        : {$summary['sent']}\n";
echo "[INFO] requeued    : {$summary['requeued']}\n";
echo "[INFO] failed      : {$summary['failed']}\n";
echo "[INFO] deferred    : {$summary['deferred']}\n";
echo "[INFO] pending_now : {$pendingCount}\n";
echo "[INFO] failed_now  : {$failedCount}\n";
echo "============================================================\n";
