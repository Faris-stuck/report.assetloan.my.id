<?php
header('Content-Type: application/json');
require_once "../koneksi.php";
$id = $_POST['id'];
$catatan = $_POST['catatan'] ?? '';

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
        throw new Exception("Peminjaman sudah berstatus '{$current['status']}', tidak dapat ditolak lagi");
    }
    
    // Update status to Ditolak with catatan/alasan
    $tanggal_ditolak = date('Y-m-d');
    $stmt_update = $conn->prepare("UPDATE peminjaman SET status='Ditolak', catatan=?, tanggal_kembali=? WHERE id=?");
    $stmt_update->bind_param("ssi", $catatan, $tanggal_ditolak, $id);
    $stmt_update->execute();
    
    // Restore stok_tersedia (cap at stok_total)
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
    
    $conn->commit();
    
    echo json_encode([
        "status" => true,
        "message" => "Peminjaman berhasil ditolak dengan alasan: $catatan. Stok barang telah dikembalikan."
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
