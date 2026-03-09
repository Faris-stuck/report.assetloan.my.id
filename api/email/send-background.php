<?php
/**
 * ============================================================
 * BACKGROUND EMAIL WORKER
 * ============================================================
 * 
 * This script is executed as a separate PHP process by sendEmail()
 * in email-functions.php. It reads email parameters from a temp
 * file and sends the email via PHPMailer SMTP.
 * 
 * Usage (called automatically by email-functions.php):
 *   /opt/lampp/bin/php send-background.php /tmp/email_XXXXX > /dev/null 2>&1 &
 * 
 * This script should NEVER be called via HTTP / browser.
 * ============================================================
 */

// Block HTTP access — this script is CLI-only
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo 'Forbidden';
    exit(1);
}

// Validate arguments
if ($argc < 2 || empty($argv[1])) {
    error_log("[EMAIL BG] No payload file specified");
    exit(1);
}

$payloadFile = $argv[1];

// Read and validate payload file
if (!file_exists($payloadFile)) {
    error_log("[EMAIL BG] Payload file not found: {$payloadFile}");
    exit(1);
}

$raw = file_get_contents($payloadFile);

// Delete temp file immediately (cleanup)
@unlink($payloadFile);

if (empty($raw)) {
    error_log("[EMAIL BG] Empty payload file: {$payloadFile}");
    exit(1);
}

$payload = json_decode($raw, true);
if (!$payload || empty($payload['to']) || empty($payload['subject'])) {
    error_log("[EMAIL BG] Invalid payload format in: {$payloadFile}");
    exit(1);
}

// Load dependencies
require_once __DIR__ . '/../../config/email.php';
require_once __DIR__ . '/../../phpmailer/src/Exception.php';
require_once __DIR__ . '/../../phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../../phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Extract params
$to        = $payload['to'];
$toName    = $payload['toName'] ?? '';
$subject   = $payload['subject'];
$htmlBody  = $payload['htmlBody'] ?? '';
$plainBody = $payload['plainBody'] ?? '';

// Send email
try {
    $mail = new PHPMailer(true);

    $mail->isSMTP();
    $mail->Host       = $smtpConfig['host'];
    $mail->SMTPAuth   = true;
    $mail->Username   = $smtpConfig['username'];
    $mail->Password   = $smtpConfig['password'];
    $mail->SMTPSecure = $smtpConfig['secure'];
    $mail->Port       = $smtpConfig['port'];
    $mail->CharSet    = 'UTF-8';

    $mail->setFrom($smtpConfig['username'], $smtpConfig['fromName']);
    $mail->addAddress($to, $toName);

    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body    = $htmlBody;
    $mail->AltBody = $plainBody ?: strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $htmlBody));

    $mail->send();

    error_log("[EMAIL BG] Successfully sent to {$to} — Subject: {$subject}");
    exit(0);

} catch (Exception $e) {
    error_log("[EMAIL BG ERROR] Failed to send to {$to}: " . $mail->ErrorInfo);
    exit(1);
}
