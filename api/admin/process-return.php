<?php
header('Content-Type: application/json');
require_once "../koneksi.php";
$id = $_POST['id'];
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


$status = $_POST['status'];
$id = intval($id);

$conn->begin_transaction();

try {
    // Cek status saat ini
    $stmt_check = $conn->prepare("SELECT status FROM peminjaman WHERE id = ? FOR UPDATE");
    $stmt_check->bind_param("i", $id);
    $stmt_check->execute();
    $current = $stmt_check->get_result()->fetch_assoc();
    
    if (!$current) {
        throw new Exception("Borrowing not found");
    }
    
    if ($current['status'] === 'Returned') {
        throw new Exception("Borrowing already returned, cannot be processed again");
    }
    
    $stmt_update = $conn->prepare("UPDATE peminjaman SET status=? WHERE id=?");
    $stmt_update->bind_param("si", $status, $id);
    $stmt_update->execute();
    
    // If Returned, restore item stock (aggregate-aware: only restore remaining items)
    if ($status === 'Returned') {
        $tanggal_kembali = date('Y-m-d');
        $stmt_tgl = $conn->prepare("UPDATE peminjaman SET tanggal_kembali=? WHERE id=?");
        $stmt_tgl->bind_param("si", $tanggal_kembali, $id);
        $stmt_tgl->execute();
        
        // Get per-barang from approved units (peminjaman_units) with already-returned aggregate
        $stmt_detail = $conn->prepare("
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
        $stmt_detail->bind_param("ii", $id, $id);
        $stmt_detail->execute();
        $detail_query = $stmt_detail->get_result();
        while ($detail = $detail_query->fetch_assoc()) {
            $barang_id = intval($detail['barang_id']);
            $jumlah = intval($detail['jumlah']);
            $already_returned = intval($detail['already_returned']);
            // Only restore items not yet returned via pengembalian flow
            $remaining = $jumlah - $already_returned;
            if ($remaining > 0) {
                $stmt_restore = $conn->prepare("UPDATE barang SET stok_tersedia = LEAST(stok_total, stok_tersedia + ?) WHERE id = ?");
                $stmt_restore->bind_param("ii", $remaining, $barang_id);
                $stmt_restore->execute();
            }
        }
        
        // Finalize any pending pengembalian records
        $stmt_finalize = $conn->prepare("UPDATE pengembalian SET status = 'Completed', selesai_at = NOW() WHERE peminjaman_id = ? AND status IN ('Submitted', 'Being Inspected')");
        $stmt_finalize->bind_param("i", $id);
        $stmt_finalize->execute();
    }
    // If Rejected, restore item stock - use approved units from peminjaman_units
    if ($status === 'Rejected') {
        // Check if peminjaman_units exist (manager already processed approval)
        $chk_units = $conn->prepare("SELECT COUNT(*) as cnt FROM peminjaman_units WHERE peminjaman_id = ?");
        $chk_units->bind_param("i", $id);
        $chk_units->execute();
        $has_units = (int)($chk_units->get_result()->fetch_assoc()['cnt'] ?? 0) > 0;

        if ($has_units) {
            $stmt_detail = $conn->prepare("
                SELECT pu.barang_id, COUNT(pu.id) as jumlah
                FROM peminjaman_units pu
                WHERE pu.peminjaman_id = ? AND pu.approval_status = 'Approved'
                GROUP BY pu.barang_id
            ");
            $stmt_detail->bind_param("i", $id);
        } else {
            $stmt_detail = $conn->prepare("SELECT barang_id, jumlah FROM detail_peminjaman WHERE peminjaman_id = ?");
            $stmt_detail->bind_param("i", $id);
        }
        $stmt_detail->execute();
        $detail_query = $stmt_detail->get_result();
        while ($detail = $detail_query->fetch_assoc()) {
            $barang_id = intval($detail['barang_id']);
            $jumlah = intval($detail['jumlah']);
            $stmt_restore = $conn->prepare("UPDATE barang SET stok_tersedia = LEAST(stok_total, stok_tersedia + ?) WHERE id = ?");
            $stmt_restore->bind_param("ii", $jumlah, $barang_id);
            $stmt_restore->execute();
        }
    }
    
    $conn->commit();

    // Kirim email notifikasi berdasarkan status
    if ($status === 'Returned') {
        try {
            require_once __DIR__ . '/../email/send-return-confirmed.php';
            sendReturnConfirmedEmail($conn, $id);
        } catch (Exception $emailEx) {
            error_log("[EMAIL ERROR] admin/process-return: " . $emailEx->getMessage());
        }
    }

    // Kirim email notifikasi penolakan pengembalian ke user
    if ($status === 'Rejected') {
        try {
            require_once __DIR__ . '/../email/send-rejected.php';
            sendRejectedEmail($conn, $id, 'Return');
        } catch (Exception $emailEx) {
            error_log("[EMAIL ERROR] admin/process-return-reject: " . $emailEx->getMessage());
        }
    }

    echo json_encode(["status" => true, "success" => true, "message" => "Status updated successfully"]);    
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(["status" => false, "success" => false, "message" => "Error: " . $e->getMessage()]);
}
?>
