<?php
/**
 * ============================================================
 * EMAIL FUNCTIONS - Reusable Functions for Sending Email
 * ============================================================
 * 
 * File   : /PROJECT/api/email/email-functions.php
 * 
 * Usage:
 *   require_once __DIR__ . '/email-functions.php';
 *   $result = sendEmail($emailFromDB, 'Subject', '<h1>Body HTML</h1>');
 * 
 * ============================================================
 */

// Load email configuration
require_once __DIR__ . '/../../config/email.php';

// Load PHPMailer
require_once __DIR__ . '/../../phpmailer/src/Exception.php';
require_once __DIR__ . '/../../phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../../phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Email queue directory (used when background dispatcher fails).
 */
function _getEmailQueueDir() {
    $customDir = trim((string) getenv('EMAIL_QUEUE_DIR'));
    if ($customDir !== '') {
        return $customDir;
    }
    return __DIR__ . '/../../tmp/email-queue';
}

function _getEmailQueueFailedDir() {
    return _getEmailQueueDir() . '/failed';
}

function _ensureEmailQueueDirs() {
    $dirs = [_getEmailQueueDir(), _getEmailQueueFailedDir()];
    foreach ($dirs as $dir) {
        if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
            error_log("[EMAIL QUEUE] Failed to create queue directory: {$dir}");
            return false;
        }
    }
    return true;
}

function _buildQueuedEmailPayload($to, $subject, $htmlBody, $toName, $plainBody) {
    return [
        'version' => 1,
        'to' => (string) $to,
        'toName' => (string) $toName,
        'subject' => (string) $subject,
        'htmlBody' => (string) $htmlBody,
        'plainBody' => (string) $plainBody,
        'attempts' => 0,
        'next_attempt_at' => time(),
        'created_at' => date('c'),
        'last_error' => null,
    ];
}

function _enqueueEmailForRetry($payload) {
    if (!_ensureEmailQueueDirs()) {
        return false;
    }

    try {
        $rand = bin2hex(random_bytes(6));
    } catch (Throwable $e) {
        $rand = uniqid('', true);
    }

    $file = _getEmailQueueDir() . '/mailq_' . date('Ymd_His') . '_' . str_replace('.', '', $rand) . '.json';
    $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        error_log("[EMAIL QUEUE] Failed to encode queue payload");
        return false;
    }

    $ok = @file_put_contents($file, $json, LOCK_EX);
    if ($ok === false) {
        error_log("[EMAIL QUEUE] Failed to write queue payload: {$file}");
        return false;
    }

    error_log("[EMAIL QUEUE] Email queued: {$file}");
    return true;
}

function _dispatchQueueWorkerBackground() {
    $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    $phpBin = _findPhpBinary();
    if (!$phpBin) {
        error_log("[EMAIL QUEUE] Cannot find PHP binary for queue worker");
        return false;
    }

    $workerScript = __DIR__ . '/send-queue-worker.php';
    if (!file_exists($workerScript)) {
        error_log("[EMAIL QUEUE] Queue worker not found: {$workerScript}");
        return false;
    }

    $maxPerRun = max(1, (int) (getenv('EMAIL_QUEUE_MAX_PER_RUN') ?: 25));
    $cmdBase = escapeshellarg($phpBin) . ' ' . escapeshellarg($workerScript) . ' --max=' . $maxPerRun;

    if (_startBackgroundCommand($cmdBase)) {
        return true;
    }

    error_log($isWindows
        ? "[EMAIL QUEUE] Failed to start queue worker in Windows background process"
        : "[EMAIL QUEUE] Failed to start queue worker in POSIX background process");
    return false;
}

function _startBackgroundCommand($cmdBase) {
    $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';

    if ($isWindows) {
        // Use cmd /c start for detached non-blocking execution.
        $cmd = 'cmd /c start "" /B ' . $cmdBase . ' > NUL 2>&1';
        $handle = @popen($cmd, 'r');
        if (is_resource($handle)) {
            @pclose($handle);
            return true;
        }

        // Fallback to proc_open in case popen is restricted.
        $proc = @proc_open(
            'cmd /c start "" /B ' . $cmdBase,
            [
                0 => ['pipe', 'r'],
                1 => ['file', 'NUL', 'w'],
                2 => ['file', 'NUL', 'w'],
            ],
            $pipes
        );
        if (is_resource($proc)) {
            if (isset($pipes[0]) && is_resource($pipes[0])) {
                @fclose($pipes[0]);
            }
            @proc_close($proc);
            return true;
        }

        return false;
    }

    $exitCode = 1;
    exec($cmdBase . ' > /dev/null 2>&1 &', $voidOutput, $exitCode);
    return $exitCode === 0;
}

function _requeueOrFailLockedEmail($lockedPath, $payload, $errorMessage) {
    $attempts = (int)($payload['attempts'] ?? 0) + 1;
    $payload['attempts'] = $attempts;
    $payload['last_error'] = substr((string)$errorMessage, 0, 500);
    $payload['updated_at'] = date('c');
    $encodedPayload = json_encode($payload, JSON_UNESCAPED_SLASHES);
    $queuePath = substr($lockedPath, 0, -5); // remove .lock

    $maxAttempts = max(1, (int)(getenv('EMAIL_QUEUE_MAX_ATTEMPTS') ?: 20));
    if ($attempts >= $maxAttempts) {
        $failedName = basename(substr($lockedPath, 0, -5));
        $failedPath = _getEmailQueueFailedDir() . '/failed_' . $failedName;

        if ($encodedPayload === false) {
            error_log("[EMAIL QUEUE] Failed to encode payload for failed queue; restoring original queue file: {$queuePath}");
            @rename($lockedPath, $queuePath);
            return 'requeued';
        }

        $failedWritten = @file_put_contents($failedPath, $encodedPayload, LOCK_EX);
        if ($failedWritten === false) {
            error_log("[EMAIL QUEUE] Failed to move payload to failed queue; restoring original queue file: {$queuePath}");
            @rename($lockedPath, $queuePath);
            return 'requeued';
        }

        @unlink($lockedPath);
        error_log("[EMAIL QUEUE] Moved to failed queue after {$attempts} attempts: {$failedPath}");
        return 'failed';
    }

    $backoff = min(3600, (int)pow(2, max(0, $attempts - 1)) * 15); // 15s, 30s, 60s, ... capped at 1 hour
    $payload['next_attempt_at'] = time() + $backoff;
    $encodedPayload = json_encode($payload, JSON_UNESCAPED_SLASHES);

    if ($encodedPayload === false) {
        error_log("[EMAIL QUEUE] Failed to encode payload for retry; restoring original queue file: {$queuePath}");
        @rename($lockedPath, $queuePath);
        return 'requeued';
    }

    $written = @file_put_contents($queuePath, $encodedPayload, LOCK_EX);
    if ($written !== false) {
        @unlink($lockedPath);
        return 'requeued';
    }

    @rename($lockedPath, $queuePath);
    if ($written === false) {
        error_log("[EMAIL QUEUE] Failed to rewrite retry payload; restored original queue file: {$queuePath}");
    }
    return 'requeued';
}

/**
 * Process queued emails synchronously (for cron/worker).
 *
 * @param int $maxPerRun
 * @return array
 */
function processEmailQueue($maxPerRun = 25) {
    $maxPerRun = max(1, (int)$maxPerRun);
    $summary = [
        'processed' => 0,
        'sent' => 0,
        'requeued' => 0,
        'failed' => 0,
        'deferred' => 0,
    ];

    if (!_ensureEmailQueueDirs()) {
        return $summary;
    }

    $queueFiles = glob(_getEmailQueueDir() . '/mailq_*.json') ?: [];
    sort($queueFiles, SORT_STRING);

    foreach ($queueFiles as $queuePath) {
        if ($summary['processed'] >= $maxPerRun) {
            break;
        }

        $lockedPath = $queuePath . '.lock';
        if (!@rename($queuePath, $lockedPath)) {
            continue; // already handled by another worker
        }

        $summary['processed']++;
        $raw = @file_get_contents($lockedPath);
        if ($raw === false) {
            @unlink($lockedPath);
            $summary['failed']++;
            continue;
        }

        $payload = json_decode($raw, true);
        if (!is_array($payload) || empty($payload['to']) || empty($payload['subject'])) {
            @unlink($lockedPath);
            $summary['failed']++;
            continue;
        }

        $nextAttemptAt = (int)($payload['next_attempt_at'] ?? 0);
        if ($nextAttemptAt > time()) {
            // Not yet due, put it back and continue.
            @rename($lockedPath, $queuePath);
            $summary['deferred']++;
            continue;
        }

        $sent = _sendEmailSync(
            (string)$payload['to'],
            (string)$payload['subject'],
            (string)($payload['htmlBody'] ?? ''),
            (string)($payload['toName'] ?? ''),
            (string)($payload['plainBody'] ?? '')
        );

        if ($sent) {
            @unlink($lockedPath);
            $summary['sent']++;
            continue;
        }

        $outcome = _requeueOrFailLockedEmail($lockedPath, $payload, 'SMTP send failed');
        if ($outcome === 'requeued') {
            $summary['requeued']++;
        } else {
            $summary['failed']++;
        }
    }

    return $summary;
}

function _isCliContext() {
    return php_sapi_name() === 'cli';
}

function _isHttpRequestContext() {
    return !_isCliContext();
}

/**
 * Persist an email payload into the shared queue.
 *
 * @param string $to
 * @param string $subject
 * @param string $htmlBody
 * @param string $toName
 * @param string $plainBody
 * @return bool
 */
function queueEmail($to, $subject, $htmlBody, $toName = '', $plainBody = '') {
    $to = trim((string)$to);
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        error_log("[EMAIL QUEUE] Invalid recipient email: {$to}");
        return false;
    }

    return _enqueueEmailForRetry(
        _buildQueuedEmailPayload($to, $subject, $htmlBody, $toName, $plainBody)
    );
}

/**
 * Try to start the queue worker in background. Failure is non-fatal because cron remains the backup.
 *
 * @return bool true when the worker process was started, false when queued emails must wait for cron
 */
function dispatchEmailQueueWorker() {
    $dispatched = _dispatchQueueWorkerBackground();
    if (!$dispatched) {
        error_log("[EMAIL QUEUE] Queue worker could not be started immediately; queued messages remain pending for cron");
    }
    return $dispatched;
}

/**
 * Send email using queue-first delivery for HTTP requests.
 *
 * HTTP requests enqueue first and never fall back to synchronous SMTP.
 * CLI/worker/cron contexts continue using synchronous SMTP unless the caller opts into queueing.
 *
 * @param string $to         Recipient email
 * @param string $subject    Email subject
 * @param string $htmlBody   Email body in HTML format
 * @param string $toName     Recipient name (optional)
 * @param string $plainBody  Plain text email body fallback (optional)
 * @param array  $options    Optional flags:
 *                           - forceSync (bool): send synchronously
 *                           - skipDispatch (bool): queue only, caller will dispatch worker manually
 * @return bool              true if queued/sent successfully, false if failed
 */
function sendEmail($to, $subject, $htmlBody, $toName = '', $plainBody = '', $options = []) {
    $forceSync = is_array($options) && !empty($options['forceSync']);
    $skipDispatch = is_array($options) && !empty($options['skipDispatch']);

    if ($forceSync || _isCliContext()) {
        return _sendEmailSync($to, $subject, $htmlBody, $toName, $plainBody);
    }

    $queued = queueEmail($to, $subject, $htmlBody, $toName, $plainBody);
    if (!$queued) {
        return false;
    }

    if (!$skipDispatch && _isHttpRequestContext()) {
        dispatchEmailQueueWorker();
    }

    return true;
}

/**
 * Dispatch email to background PHP process (non-blocking)
 *
 * @return bool  true if dispatched successfully
 */
function _dispatchEmailBackground($to, $subject, $htmlBody, $toName, $plainBody) {
    $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';

    // Determine PHP binary path
    $phpBin = _findPhpBinary();
    if (!$phpBin) {
        error_log("[EMAIL] Cannot find PHP binary for background dispatch");
        return false;
    }

    // Path to the background worker script
    $workerScript = __DIR__ . '/send-background.php';
    if (!file_exists($workerScript)) {
        error_log("[EMAIL] Background worker not found: {$workerScript}");
        return false;
    }

    // Serialize email params to a temp file
    $payload = [
        'to'        => $to,
        'toName'    => $toName,
        'subject'   => $subject,
        'htmlBody'  => $htmlBody,
        'plainBody' => $plainBody,
    ];

    $tmpDir = sys_get_temp_dir();
    $tmpFile = tempnam($tmpDir, 'email_');
    if (!$tmpFile) {
        error_log("[EMAIL] Failed to create temp file in {$tmpDir}");
        return false;
    }

    if (file_put_contents($tmpFile, json_encode($payload)) === false) {
        error_log("[EMAIL] Failed to write payload to {$tmpFile}");
        @unlink($tmpFile);
        return false;
    }

    // Execute background process (Linux: & for background, /dev/null to detach)
    $cmdBase = escapeshellarg($phpBin) . ' ' . escapeshellarg($workerScript) . ' ' . escapeshellarg($tmpFile);

    if (_startBackgroundCommand($cmdBase)) {
        error_log($isWindows
            ? "[EMAIL] Dispatched background email (WIN) to {$to} via {$tmpFile}"
            : "[EMAIL] Dispatched background email to {$to} via {$tmpFile}");
        return true;
    }

    // Cleanup payload file if dispatcher failed to start.
    // Prevent stale temp files from accumulating in system temp directory.
    @unlink($tmpFile);
    error_log($isWindows
        ? "[EMAIL] Failed to start Windows background dispatcher"
        : "[EMAIL] Failed to start POSIX background dispatcher");
    return false;
}

function _isUsablePhpCliBinary($path) {
    if (empty($path)) {
        return false;
    }

    $name = strtolower(basename($path));
    if ($name === '' || strpos($name, 'httpd') !== false || strpos($name, 'apache') !== false) {
        return false;
    }

    $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    if ($isWindows) {
        return in_array($name, ['php.exe', 'php-win.exe', 'php-cgi.exe', 'phpdbg.exe'], true);
    }

    return $name === 'php' || strpos($name, 'php') === 0;
}

/**
 * Find the PHP CLI binary path
 *
 * @return string|null  Path to PHP binary, or null if not found
 */
function _findPhpBinary() {
    $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';

    // Priority order:
    // 1) Explicit env override
    // 2) OS-specific common install paths
    // 3) Current runtime binary (PHP_BINARY), only if it is a PHP CLI executable
    //    (in apache2handler this can be httpd.exe, which is invalid for CLI worker)
    $candidates = [];

    $envPhp = getenv('PHP_CLI_BIN');
    if (!empty($envPhp)) {
        $candidates[] = $envPhp;
    }

    if ($isWindows) {
        $candidates[] = 'E:\\xampp\\php\\php.exe';
        $candidates[] = 'C:\\xampp\\php\\php.exe';
    } else {
        $candidates[] = '/usr/bin/php';
        $candidates[] = '/usr/local/bin/php';
        $candidates[] = '/opt/lampp/bin/php';
    }

    if (defined('PHP_BINARY') && !empty(PHP_BINARY)) {
        $runtimeBinary = (string) PHP_BINARY;
        if (_isUsablePhpCliBinary($runtimeBinary)) {
            $candidates[] = $runtimeBinary;
        } else {
            // Helpful fallback when running under Apache module on XAMPP.
            // Example PHP_BINARY: E:\xampp\apache\bin\httpd.exe
            $runtimeDir = dirname($runtimeBinary);
            $parentDir = dirname($runtimeDir);
            if ($isWindows && strtolower(basename($runtimeDir)) === 'bin' && strtolower(basename($parentDir)) === 'apache') {
                $xamppRoot = dirname($parentDir);
                $candidates[] = $xamppRoot . '\\php\\php.exe';
            }
        }
    }

    foreach (array_unique($candidates) as $path) {
        if (empty($path) || !file_exists($path)) {
            continue;
        }
        if (!_isUsablePhpCliBinary($path)) {
            continue;
        }

        // is_executable can be unreliable on Windows; file existence is enough there.
        if ($isWindows || is_executable($path)) {
            return $path;
        }
    }

    // Try system PATH
    $which = $isWindows ? 'where php' : 'which php';
    $result = trim(shell_exec($which) ?? '');
    if (!empty($result)) {
        $lines = preg_split('/\r\n|\r|\n/', $result);
        foreach ($lines as $line) {
            $candidate = trim($line, " \t\n\r\0\x0B\"'");
            if ($candidate !== ''
                && file_exists($candidate)
                && _isUsablePhpCliBinary($candidate)
                && ($isWindows || is_executable($candidate))
            ) {
                return $candidate;
            }
        }
    }

    return null;
}

/**
 * Send email synchronously using PHPMailer + SMTP Gmail (BLOCKING)
 * Used as fallback when background dispatch is not available.
 *
 * @return bool  true if sent successfully
 */
function _sendEmailSync($to, $subject, $htmlBody, $toName = '', $plainBody = '') {
    global $smtpConfig;

    $mail = new PHPMailer(true);

    try {
        // SMTP Configuration
        $mail->isSMTP();
        $mail->Host       = $smtpConfig['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtpConfig['username'];
        $mail->Password   = $smtpConfig['password'];
        $mail->SMTPSecure = $smtpConfig['secure'];
        $mail->Port       = $smtpConfig['port'];
        $mail->CharSet    = 'UTF-8';

        // Sender
        $mail->setFrom($smtpConfig['username'], $smtpConfig['fromName']);

        // Recipient
        $mail->addAddress($to, $toName);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = $plainBody ?: strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $htmlBody));

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log("[EMAIL ERROR] Failed to send to {$to}: " . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Create standard HTML email template
 *
 * @param string $title      Title in email header
 * @param string $bodyHtml   Body content (HTML)
 * @return string            Full HTML email
 */
function buildEmailTemplate($title, $bodyHtml) {
    $year = date('Y');
    return '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { 
                font-family: "Segoe UI", Arial, sans-serif; 
                background: #f4f6f8; 
                margin: 0; 
                padding: 0; 
            }
            .container { 
                max-width: 600px; 
                margin: 30px auto; 
                background: #ffffff; 
                border-radius: 10px; 
                overflow: hidden;
                box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            }
            .header { 
                background: linear-gradient(135deg, #1e3a8a, #2563eb); 
                color: #ffffff; 
                padding: 28px 32px; 
                text-align: center;
            }
            .header h1 { 
                margin: 0; 
                font-size: 20px; 
                font-weight: 600; 
            }
            .header p {
                margin: 6px 0 0 0;
                font-size: 13px;
                opacity: 0.85;
            }
            .body { 
                padding: 32px; 
                color: #1f2937; 
                line-height: 1.7; 
                font-size: 15px;
            }
            .info-table {
                width: 100%;
                border-collapse: collapse;
                margin: 20px 0;
            }
            .info-table td {
                padding: 10px 14px;
                font-size: 14px;
                border-bottom: 1px solid #e5e7eb;
            }
            .info-table td:first-child {
                font-weight: 600;
                color: #374151;
                width: 45%;
            }
            .info-table td:last-child {
                color: #1f2937;
            }
            .success-box {
                background: #d1fae5;
                border-left: 4px solid #10b981;
                padding: 14px 18px;
                border-radius: 6px;
                margin: 20px 0;
                font-size: 14px;
                color: #065f46;
            }
            .info-box {
                background: #dbeafe;
                border-left: 4px solid #3b82f6;
                padding: 14px 18px;
                border-radius: 6px;
                margin: 20px 0;
                font-size: 14px;
                color: #1e3a5f;
            }
            .warning-box {
                background: #fef3c7;
                border-left: 4px solid #f59e0b;
                padding: 14px 18px;
                border-radius: 6px;
                margin: 20px 0;
                font-size: 14px;
                color: #92400e;
            }
            .footer { 
                background: #f9fafb; 
                padding: 18px 32px; 
                text-align: center; 
                font-size: 12px; 
                color: #9ca3af; 
                border-top: 1px solid #e5e7eb;
            }
            .auto-note {
                margin-top: 24px; 
                color: #6b7280; 
                font-size: 13px;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>' . htmlspecialchars($title) . '</h1>
                <p>Komatsu Indonesia - Borrowing System</p>
            </div>
            <div class="body">
                ' . $bodyHtml . '
                <p class="auto-note">
                    <em>This email was sent automatically by the system. Please do not reply to this email.</em>
                </p>
            </div>
            <div class="footer">
                &copy; ' . $year . ' ICT Komatsu Indonesia — Item Borrowing System
            </div>
        </div>
    </body>
    </html>';
}

/**
 * Normalize borrower identity from loan/extend/user query results.
 *
 * @param array $data
 * @return array{nama:string,email:string,nrp:string}
 */
function getBorrowerIdentity(array $data) {
    $nama = trim((string)($data['nama_peminjam'] ?? ''));
    if ($nama === '') {
        $nama = trim((string)($data['nama_user'] ?? ''));
    }

    $email = trim((string)($data['email'] ?? ''));

    $nrp = trim((string)($data['nrp'] ?? ''));
    if ($nrp === '') {
        $nrp = trim((string)($data['nrp_user'] ?? ''));
    }

    return [
        'nama' => $nama !== '' ? $nama : '-',
        'email' => $email !== '' ? $email : '-',
        'nrp' => $nrp !== '' ? $nrp : '-',
    ];
}

/**
 * Resolve label set for identity rows shown in system email.
 *
 * @param string $context
 * @return array{name:string,email:string,nrp:string}
 */
function getEmailIdentityLabels($context = 'borrower') {
    if ($context === 'generic') {
        return [
            'name' => 'Name',
            'email' => 'Email',
            'nrp' => 'NRP',
        ];
    }

    return [
        'name' => 'Borrower Name',
        'email' => 'Borrower Email',
        'nrp' => 'Borrower NRP',
    ];
}

/**
 * Build identity rows for email info tables.
 *
 * @param array $dataOrBorrower
 * @param string $context borrower|generic
 * @return string
 */
function buildBorrowerIdentityRows(array $dataOrBorrower, $context = 'borrower') {
    $borrower = isset($dataOrBorrower['nama'], $dataOrBorrower['email'], $dataOrBorrower['nrp'])
        ? $dataOrBorrower
        : getBorrowerIdentity($dataOrBorrower);
    $labels = getEmailIdentityLabels($context);

    return '
            <tr>
                <td>' . htmlspecialchars($labels['name']) . '</td>
                <td>' . htmlspecialchars($borrower['nama']) . '</td>
            </tr>
            <tr>
                <td>' . htmlspecialchars($labels['email']) . '</td>
                <td>' . htmlspecialchars($borrower['email']) . '</td>
            </tr>
            <tr>
                <td>' . htmlspecialchars($labels['nrp']) . '</td>
                <td>' . htmlspecialchars($borrower['nrp']) . '</td>
            </tr>';
}

/**
 * ============================================================
 * HELPER FUNCTIONS: Retrieve emails DYNAMICALLY from database
 * No hardcoded emails — all from the users table
 * ============================================================
 */

/**
 * Get all admins from database (role = 'admin')
 *
 * @param mysqli $conn   Database connection
 * @return array         Array of ['nama' => ..., 'email' => ...]
 */
function getAdminEmails($conn) {
    $result = $conn->query("SELECT nama, email FROM users WHERE role = 'admin'");
    $admins = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            if (!empty($row['email']) && filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
                $admins[] = $row;
            }
        }
    }
    return $admins;
}

/**
 * Get all managers from database (role = 'manager')
 *
 * @param mysqli $conn   Database connection
 * @return array         Array of ['nama' => ..., 'email' => ...]
 */
function getManagerEmails($conn) {
    $result = $conn->query("SELECT nama, email FROM users WHERE role = 'manager'");
    $managers = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            if (!empty($row['email']) && filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
                $managers[] = $row;
            }
        }
    }
    return $managers;
}

/**
 * Get all PIC Barang from database (role = 'pic_barang')
 *
 * @param mysqli $conn   Database connection
 * @return array         Array of ['nama' => ..., 'email' => ...]
 */
function getPicBarangEmails($conn) {
    $result = $conn->query("SELECT nama, email FROM users WHERE role = 'pic_barang'");
    $pics = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            if (!empty($row['email']) && filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
                $pics[] = $row;
            }
        }
    }
    return $pics;
}

/**
 * Get emails by a specific role
 *
 * @param mysqli $conn   Database connection
 * @param string $role   Role: 'admin', 'manager', 'pic_barang', 'user'
 * @return array         Array of ['nama' => ..., 'email' => ...]
 */
function getEmailsByRole($conn, $role) {
    $stmt = $conn->prepare("SELECT nama, email FROM users WHERE role = ?");
    if (!$stmt) return [];
    $stmt->bind_param('s', $role);
    $stmt->execute();
    $result = $stmt->get_result();
    $users = [];
    while ($row = $result->fetch_assoc()) {
        if (!empty($row['email']) && filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
            $users[] = $row;
        }
    }
    return $users;
}

/**
 * Send email to all users with a specific role
 *
 * @param mysqli $conn      Database connection
 * @param string $role      Role target
 * @param string $subject   Subject email
 * @param string $htmlBody  Body HTML
 * @return int              Number of emails successfully queued
 */
function sendEmailToRole($conn, $role, $subject, $htmlBody) {
    $users = getEmailsByRole($conn, $role);
    $queued = 0;
    foreach ($users as $user) {
        if (queueEmail($user['email'], $subject, $htmlBody, $user['nama'])) {
            $queued++;
        }
    }
    if ($queued > 0 && _isHttpRequestContext()) {
        dispatchEmailQueueWorker();
    }
    return $queued;
}

/**
 * Get actor data from SESSION (currently logged in user)
 * Retrieved from database based on $_SESSION['user_id']
 *
 * @param mysqli $conn   Database connection
 * @return array|null    ['nama' => ..., 'email' => ...] or null if failed
 */
function getActorEmail($conn) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $actor_id = $_SESSION['user_id'] ?? null;
    if (!$actor_id) return null;

    $stmt = $conn->prepare("SELECT nama, email FROM users WHERE id = ?");
    if (!$stmt) return null;
    $stmt->bind_param('i', $actor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    if ($row && !empty($row['email']) && filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
        return $row;
    }
    return null;
}

/**
 * Build $recipients array from all related parties (NO DUPLICATES)
 * Combines borrower user, admin, pic_barang, and action actor
 *
 * @param array  $sources  Array of ['nama'=>..., 'email'=>...] items
 * @return array           Deduplicated recipients array
 */
function buildUniqueRecipients(...$sources) {
    $recipients = [];
    $seen = [];
    foreach ($sources as $source) {
        if (is_null($source)) continue;
        // If single item (not array of arrays), wrap it
        if (isset($source['email'])) {
            $source = [$source];
        }
        foreach ($source as $item) {
            if (empty($item['email']) || !filter_var($item['email'], FILTER_VALIDATE_EMAIL)) continue;
            $emailLower = strtolower($item['email']);
            if (!isset($seen[$emailLower])) {
                $seen[$emailLower] = true;
                $recipients[] = $item;
            }
        }
    }
    return $recipients;
}

/**
 * Queue email to all recipients using LOOP
 * REQUIRED: Each recipient gets their own queueEmail() call
 *
 * @param array  $recipients  Array of ['nama'=>..., 'email'=>...]
 * @param string $subject     Subject email
 * @param string $htmlBody    Full HTML body email
 * @return int                Number of emails successfully queued
 */
function sendEmailToAll($recipients, $subject, $htmlBody) {
    $totalQueued = 0;
    foreach ($recipients as $r) {
        if (queueEmail($r['email'], $subject, $htmlBody, $r['nama'])) {
            error_log("[EMAIL] EMAIL QUEUED TO: " . $r['email'] . " (" . $r['nama'] . ")");
            $totalQueued++;
        } else {
            error_log("[EMAIL] EMAIL QUEUE FAILED TO: " . $r['email'] . " (" . $r['nama'] . ")");
        }
    }
    if ($totalQueued > 0 && _isHttpRequestContext()) {
        dispatchEmailQueueWorker();
    }
    return $totalQueued;
}

/**
 * Helper: Get loan + user data from database
 *
 * @param mysqli $conn           Database connection
 * @param int    $peminjamanId   Loan ID
 * @return array|null            Loan + user data, or null if not found
 */
function getPeminjamanWithUser($conn, $peminjamanId) {
    $stmt = $conn->prepare("
        SELECT 
            p.id,
            p.kode_peminjaman,
            p.nama_peminjam,
            p.nrp,
            p.tanggal_pinjam,
            p.rencana_kembali,
            p.tanggal_kembali,
            p.status,
            p.catatan,
            u.email,
            u.nama AS nama_user,
            u.nrp AS nrp_user
        FROM peminjaman p
        JOIN users u ON p.user_id = u.id
        WHERE p.id = ?
    ");

    if (!$stmt) {
        error_log("[EMAIL ERROR] Prepare failed: " . $conn->error);
        return null;
    }

    $stmt->bind_param('i', $peminjamanId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        return null;
    }

    return $result->fetch_assoc();
}
