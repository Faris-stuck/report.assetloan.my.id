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
        throw new Exception("Peminjaman tidak ditemukan");
    }
    
    if ($current['status'] === 'Ditolak' || $current['status'] === 'Dikembalikan') {
        throw new Exception("Peminjaman sudah berstatus '{$current['status']}', tidak dapat ditolak lagi");
    }
    
    // Update peminjaman status to Ditolak
    $stmt_update = $conn->prepare("UPDATE peminjaman SET status='Ditolak', catatan=? WHERE id=?");
    $stmt_update->bind_param("si", $rejection_reason, $id);
    $stmt_update->execute();
    
    // Restore stok_tersedia for all items (cap at stok_total)
    $stmt_detail = $conn->prepare("SELECT barang_id, jumlah FROM detail_peminjaman WHERE peminjaman_id = ?");
    $stmt_detail->bind_param("i", $id);
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
    
    echo json_encode([
        "status" => true,
        "message" => "Peminjaman ditolak dan stok barang berhasil dikembalikan"
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
