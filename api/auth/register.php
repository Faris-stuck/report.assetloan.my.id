<?php
header('Content-Type: application/json');
require_once "../koneksi.php";

$nama = $_POST['nama'] ?? '';
$nrp = $_POST['nrp'] ?? '';
$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';

// Validasi input
if (!$nama || !$nrp || !$email || !$password) {
    http_response_code(400);
    echo json_encode([
        "status" => false,
        "message" => "Semua field wajib diisi"
    ]);
    exit;
}

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
        "message" => "Email sudah terdaftar. Gunakan email lain atau login."
    ]);
    exit;
}

$check_stmt->close();

// Store password as plaintext (development only)
$plain_password = $password;

// Insert user baru dengan role default 'user'
$insert_stmt = $conn->prepare("INSERT INTO users (nama, nrp, email, password, role) VALUES (?, ?, ?, ?, 'user')");
if (!$insert_stmt) {
    http_response_code(500);
    echo json_encode([
        "status" => false,
        "message" => "Internal server error"
    ]);
    exit;
}

$insert_stmt->bind_param("ssss", $nama, $nrp, $email, $plain_password);

if ($insert_stmt->execute()) {
    echo json_encode([
        "status" => true,
        "message" => "Registrasi berhasil. Silakan login."
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        "status" => false,
        "message" => "Gagal melakukan registrasi. Coba lagi nanti."
    ]);
}

$insert_stmt->close();
?>
