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
        throw new Exception("Peminjaman tidak ditemukan");
    }
    
    if ($current['status'] === 'Ditolak' || $current['status'] === 'Dikembalikan') {
        throw new Exception("Peminjaman sudah berstatus '{$current['status']}', tidak dapat diproses lagi");
    }
    
    // Manager approval is now the only approval needed - status goes directly to Sedang Dipinjam
    // Admin API here handles other admin functions: returns, and conditional rejections
    
    if ($status === 'Dikembalikan') {
        // Process item return
        $tanggal_kembali = date('Y-m-d');
        $stmt_return = $conn->prepare("UPDATE peminjaman SET status='Dikembalikan', tanggal_kembali=? WHERE id=?");
        $stmt_return->bind_param("si", $tanggal_kembali, $id);
        $stmt_return->execute();
        
        // Return stock, cap at stok_total
        $stmt_detail = $conn->prepare("SELECT barang_id, jumlah FROM detail_peminjaman WHERE peminjaman_id = ?");
        $stmt_detail->bind_param("i", $id);
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
        
        // RESTORE STOCK: cap at stok_total
        $stmt_detail = $conn->prepare("SELECT barang_id, jumlah FROM detail_peminjaman WHERE peminjaman_id = ?");
        $stmt_detail->bind_param("i", $id);
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
            sendRejectedEmail($conn, $id, 'Peminjaman');
        } catch (Exception $emailEx) {
            error_log("[EMAIL ERROR] admin/approve-reject: " . $emailEx->getMessage());
        }

        echo json_encode(["status" => true, "message" => "Borrowing rejected. Items stock has been restored."]);
    } else {
        throw new Exception("Status tidak valid: $status");
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

