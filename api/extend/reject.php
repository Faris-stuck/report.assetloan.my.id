<?php
/**
 * API: Reject Extend Request
 * Method: POST
 * Params: extend_id
 * Role: admin, pic_barang
 */
require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/../session-helper.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => false, 'message' => 'Method not allowed']);
    exit;
}

$session = new SessionValidator();
if (!$session->isLoggedIn()) {
    http_response_code(403);
    echo json_encode(['status' => false, 'message' => 'Unauthorized']);
    exit;
}

$role = $session->getRole();
if (!in_array($role, ['admin', 'pic_barang'])) {
    http_response_code(403);
    echo json_encode(['status' => false, 'message' => 'Akses ditolak. Hanya admin dan PIC Barang yang dapat menolak']);
    exit;
}

$extend_id = isset($_POST['extend_id']) ? (int)$_POST['extend_id'] : 0;

if ($extend_id <= 0) {
    echo json_encode(['status' => false, 'message' => 'Extend ID tidak valid']);
    exit;
}

try {
    // Get extend request
    $stmt = $conn->prepare("SELECT id, status FROM extend_peminjaman WHERE id = ?");
    $stmt->bind_param("i", $extend_id);
    $stmt->execute();
    $extend = $stmt->get_result()->fetch_assoc();

    if (!$extend) {
        echo json_encode(['status' => false, 'message' => 'Permintaan perpanjangan tidak ditemukan']);
        exit;
    }

    if ($extend['status'] !== 'Pending') {
        echo json_encode(['status' => false, 'message' => 'Permintaan sudah diproses (status: ' . $extend['status'] . ')']);
        exit;
    }

    $approver_id = $session->getUserId();
    $now = date('Y-m-d H:i:s');

    // Update extend_peminjaman status to Rejected
    $stmt = $conn->prepare("UPDATE extend_peminjaman SET status = 'Rejected', approved_by = ?, approved_at = ? WHERE id = ?");
    $stmt->bind_param("isi", $approver_id, $now, $extend_id);
    $stmt->execute();

    // Kirim email notifikasi penolakan perpanjangan ke user
    try {
        require_once __DIR__ . '/../email/send-extend-rejected.php';
        sendExtendRejectedEmail($conn, $extend_id);
    } catch (Exception $emailEx) {
        error_log("[EMAIL ERROR] extend/reject: " . $emailEx->getMessage());
    }

    echo json_encode(['status' => true, 'message' => 'Permintaan perpanjangan ditolak']);

} catch (Exception $e) {
    echo json_encode(['status' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
