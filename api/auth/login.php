<?php
header('Content-Type: application/json');
require_once "../koneksi.php";

// Start session sebelum apapun (1 session = 1 role per browser)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';

if (!$email || !$password) {
    http_response_code(400);
    echo json_encode(["error" => "Email dan password diperlukan"]);
    exit;
}

$stmt = $conn->prepare("SELECT id, nama, role, nrp, email, password FROM users WHERE email = ? LIMIT 1");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(["error" => "Internal server error"]);
    exit;
}

$stmt->bind_param("s", $email);
$stmt->execute();
$res = $stmt->get_result();

if ($res && $res->num_rows > 0) {
    $user = $res->fetch_assoc();
    $stored = (string)($user['password'] ?? '');

    // Verify password (plaintext comparison for development)
    if ($password !== $stored) {
        http_response_code(401);
        echo json_encode(["error" => "Login failed"]);
        exit;
    }

    // Clear old session before creating new one
    // This ensures 1 browser = 1 role (cannot multi-role)
    $_SESSION = array();
    session_regenerate_id(true); // Regenerate ID for security

    // Set new session
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['user_nama'] = $user['nama'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['login_time'] = time();

    unset($user['password']);
    echo json_encode($user);
} else {
    http_response_code(401);
    echo json_encode(["error" => "Login failed"]);
}

