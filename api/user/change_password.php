<?php
/**
 * API: Change User Password (Admin only)
 * Endpoint: POST /api/user/change_password.php
 * Parameters: id (user_id), password (new password)
 */
header('Content-Type: application/json');
require_once "../koneksi.php";
require_once "../session-helper.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    SessionValidator::requireRole(['admin']);
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(["status" => false, "message" => "Unauthorized: " . $e->getMessage()]);
    exit;
}

$id = (int) ($_POST['id'] ?? 0);
$password = $_POST['password'] ?? '';

if (!$id || !$password) {
    http_response_code(400);
    echo json_encode(["status" => false, "message" => "User ID dan password wajib diisi"]);
    exit;
}

if (strlen($password) < 6) {
    http_response_code(400);
    echo json_encode(["status" => false, "message" => "Password minimal 6 karakter"]);
    exit;
}

// Check user exists
$stmt = $conn->prepare("SELECT id, nama FROM users WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    http_response_code(404);
    echo json_encode(["status" => false, "message" => "User tidak ditemukan"]);
    exit;
}

// Hash and update password
$hashed = password_hash($password, PASSWORD_DEFAULT);
$stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
$stmt->bind_param("si", $hashed, $id);

if ($stmt->execute()) {
    echo json_encode([
        "status" => true,
        "message" => "Password user '{$user['nama']}' berhasil diubah"
    ]);
} else {
    http_response_code(500);
    echo json_encode(["status" => false, "message" => "Gagal mengubah password"]);
}

$stmt->close();
?>
