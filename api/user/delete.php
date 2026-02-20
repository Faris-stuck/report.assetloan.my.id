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

$id = $_POST['id'] ?? 0;
$id = (int) $id;

if (!$id) {
    http_response_code(400);
    echo json_encode(["status" => false, "message" => "Invalid user ID"]);
    exit;
}

// Cek apakah user memiliki data peminjaman
$check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM peminjaman WHERE user_id = ?");
if ($check_stmt) {
    $check_stmt->bind_param("i", $id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    $count_row = $check_result->fetch_assoc();
    
    if ($count_row['count'] > 0) {
        http_response_code(400);
        echo json_encode(["status" => false, "message" => "User tidak dapat dihapus karena memiliki data peminjaman. Silakan hapus data peminjaman terlebih dahulu."]);
        exit;
    }
    $check_stmt->close();
}

// Delete user
$stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(["status" => false, "message" => "Internal server error"]);
    exit;
}

$stmt->bind_param("i", $id);
if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        echo json_encode(["status" => true, "message" => "User berhasil dihapus"]);
    } else {
        http_response_code(404);
        echo json_encode(["status" => false, "message" => "User tidak ditemukan"]);
    }
} else {
    http_response_code(500);
    echo json_encode(["status" => false, "message" => "Gagal menghapus user"]);
}

$stmt->close();
?>
