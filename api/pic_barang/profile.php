<?php
/**
 * API: Pic Barang Profile (session user)
 * Endpoint: /api/pic_barang/profile.php
 */

require_once "../koneksi.php";
require_once "../session-helper.php";
header('Content-Type: application/json');

try {
    SessionValidator::requireRole(['pic_barang']);
    $user_id = SessionValidator::getUserId();
    if (!$user_id) {
        throw new Exception("Session tidak valid");
    }

    $stmt = $conn->prepare("SELECT id, nama, nrp, email, role, created_at FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        unset($row['password']);
        echo json_encode([
            'status' => true,
            'data' => $row
        ]);
    } else {
        http_response_code(404);
        echo json_encode(['status' => false, 'message' => 'User tidak ditemukan']);
    }
} catch (Exception $e) {
    http_response_code(403);
    echo json_encode([
        'status' => false,
        'message' => $e->getMessage()
    ]);
}
