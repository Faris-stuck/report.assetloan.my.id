<?php
header('Content-Type: application/json');
require_once "../koneksi.php";

$step = $_POST['step'] ?? '';
$email = $_POST['email'] ?? '';

if (!$email) {
    http_response_code(400);
    echo json_encode(["status" => false, "message" => "Email is required"]);
    exit;
}

// ─── STEP 1: Request token ───────────────────────────────────────────────
if ($step === 'request_token') {
    // Check if email exists
    $stmt = $conn->prepare("SELECT id, nama FROM users WHERE email = ?");
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(["status" => false, "message" => "Internal server error"]);
        exit;
    }
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $res = $stmt->get_result();
    if (!$res || $res->num_rows === 0) {
        http_response_code(404);
        echo json_encode(["status" => false, "message" => "Email not found in the system"]);
        exit;
    }
    $user = $res->fetch_assoc();

    // Generate 6-digit token
    $token = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
    $expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));

    // Store token in DB (create column if needed, or use a simple approach)
    $stmtToken = $conn->prepare("UPDATE users SET reset_token = ?, reset_token_expires = ? WHERE email = ?");
    if (!$stmtToken) {
        // If columns don't exist, create them
        $conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS reset_token VARCHAR(10) DEFAULT NULL");
        $conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS reset_token_expires DATETIME DEFAULT NULL");
        $stmtToken = $conn->prepare("UPDATE users SET reset_token = ?, reset_token_expires = ? WHERE email = ?");
    }
    $stmtToken->bind_param("sss", $token, $expires, $email);
    $stmtToken->execute();

    // Send email with token
    try {
        require_once __DIR__ . '/../email/email-functions.php';
        $subject = "Password Reset Token - Komatsu Indonesia Borrowing System";
        $body = "<h3>Password Reset</h3>
                 <p>Hello <strong>{$user['nama']}</strong>,</p>
                 <p>Your password reset verification token is:</p>
                 <h2 style='color:#1e3a8a; letter-spacing:4px;'>{$token}</h2>
                 <p>This token expires in <strong>15 minutes</strong>.</p>
                 <p>If you did not request this, please ignore this email.</p>";
        sendEmailDirect($email, $subject, $body);
    } catch (Exception $emailEx) {
        error_log("[EMAIL ERROR] forgot-password token: " . $emailEx->getMessage());
        // Still return success — token is stored even if email fails
    }

    echo json_encode([
        "status" => true,
        "message" => "Verification token has been sent to your email"
    ]);
    exit;
}

// ─── STEP 2: Reset password with token ──────────────────────────────────
if ($step === 'reset_password') {
    $token = $_POST['token'] ?? '';
    $new_password = $_POST['password'] ?? '';

    if (!$token || !$new_password) {
        http_response_code(400);
        echo json_encode(["status" => false, "message" => "Token and password are required"]);
        exit;
    }

    // Verify token
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND reset_token = ? AND reset_token_expires > NOW()");
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(["status" => false, "message" => "Internal server error"]);
        exit;
    }
    $stmt->bind_param("ss", $email, $token);
    $stmt->execute();
    $res = $stmt->get_result();

    if (!$res || $res->num_rows === 0) {
        http_response_code(400);
        echo json_encode(["status" => false, "message" => "Invalid or expired token"]);
        exit;
    }

    // Update password and clear token
    $hashed = password_hash($new_password, PASSWORD_DEFAULT);
    $update_stmt = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_token_expires = NULL WHERE email = ?");
    if (!$update_stmt) {
        http_response_code(500);
        echo json_encode(["status" => false, "message" => "Internal server error"]);
        exit;
    }
    $update_stmt->bind_param("ss", $hashed, $email);

    if ($update_stmt->execute()) {
        echo json_encode(["status" => true, "message" => "Password reset successfully"]);
    } else {
        http_response_code(500);
        echo json_encode(["status" => false, "message" => "Failed to update password"]);
    }
    exit;
}

// ─── Fallback for old-style direct reset (backward compat) ──────────────
$new_password = $_POST['password'] ?? '';
if (!$new_password) {
    http_response_code(400);
    echo json_encode(["status" => false, "message" => "Missing step parameter. Use step=request_token or step=reset_password"]);
    exit;
}

http_response_code(400);
echo json_encode(["status" => false, "message" => "Invalid request. Please use the password reset form."]);
?>
