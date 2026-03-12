<?php
header('Content-Type: application/json');
// [API] GET DATA BARANG
require_once "../koneksi.php";
require_once "../session-helper.php";

try {
    SessionValidator::requireRole(['admin', 'manager', 'pic_barang', 'user']);
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(["status" => false, "message" => "Unauthorized"]);
    exit;
}

$q = $conn->query("
  SELECT id, kode_barang, nama_barang, kategori, lokasi,
      stok_total, stok_tersedia, safety_stock, kondisi, keterangan, created_at
  FROM barang
  ORDER BY id ASC
");

if (!$q) {
    http_response_code(500);
    echo json_encode(["status" => false, "message" => "Database query failed"]);
    exit;
}

$data = [];
while ($row = $q->fetch_assoc()) {
    // HITUNG STATUS BERDASARKAN STOK
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
