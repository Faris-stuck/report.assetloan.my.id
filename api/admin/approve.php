<?php
header('Content-Type: application/json');
require_once "../koneksi.php";
$id = $_POST['id'];
// Server-side session validation
require_once "../session-helper.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
    
    if ($current['status'] === 'Ditolak' || $current['status'] === 'Dikembalikan') {
        throw new Exception("Borrowing already has status '{$current['status']}', cannot be processed again");
    }
    
    // Manager approval is now the only approval needed - status goes directly to Sedang Dipinjam
    // Admin API here handles other admin functions: returns, and conditional rejections
    
    if ($status === 'Dikembalikan') {
        // Process item return
        $tanggal_kembali = date('Y-m-d');
        $stmt_return = $conn->prepare("UPDATE peminjaman SET status='Dikembalikan', tanggal_kembali=? WHERE id=?");
        $stmt_return->bind_param("si", $tanggal_kembali, $id);
        $stmt_return->execute();
        
        // Return stock - aggregate-aware: use approved units from peminjaman_units (not dp.jumlah)
        $stmt_detail = $conn->prepare("
            SELECT pu.barang_id, COUNT(pu.id) as jumlah,
                COALESCE((
                    SELECT SUM(dr.jumlah_kembali) FROM detail_pengembalian dr
                    JOIN pengembalian p ON dr.pengembalian_id = p.id
                    WHERE p.peminjaman_id = ? AND dr.barang_id = pu.barang_id AND p.status = 'Selesai'
                ), 0) as already_returned
            FROM peminjaman_units pu
            WHERE pu.peminjaman_id = ? AND pu.approval_status = 'Disetujui'
            GROUP BY pu.barang_id
        ");
        $stmt_detail->bind_param("ii", $id, $id);
        $stmt_detail->execute();
        $detail_query = $stmt_detail->get_result();
        while ($detail = $detail_query->fetch_assoc()) {
            $barang_id = intval($detail['barang_id']);
            $jumlah = intval($detail['jumlah']);
            $already_returned = intval($detail['already_returned']);
            $remaining = $jumlah - $already_returned;
            if ($remaining > 0) {
                $stmt_restore = $conn->prepare("UPDATE barang SET stok_tersedia = LEAST(stok_total, stok_tersedia + ?) WHERE id = ?");
                $stmt_restore->bind_param("ii", $remaining, $barang_id);
                $stmt_restore->execute();
            }
        }
        
        // Finalize any pending pengembalian records
        $stmt_finalize = $conn->prepare("UPDATE pengembalian SET status = 'Selesai', selesai_at = NOW() WHERE peminjaman_id = ? AND status IN ('Diajukan', 'Dicek')");
        $stmt_finalize->bind_param("i", $id);
        $stmt_finalize->execute();
        
        $conn->commit();

        // Kirim email notifikasi ke user bahwa pengembalian dikonfirmasi
        try {
            require_once __DIR__ . '/../email/send-return-confirmed.php';
            sendReturnConfirmedEmail($conn, $id);
        } catch (Exception $emailEx) {
            error_log("[EMAIL ERROR] admin/approve: " . $emailEx->getMessage());
        }

        echo json_encode(["status" => true, "message" => "Item successfully returned and stock restored."]);
    } elseif ($status === 'Ditolak') {
        // Admin can reject even after manager approval if needed
        $rejection_reason = isset($_POST['rejection_reason']) ? $_POST['rejection_reason'] : 'No reason provided';
        
        $stmt_reject = $conn->prepare("UPDATE peminjaman SET status='Ditolak', catatan=? WHERE id=?");
        $stmt_reject->bind_param("si", $rejection_reason, $id);
        $stmt_reject->execute();
        
        // RESTORE STOCK: use approved units from peminjaman_units (not dp.jumlah)
        // Check if peminjaman_units exist (manager already processed approval)
        $chk_units = $conn->prepare("SELECT COUNT(*) as cnt FROM peminjaman_units WHERE peminjaman_id = ?");
        $chk_units->bind_param("i", $id);
        $chk_units->execute();
        $has_units = (int)($chk_units->get_result()->fetch_assoc()['cnt'] ?? 0) > 0;

        if ($has_units) {
            // Restore stock only for approved units (rejected units' stock already restored during approval)
            $stmt_detail = $conn->prepare("
                SELECT pu.barang_id, COUNT(pu.id) as jumlah
                FROM peminjaman_units pu
                WHERE pu.peminjaman_id = ? AND pu.approval_status = 'Disetujui'
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
            error_log("[EMAIL ERROR] admin/approve-reject: " . $emailEx->getMessage());
        }

        echo json_encode(["status" => true, "message" => "Borrowing rejected. Items stock has been restored."]);
    } else {
        throw new Exception("Invalid status: $status");
    }
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode([
        "status" => false,
        "message" => "Error: " . $e->getMessage()
    ]);
}
?>

