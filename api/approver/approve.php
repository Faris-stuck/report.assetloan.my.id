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
    // Cek status saat ini untuk validasi
    $stmt_check = $conn->prepare("SELECT status FROM peminjaman WHERE id = ? FOR UPDATE");
    $stmt_check->bind_param("i", $id);
    $stmt_check->execute();
    $current = $stmt_check->get_result()->fetch_assoc();
    
    if (!$current) {
        throw new Exception("Peminjaman tidak ditemukan");
    }
    
    if ($current['status'] === 'Ditolak' || $current['status'] === 'Dikembalikan') {
        throw new Exception("Peminjaman sudah berstatus '{$current['status']}', tidak dapat diproses lagi");
    }
    
    if ($status === 'Disetujui') {
        // Manager approve: change status directly to Sedang Dipinjam (Currently Borrowed)
        // No admin approval needed - borrowing starts immediately
        $tanggal_disetujui = date('Y-m-d');
        $stmt_approve = $conn->prepare("UPDATE peminjaman SET status='Sedang Dipinjam', tanggal_disetujui=? WHERE id=?");
        $stmt_approve->bind_param("si", $tanggal_disetujui, $id);
        $stmt_approve->execute();
        echo json_encode(["status" => true, "message" => "Peminjaman disetujui dan status berubah menjadi Sedang Dipinjam."]);
    } elseif ($status === 'Ditolak') {
        // Manager reject - store rejection reason in catatan field
        $rejection_reason = isset($_POST['rejection_reason']) ? $_POST['rejection_reason'] : 'No reason provided';
        
        $stmt_reject = $conn->prepare("UPDATE peminjaman SET status='Ditolak', catatan=? WHERE id=?");
        $stmt_reject->bind_param("si", $rejection_reason, $id);
        $stmt_reject->execute();
        
        // RESTORE STOCK: Kembalikan stok_tersedia (cap at stok_total)
        $stmt_detail = $conn->prepare("SELECT barang_id, jumlah FROM detail_peminjaman WHERE peminjaman_id = ?");
        $stmt_detail->bind_param("i", $id);
        $stmt_detail->execute();
        $detail_query = $stmt_detail->get_result();
        
        while ($detail = $detail_query->fetch_assoc()) {
            $barang_id = intval($detail['barang_id']);
            $jumlah = intval($detail['jumlah']);
            
            // Restore stock, cap at stok_total
            $stmt_restore = $conn->prepare("UPDATE barang SET stok_tersedia = LEAST(stok_total, stok_tersedia + ?) WHERE id = ?");
            $stmt_restore->bind_param("ii", $jumlah, $barang_id);
            $stmt_restore->execute();
        }
        
        echo json_encode(["status" => true, "message" => "Peminjaman ditolak oleh Manager. Stok barang berhasil dikembalikan."]);
    } else {
        throw new Exception("Status tidak valid: $status");
    }
    
    $conn->commit();

    // Kirim email notifikasi setelah berhasil
    if ($status === 'Disetujui') {
        try {
            require_once __DIR__ . '/../email/send-approved.php';
            sendApprovedEmail($conn, $id);
        } catch (Exception $emailEx) {
            error_log("[EMAIL ERROR] approver/approve: " . $emailEx->getMessage());
        }
    }

    // Kirim email notifikasi penolakan ke user
    if ($status === 'Ditolak') {
        try {
            require_once __DIR__ . '/../email/send-rejected.php';
            sendRejectedEmail($conn, $id, 'Peminjaman');
        } catch (Exception $emailEx) {
            error_log("[EMAIL ERROR] approver/approve-reject: " . $emailEx->getMessage());
        }
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


