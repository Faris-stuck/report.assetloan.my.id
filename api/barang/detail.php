<?php
header('Content-Type: application/json');
// [API] DETAIL BARANG
require_once "../koneksi.php";

$id = $_GET['id'] ?? null;
if (!$id) {
    echo json_encode(["status" => false, "message" => "ID is empty"]);
    exit;
}

// DATA BARANG
$barang = $conn->query("
SELECT * FROM `barang` WHERE ID = '$id'
")->fetch_assoc();

if (!$barang) {
    echo json_encode(["status" => false, "message" => "Item not found"]);
    exit;
}

// HISTORY PEMBELIAN
$hist = [];
$q = $conn->query("
  SELECT p.tanggal_pembelian, v.nama_vendor, p.jumlah, p.harga_satuan
  FROM pembelian_barang p
  JOIN vendor v ON p.vendor_id = v.id
  WHERE p.barang_id = $id
  ORDER BY p.tanggal_pembelian DESC
");
while ($row = $q->fetch_assoc()) {
    $hist[] = $row;
}

// ================= DAFTAR PEMINJAM =================
// Uses peminjaman_units (approved only) instead of detail_peminjaman.jumlah
$peminjam = [];
$q = $conn->query("
  SELECT 
    pm.id AS peminjaman_id,
    pm.kode_peminjaman,
    u.nama,
    COUNT(pu.id) AS jumlah,
    pm.tanggal_pinjam,
    pm.rencana_kembali,
    pm.status
  FROM detail_peminjaman dp
  JOIN peminjaman pm ON dp.peminjaman_id = pm.id
  JOIN users u ON pm.user_id = u.id
  JOIN peminjaman_units pu ON pu.peminjaman_id = pm.id
    AND pu.barang_id = dp.barang_id
  WHERE dp.barang_id = $id
    AND pm.status NOT IN ('Menunggu Persetujuan', 'Ditolak')
    AND pu.approval_status = 'Disetujui'
    AND pu.return_status NOT IN ('Dikembalikan', 'Rusak')
  GROUP BY pm.id, pm.kode_peminjaman, u.nama,
           pm.tanggal_pinjam, pm.rencana_kembali, pm.status
  ORDER BY pm.tanggal_pinjam DESC
");

while ($row = $q->fetch_assoc()) {
    $nearest_expected = getNearestExpectedReturn($conn, $row['peminjaman_id']);
    $row['status'] = computeDueStatus($row['status'], $nearest_expected ?? $row['rencana_kembali']);
    $row['expected_return_nearest'] = $nearest_expected ? date('d/m/Y', strtotime($nearest_expected)) : date('d/m/Y', strtotime($row['rencana_kembali']));
    $peminjam[] = $row;
}


// HITUNG STATUS
if ($barang['stok_tersedia'] == 0) {
    $status = "Habis";
} elseif ($barang['stok_tersedia'] <= $barang['safety_stock']) {
    $status = "Menipis";
} else {
    $status = "Tersedia";
}

echo json_encode([
    "status" => true,
    "barang" => $barang,
    "status_barang" => $status,
    "history" => $hist,
    "peminjam" => $peminjam
]);
