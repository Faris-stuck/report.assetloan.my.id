<?php
/**
 * ============================================================
 * BACKGROUND EMAIL WORKER
 * ============================================================
 *
 * Reads payload from temp file and sends mail synchronously.
 * If SMTP send fails, payload is pushed to retry queue so it is
 * not lost when background dispatcher succeeds but SMTP fails.
 *
 * CLI only.
 * ============================================================
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo 'Forbidden';
    exit(1);
}

if ($argc < 2 || empty($argv[1])) {
    error_log("[EMAIL BG] No payload file specified");
    exit(1);
}

$payloadFile = $argv[1];
if (!file_exists($payloadFile)) {
    error_log("[EMAIL BG] Payload file not found: {$payloadFile}");
    exit(1);
}

$raw = file_get_contents($payloadFile);
@unlink($payloadFile);

if ($raw === false || trim($raw) === '') {
    error_log("[EMAIL BG] Empty payload file: {$payloadFile}");
    exit(1);
}

$payload = json_decode($raw, true);
if (!is_array($payload) || empty($payload['to']) || empty($payload['subject'])) {
    error_log("[EMAIL BG] Invalid payload format in: {$payloadFile}");
    exit(1);
}

require_once __DIR__ . '/email-functions.php';

$to = (string) $payload['to'];
$toName = (string) ($payload['toName'] ?? '');
$subject = (string) $payload['subject'];
$htmlBody = (string) ($payload['htmlBody'] ?? '');
$plainBody = (string) ($payload['plainBody'] ?? '');

if (_sendEmailSync($to, $subject, $htmlBody, $toName, $plainBody)) {
    error_log("[EMAIL BG] Successfully sent to {$to} | Subject: {$subject}");
    exit(0);
}

$queued = _enqueueEmailForRetry(_buildQueuedEmailPayload($to, $subject, $htmlBody, $toName, $plainBody));
if ($queued) {
    if (!_dispatchQueueWorkerBackground()) {
        error_log("[EMAIL BG] SMTP failed and queue worker could not be started immediately; payload kept in queue");
    }
    error_log("[EMAIL BG] SMTP failed, queued for retry: {$to} | Subject: {$subject}");
    exit(0);
}

error_log("[EMAIL BG ERROR] SMTP failed and queue fallback failed for {$to} | Subject: {$subject}");
exit(1);
