<?php
/**
 * API: Pic Barang - Daftar Barang (hanya role pic_barang)
 * Endpoint: /api/pic_barang/barang-get.php
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

$q = $conn->query("
  SELECT id, kode_barang, nama_barang, kategori, lokasi,
      stok_total, stok_tersedia, safety_stock, kondisi, keterangan, created_at
  FROM barang
  ORDER BY id ASC
");

$data = [];
while ($row = $q->fetch_assoc()) {
    if ($row['stok_tersedia'] == 0) {
        $row['status'] = 'Habis';
    } elseif ($row['stok_tersedia'] <= $row['safety_stock']) {
        $row['status'] = 'Menipis';
    } else {
        $row['status'] = 'Tersedia';
    }
    $data[] = $row;
}

echo json_encode([
    "status" => true,
    "data" => $data
]);
