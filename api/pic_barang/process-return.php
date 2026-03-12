<?php
/**
 * API: Pic Barang - Proses Pengembalian (update status jadi Dikembalikan + kembalikan stok)
 * Endpoint: /api/pic_barang/process-return.php
 */

require_once "../koneksi.php";
require_once "../session-helper.php";
header('Content-Type: application/json');

try {
    SessionValidator::requireRole(['pic_barang']);
} catch (Exception $e) {
    http_response_code(403);
    echo json_encode(["status" => false, "message" => $e->getMessage()]);
    exit;
}

$id = (int)($_POST['id'] ?? 0);
if (!$id) {
    echo json_encode(["status" => false, "message" => "Borrowing ID is required"]);
    exit;
}

$conn->begin_transaction();

try {
    // Lock and check current status
    $chk = $conn->prepare("SELECT id, status FROM peminjaman WHERE id = ? FOR UPDATE");
    $chk->bind_param("i", $id);
    $chk->execute();
    $row = $chk->get_result()->fetch_assoc();
    
    if (!$row) {
        throw new Exception("Borrowing not found");
    }
    if (in_array($row['status'], ['Returned', 'Rejected'])) {
        throw new Exception("Borrowing already has status: " . $row['status']);
    }

    // Get total items borrowed (approved units from peminjaman_units)
    $tq = $conn->prepare("SELECT COUNT(*) as total FROM peminjaman_units WHERE peminjaman_id = ? AND approval_status = 'Approved'");
    $tq->bind_param("i", $id);
    $tq->execute();
    $total_items = (int)($tq->get_result()->fetch_assoc()['total'] ?? 0);
    
    // Get total already returned (finalized) from aggregate
    $aq = $conn->prepare("
        SELECT COALESCE(SUM(dp.jumlah_kembali), 0) as total_returned
        FROM detail_pengembalian dp
        JOIN pengembalian p ON dp.pengembalian_id = p.id
        WHERE p.peminjaman_id = ? AND p.status = 'Completed'
    ");
    $aq->bind_param("i", $id);
    $aq->execute();
    $total_already_returned = (int)($aq->get_result()->fetch_assoc()['total_returned'] ?? 0);
    
    // Only restore stock for items NOT yet returned via pengembalian flow
    // Use approved units from peminjaman_units (not dp.jumlah)
    $detail_query = $conn->prepare("
        SELECT pu.barang_id, COUNT(pu.id) as jumlah,
            COALESCE((
                SELECT SUM(dr.jumlah_kembali) FROM detail_pengembalian dr
                JOIN pengembalian p ON dr.pengembalian_id = p.id
                WHERE p.peminjaman_id = ? AND dr.barang_id = pu.barang_id AND p.status = 'Completed'
            ), 0) as already_returned
        FROM peminjaman_units pu
        WHERE pu.peminjaman_id = ? AND pu.approval_status = 'Approved'
        GROUP BY pu.barang_id
    ");
    $detail_query->bind_param("ii", $id, $id);
    $detail_query->execute();
    $detail_result = $detail_query->get_result();
    
    while ($detail = $detail_result->fetch_assoc()) {
        $barang_id = (int)$detail['barang_id'];
        $jumlah = (int)$detail['jumlah'];
        $already_returned = (int)$detail['already_returned'];
        // Only restore remaining items not yet returned through pengembalian
        $remaining = $jumlah - $already_returned;
        if ($remaining > 0) {
            $restore = $conn->prepare("UPDATE barang SET stok_tersedia = LEAST(stok_total, stok_tersedia + ?) WHERE id = ?");
            $restore->bind_param("ii", $remaining, $barang_id);
            $restore->execute();
        }
    }
    
    // Mark as Returned
    $upd = $conn->prepare("UPDATE peminjaman SET status = 'Returned', tanggal_kembali = CURDATE() WHERE id = ?");
    $upd->bind_param("i", $id);
    $upd->execute();
    
    // Also finalize any pending pengembalian records
    $finalize = $conn->prepare("UPDATE pengembalian SET status = 'Completed', selesai_at = NOW() WHERE peminjaman_id = ? AND status IN ('Submitted', 'Being Inspected')");
    $finalize->bind_param("i", $id);
    $finalize->execute();
    
    $conn->commit();

    // Kirim email notifikasi ke user bahwa pengembalian dikonfirmasi
    try {
        require_once __DIR__ . '/../email/send-return-confirmed.php';
        sendReturnConfirmedEmail($conn, $id);
    } catch (Exception $emailEx) {
        error_log("[EMAIL ERROR] pic_barang/process-return: " . $emailEx->getMessage());
    }

    echo json_encode(["status" => true, "message" => "Return processed successfully"]);
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(["status" => false, "message" => "Error: " . $e->getMessage()]);
}
