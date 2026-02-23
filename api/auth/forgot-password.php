<?php
header('Content-Type: application/json');
require_once "../koneksi.php";

$email = $_POST['email'] ?? '';
$new_password = $_POST['password'] ?? '';

if (!$email || !$new_password) {
    http_response_code(400);
    echo json_encode([
        "status" => false,
        "message" => "Email dan password diperlukan"
    ]);
    exit;
}

// Cek apakah email ada di database
$stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
if (!$stmt) {
    http_response_code(500);
    echo json_encode([
        "status" => false,
        "message" => "Internal server error"
    ]);
    exit;
}

$stmt->bind_param("s", $email);
$stmt->execute();
$res = $stmt->get_result();

if (!$res || $res->num_rows === 0) {
    http_response_code(404);
    echo json_encode([
        "status" => false,
        "message" => "Email tidak ditemukan dalam sistem"
    ]);
    exit;
}

// Store new password as plaintext (development only)
$plain_password = $new_password;

// Update password
$update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
if (!$update_stmt) {
    http_response_code(500);
    echo json_encode([
        "status" => false,
        "message" => "Internal server error"
    ]);
    exit;
}

$update_stmt->bind_param("ss", $plain_password, $email);

if ($update_stmt->execute()) {
    echo json_encode([
        "status" => true,
        "message" => "Password berhasil direset"
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        "status" => false,
        "message" => "Gagal memperbarui password"
    ]);
}

$update_stmt->close();
?>
