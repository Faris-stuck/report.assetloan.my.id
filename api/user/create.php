<?php
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

$nama = $_POST['nama'] ?? '';
$nrp = $_POST['nrp'] ?? '';
$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';
$role = $_POST['role'] ?? 'user';

// Validasi input
if (!$nama || !$nrp || !$email || !$password) {
    http_response_code(400);
    echo json_encode([
        "status" => false,
        "message" => "Semua field wajib diisi (nama, nrp, email, password)"
    ]);
    exit;
}

// Validate role against database roles table
$roleCheck = $conn->prepare("SELECT role_name FROM roles WHERE role_name = ?");
$roleCheck->bind_param('s', $role);
$roleCheck->execute();
if ($roleCheck->get_result()->num_rows === 0) {
    $role = 'user'; // fallback to default
}
$roleCheck->close();

// Cek apakah email sudah terdaftar
$check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
if (!$check_stmt) {
    http_response_code(500);
    echo json_encode([
        "status" => false,
        "message" => "Internal server error"
    ]);
    exit;
}

$check_stmt->bind_param("s", $email);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result && $check_result->num_rows > 0) {
    http_response_code(400);
    echo json_encode([
        "status" => false,
        "message" => "Email sudah terdaftar. Gunakan email lain."
    ]);
    exit;
}

$check_stmt->close();

// Hash password
$hashed_password = password_hash($password, PASSWORD_BCRYPT);
if (!$hashed_password) {
    http_response_code(500);
    echo json_encode([
        "status" => false,
        "message" => "Gagal memproses password"
    ]);
    exit;
}

// Insert user baru (terhubung dengan tabel peminjaman via user_id)
$insert_stmt = $conn->prepare("INSERT INTO users (nama, nrp, email, password, role) VALUES (?, ?, ?, ?, ?)");
if (!$insert_stmt) {
    http_response_code(500);
    echo json_encode([
        "status" => false,
        "message" => "Internal server error"
    ]);
    exit;
}

$insert_stmt->bind_param("sssss", $nama, $nrp, $email, $hashed_password, $role);

if ($insert_stmt->execute()) {
    $user_id = $conn->insert_id;
    echo json_encode([
        "status" => true,
        "message" => "User berhasil dibuat.",
        "user_id" => (int) $user_id
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        "status" => false,
        "message" => "Gagal membuat user. Coba lagi nanti."
    ]);
}

$insert_stmt->close();
