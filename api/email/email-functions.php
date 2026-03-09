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
 * Send email using PHPMailer + SMTP Gmail (NON-BLOCKING)
 * 
 * Dispatches email to a background PHP process so the HTTP response
 * is returned immediately without waiting for SMTP. Falls back to
 * synchronous sending if background dispatch fails.
 *
 * @param string $to         Recipient email
 * @param string $subject    Email subject
 * @param string $htmlBody   Email body in HTML format
 * @param string $toName     Recipient name (optional)
 * @param string $plainBody  Plain text email body fallback (optional)
 * @return bool              true if dispatched/sent, false if failed
 */
function sendEmail($to, $subject, $htmlBody, $toName = '', $plainBody = '') {
    // Try non-blocking background dispatch first
    $dispatched = _dispatchEmailBackground($to, $subject, $htmlBody, $toName, $plainBody);
    if ($dispatched) {
        return true;
    }

    // Fallback: send synchronously if background dispatch failed
    error_log("[EMAIL] Background dispatch failed, falling back to synchronous send for {$to}");
    return _sendEmailSync($to, $subject, $htmlBody, $toName, $plainBody);
}

/**
 * Dispatch email to background PHP process (non-blocking)
 *
 * @return bool  true if dispatched successfully
 */
function _dispatchEmailBackground($to, $subject, $htmlBody, $toName, $plainBody) {
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
    $cmd = escapeshellarg($phpBin) . ' ' . escapeshellarg($workerScript) . ' ' . escapeshellarg($tmpFile);

    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        // Windows: use start /B for background
        $cmd = 'start /B ' . $cmd;
        pclose(popen($cmd, 'r'));
    } else {
        // Linux/Mac: redirect output and run in background
        $cmd .= ' > /dev/null 2>&1 &';
        exec($cmd);
    }

    error_log("[EMAIL] Dispatched background email to {$to} via {$tmpFile}");
    return true;
}

/**
 * Find the PHP CLI binary path
 *
 * @return string|null  Path to PHP binary, or null if not found
 */
function _findPhpBinary() {
    // Check common XAMPP paths first
    $candidates = [
        '/opt/lampp/bin/php',           // Linux XAMPP
        'E:\\xampp\\php\\php.exe',      // Windows XAMPP
        'C:\\xampp\\php\\php.exe',      // Windows XAMPP alt
        PHP_BINARY,                     // Current PHP binary
    ];

    foreach ($candidates as $path) {
        if (!empty($path) && file_exists($path) && is_executable($path)) {
            return $path;
        }
    }

    // Try system PATH
    $which = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? 'where php' : 'which php';
    $result = trim(shell_exec($which) ?? '');
    if (!empty($result)) {
        $firstLine = strtok($result, "\n");
        if (file_exists($firstLine)) {
            return $firstLine;
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
 * @return int              Number of emails successfully sent
 */
function sendEmailToRole($conn, $role, $subject, $htmlBody) {
    $users = getEmailsByRole($conn, $role);
    $sent = 0;
    foreach ($users as $user) {
        if (sendEmail($user['email'], $subject, $htmlBody, $user['nama'])) {
            $sent++;
        }
    }
    return $sent;
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
 * Send email to all recipients using LOOP
 * REQUIRED: Each recipient gets their own sendEmail() call
 *
 * @param array  $recipients  Array of ['nama'=>..., 'email'=>...]
 * @param string $subject     Subject email
 * @param string $htmlBody    Full HTML body email
 * @return int                Number of emails successfully sent
 */
function sendEmailToAll($recipients, $subject, $htmlBody) {
    $totalSent = 0;
    foreach ($recipients as $r) {
        if (sendEmail($r['email'], $subject, $htmlBody, $r['nama'])) {
            error_log("[EMAIL] EMAIL SENT TO: " . $r['email'] . " (" . $r['nama'] . ")");
            $totalSent++;
        } else {
            error_log("[EMAIL] EMAIL FAILED TO: " . $r['email'] . " (" . $r['nama'] . ")");
        }
    }
    return $totalSent;
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
            p.tanggal_pinjam,
            p.rencana_kembali,
            p.tanggal_kembali,
            p.status,
            p.catatan,
            u.email,
            u.nama AS nama_user
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
