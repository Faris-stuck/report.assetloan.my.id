<?php
header('Content-Type: application/json');
require_once "../koneksi.php";
$id = $_POST['id'];
$rejection_reason = isset($_POST['rejection_reason']) ? $_POST['rejection_reason'] : 'No reason provided';

// Server-side session validation
require_once "../session-helper.php";

// Validate user role
try {
    SessionValidator::requireRole(['admin', 'manager']);
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode([
        "status" => false,
        "message" => "Unauthorized: " . $e->getMessage()
    ]);
    exit;
}

$id = intval($id);
$rejection_reason = $conn->real_escape_string($rejection_reason);

$conn->begin_transaction();

try {
    // Cek status saat ini - hanya bisa ditolak jika masih Menunggu Persetujuan
    $stmt_check = $conn->prepare("SELECT status FROM peminjaman WHERE id = ? FOR UPDATE");
    $stmt_check->bind_param("i", $id);
    $stmt_check->execute();
    $current = $stmt_check->get_result()->fetch_assoc();
    
    if (!$current) {
        throw new Exception("Borrowing not found");
    }
    
    if ($current['status'] === 'Rejected' || $current['status'] === 'Returned') {
        throw new Exception("Borrowing already has status '{$current['status']}', cannot be rejected again");
    }
    
    // Update peminjaman status to Rejected
    $stmt_update = $conn->prepare("UPDATE peminjaman SET status='Rejected', catatan=? WHERE id=?");
    $stmt_update->bind_param("si", $rejection_reason, $id);
    $stmt_update->execute();
    
    // Restore stok_tersedia - use approved units from peminjaman_units if they exist
    $chk_units = $conn->prepare("SELECT COUNT(*) as cnt FROM peminjaman_units WHERE peminjaman_id = ?");
    $chk_units->bind_param("i", $id);
    $chk_units->execute();
    $has_units = (int)($chk_units->get_result()->fetch_assoc()['cnt'] ?? 0) > 0;

    if ($has_units) {
        // Restore stock only for approved units (rejected units' stock already restored during approval)
        $stmt_detail = $conn->prepare("
            SELECT pu.barang_id, COUNT(pu.id) as jumlah
            FROM peminjaman_units pu
            WHERE pu.peminjaman_id = ? AND pu.approval_status = 'Approved'
            GROUP BY pu.barang_id
        ");
        $stmt_detail->bind_param("i", $id);
    } else {
        // No units yet (still pending approval) - restore original dp.jumlah
        $stmt_detail = $conn->prepare("SELECT barang_id, jumlah FROM detail_peminjaman WHERE peminjaman_id = ?");
        $stmt_detail->bind_param("i", $id);
    }
    $stmt_detail->execute();
    $detail_query = $stmt_detail->get_result();
    
    while ($detail = $detail_query->fetch_assoc()) {
        $barang_id = intval($detail['barang_id']);
        $jumlah = intval($detail['jumlah']);
        
        // Restore stock, cap at stok_total to prevent over-restore
        $stmt_restore = $conn->prepare("UPDATE barang SET stok_tersedia = LEAST(stok_total, stok_tersedia + ?) WHERE id = ?");
        $stmt_restore->bind_param("ii", $jumlah, $barang_id);
        $stmt_restore->execute();
    }
    
    $conn->commit();

    // Kirim email notifikasi penolakan ke user
    try {
        require_once __DIR__ . '/../email/send-rejected.php';
        sendRejectedEmail($conn, $id, 'Loan');
    } catch (Exception $emailEx) {
        error_log("[EMAIL ERROR] approver/reject: " . $emailEx->getMessage());
    }
    
    echo json_encode([
        "status" => true,
        "message" => "Borrowing rejected and item stock restored"
    ]);
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode([
        "status" => false,
        "message" => "Error: " . $e->getMessage()
    ]);
}
?>
