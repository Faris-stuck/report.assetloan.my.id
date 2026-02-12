<?php
header('Content-Type: application/json');
// [API] GET DATA BARANG
require_once "../koneksi.php";

$q = $conn->query("
  SELECT id, kode_barang, nama_barang, kategori, lokasi,
      stok_total, stok_tersedia, safety_stock, kondisi, keterangan, created_at
  FROM barang
  ORDER BY id ASC
");

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
