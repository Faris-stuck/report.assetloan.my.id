<?php
require_once "../koneksi.php";
$id = $_POST['id'];
// Server-side session validation
require_once "../session-helper.php";

// Validate user role
try {
    SessionValidator::requireRole(['admin']);
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
    
    if ($status === 'Sedang Dipinjam') {
        // Admin approve: move to "Sedang Dipinjam"
        $tanggal_disetujui = date('Y-m-d');
        $stmt_approve = $conn->prepare("UPDATE peminjaman SET status='Sedang Dipinjam', tanggal_disetujui=? WHERE id=?");
        $stmt_approve->bind_param("si", $tanggal_disetujui, $id);
        $stmt_approve->execute();
        $conn->commit();
        echo json_encode(["status" => true, "message" => "Peminjaman disetujui Admin. Peminjaman sekarang sedang berlangsung."]);
    } elseif ($status === 'Ditolak') {
        // Admin reject
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
        echo json_encode(["status" => true, "message" => "Peminjaman ditolak oleh Admin. Stok barang berhasil dikembalikan."]);
    } elseif ($status === 'Dikembalikan') {
        // Return handling
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
        echo json_encode(["status" => true, "message" => "Peminjaman berhasil dikembalikan"]);
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

