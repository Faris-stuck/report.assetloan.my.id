<?php
/**
 * API: Approve Extend Request
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
    echo json_encode(['status' => false, 'message' => 'Akses ditolak. Hanya admin dan PIC Barang yang dapat menyetujui']);
    exit;
}

$extend_id = isset($_POST['extend_id']) ? (int)$_POST['extend_id'] : 0;

if ($extend_id <= 0) {
    echo json_encode(['status' => false, 'message' => 'Extend ID tidak valid']);
    exit;
}

try {
    $conn->begin_transaction();

    // Get extend request
    $stmt = $conn->prepare("SELECT e.*, p.rencana_kembali FROM extend_peminjaman e JOIN peminjaman p ON e.peminjaman_id = p.id WHERE e.id = ? FOR UPDATE");
    $stmt->bind_param("i", $extend_id);
    $stmt->execute();
    $extend = $stmt->get_result()->fetch_assoc();

    if (!$extend) {
        $conn->rollback();
        echo json_encode(['status' => false, 'message' => 'Permintaan perpanjangan tidak ditemukan']);
        exit;
    }

    if ($extend['status'] !== 'Pending') {
        $conn->rollback();
        echo json_encode(['status' => false, 'message' => 'Permintaan sudah diproses (status: ' . $extend['status'] . ')']);
        exit;
    }

    $approver_id = $session->getUserId();
    $now = date('Y-m-d H:i:s');

    // Update extend_peminjaman status to Approved
    $stmt = $conn->prepare("UPDATE extend_peminjaman SET status = 'Approved', approved_by = ?, approved_at = ? WHERE id = ?");
    $stmt->bind_param("isi", $approver_id, $now, $extend_id);
    $stmt->execute();

    // Update peminjaman.rencana_kembali to the new date
    $stmt = $conn->prepare("UPDATE peminjaman SET rencana_kembali = ? WHERE id = ?");
    $stmt->bind_param("si", $extend['tanggal_perpanjang'], $extend['peminjaman_id']);
    $stmt->execute();

    $conn->commit();

    // Kirim email notifikasi ke user bahwa perpanjangan disetujui
    try {
        require_once __DIR__ . '/../email/send-extend-approved.php';
        sendExtendApprovedEmail($conn, $extend['peminjaman_id'], $extend['tanggal_perpanjang']);
    } catch (Exception $emailEx) {
        error_log("[EMAIL ERROR] extend/approve: " . $emailEx->getMessage());
    }

    echo json_encode([
        'status' => true,
        'message' => 'Perpanjangan disetujui. Tanggal kembali diperbarui ke ' . date('d/m/Y', strtotime($extend['tanggal_perpanjang']))
    ]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['status' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
