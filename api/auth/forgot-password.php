<?php
header('Content-Type: application/json');
require_once __DIR__ . "/../koneksi.php";

function jsonResponse(int $code, bool $status, string $message): void
{
    http_response_code($code);
    echo json_encode(["status" => $status, "message" => $message]);
    exit;
}

function ensureResetColumns(mysqli $conn): void
{
    $conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS reset_token VARCHAR(10) DEFAULT NULL");
    $conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS reset_token_expires DATETIME DEFAULT NULL");
}

function generateFreshResetToken(?string $previousToken = null): string
{
    $previousToken = (string) ($previousToken ?? '');
    for ($i = 0; $i < 6; $i++) {
        $candidate = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        if ($candidate !== $previousToken) {
            return $candidate;
        }
    }

    // Deterministic fallback to guarantee new token when random repeats.
    if ($previousToken !== '' && ctype_digit($previousToken)) {
        $next = ((int) $previousToken + random_int(1, 9)) % 1000000;
        return str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }

    return str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
}

function sendResetToken(mysqli $conn, string $email, bool $isResend = false): void
{
    ensureResetColumns($conn);

    $stmt = $conn->prepare("SELECT id, nama, nrp, email, reset_token FROM users WHERE email = ?");
    if (!$stmt) {
        jsonResponse(500, false, "Internal server error");
    }

    $stmt->bind_param("s", $email);
    $stmt->execute();
    $res = $stmt->get_result();

    if (!$res || $res->num_rows === 0) {
        jsonResponse(404, false, "Email not found in the system");
    }

    $user = $res->fetch_assoc();
    $oldToken = (string) ($user['reset_token'] ?? '');
    $token = generateFreshResetToken($oldToken);
    $expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));

    $stmtToken = $conn->prepare("UPDATE users SET reset_token = ?, reset_token_expires = ? WHERE email = ?");
    if (!$stmtToken) {
        jsonResponse(500, false, "Internal server error");
    }

    $stmtToken->bind_param("sss", $token, $expires, $email);
    if (!$stmtToken->execute()) {
        jsonResponse(500, false, "Failed to generate verification token");
    }

    try {
        require_once __DIR__ . '/../email/email-functions.php';
        $subject = $isResend
            ? "Resend Password Reset Token - Komatsu Indonesia Borrowing System"
            : "Password Reset Token - Komatsu Indonesia Borrowing System";
        $safeName = htmlspecialchars((string)($user['nama'] ?? '-'));
        $safeToken = htmlspecialchars($token);
        $identityRows = buildBorrowerIdentityRows([
            'nama' => (string)($user['nama'] ?? '-'),
            'email' => (string)($user['email'] ?? $email),
            'nrp' => (string)($user['nrp'] ?? '-'),
        ], 'generic');
        $body = buildEmailTemplate(
            'Password Reset Token',
            "<p>Hello <strong>{$safeName}</strong>,</p>
             <p>Your password reset verification token is:</p>
             <div class='info-box' style='text-align:center;'>
                 <div style='font-size:30px; font-weight:700; letter-spacing:4px; color:#1e3a8a;'>{$safeToken}</div>
             </div>
             <table class='info-table'>
                {$identityRows}
             </table>
             <p>This token expires in <strong>15 minutes</strong>.</p>
             <p><strong>Use the latest token only.</strong> Any previous token is no longer valid.</p>
             <p>If you did not request this, please ignore this email.</p>"
        );

        $queued = queueEmail($email, $subject, $body, $user['nama']);
        if (!$queued) {
            jsonResponse(500, false, "Failed to send verification token. Please try resend.");
        }
        dispatchEmailQueueWorker();
    } catch (Throwable $emailEx) {
        error_log("[EMAIL ERROR] forgot-password token: " . $emailEx->getMessage());
        jsonResponse(500, false, "Failed to send verification token. Please try resend.");
    }

    $message = $isResend
        ? "A new verification token has been resent to your email"
        : "Verification token has been sent to your email";
    jsonResponse(200, true, $message);
}

$step = $_POST['step'] ?? '';
$email = trim($_POST['email'] ?? '');

if ($email === '') {
    jsonResponse(400, false, "Email is required");
}

try {
    // STEP 1: Request / Resend token
    if ($step === 'request_token' || $step === 'resend_token') {
        sendResetToken($conn, $email, $step === 'resend_token');
    }

    // STEP 2: Reset password with token
    if ($step === 'reset_password') {
        $token = trim($_POST['token'] ?? '');
        $new_password = (string) ($_POST['password'] ?? '');

        if ($token === '' || $new_password === '') {
            jsonResponse(400, false, "Token and password are required");
        }

        if (strlen($new_password) < 6) {
            jsonResponse(400, false, "Password must be at least 6 characters");
        }

        ensureResetColumns($conn);

        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND reset_token = ? AND reset_token_expires > NOW()");
        if (!$stmt) {
            jsonResponse(500, false, "Internal server error");
        }

        $stmt->bind_param("ss", $email, $token);
        $stmt->execute();
        $res = $stmt->get_result();

        if (!$res || $res->num_rows === 0) {
            jsonResponse(400, false, "Invalid or expired token");
        }

        // Store password as plaintext (development only)
        $update_stmt = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_token_expires = NULL WHERE email = ?");
        if (!$update_stmt) {
            jsonResponse(500, false, "Internal server error");
        }

        $update_stmt->bind_param("ss", $new_password, $email);

        if ($update_stmt->execute()) {
            jsonResponse(200, true, "Password reset successfully");
        }

        jsonResponse(500, false, "Failed to update password");
    }

    jsonResponse(400, false, "Missing step parameter. Use step=request_token, step=resend_token, or step=reset_password");
} catch (Throwable $e) {
    error_log("[FORGOT PASSWORD ERROR] " . $e->getMessage());
    jsonResponse(500, false, "Internal server error");
}
