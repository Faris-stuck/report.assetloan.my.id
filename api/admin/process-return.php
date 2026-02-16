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
        throw new Exception("Peminjaman tidak ditemukan");
    }
    
    if ($current['status'] === 'Dikembalikan') {
        throw new Exception("Peminjaman sudah dikembalikan, tidak dapat diproses lagi");
    }
    
    $stmt_update = $conn->prepare("UPDATE peminjaman SET status=? WHERE id=?");
    $stmt_update->bind_param("si", $status, $id);
    $stmt_update->execute();
    
    // Jika dikembalikan, kembalikan stok barang (cap at stok_total)
    if ($status === 'Dikembalikan') {
        $tanggal_kembali = date('Y-m-d');
        $stmt_tgl = $conn->prepare("UPDATE peminjaman SET tanggal_kembali=? WHERE id=?");
        $stmt_tgl->bind_param("si", $tanggal_kembali, $id);
        $stmt_tgl->execute();
        
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
    }
    // Jika ditolak, kembalikan stok barang (cap at stok_total)
    if ($status === 'Ditolak') {
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
    }
    
    $conn->commit();
    echo json_encode(["status" => true, "success" => true, "message" => "Status berhasil diupdate"]);
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(["status" => false, "success" => false, "message" => "Error: " . $e->getMessage()]);
}
?>
