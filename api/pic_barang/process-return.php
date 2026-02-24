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
    echo json_encode(["status" => false, "message" => "ID peminjaman wajib"]);
    exit;
}

$conn->query("UPDATE peminjaman SET status = 'Dikembalikan', tanggal_kembali = CURDATE() WHERE id = $id");
if ($conn->affected_rows === 0) {
    echo json_encode(["status" => false, "message" => "Peminjaman tidak ditemukan atau bukan status Sedang Dipinjam"]);
    exit;
}

$detail_query = $conn->query("SELECT barang_id, jumlah FROM detail_peminjaman WHERE peminjaman_id = $id");
while ($detail = $detail_query->fetch_assoc()) {
    $conn->query("UPDATE barang SET stok_tersedia = stok_tersedia + " . (int)$detail['jumlah'] . " WHERE id = " . (int)$detail['barang_id']);
}

// Kirim email notifikasi ke user bahwa pengembalian dikonfirmasi
try {
    require_once __DIR__ . '/../email/send-return-confirmed.php';
    sendReturnConfirmedEmail($conn, $id);
} catch (Exception $emailEx) {
    error_log("[EMAIL ERROR] pic_barang/process-return: " . $emailEx->getMessage());
}

echo json_encode(["status" => true, "message" => "Pengembalian berhasil diproses"]);
