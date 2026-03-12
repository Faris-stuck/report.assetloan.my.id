<?php
header('Content-Type: application/json');
// [API] DETAIL BARANG
require_once "../koneksi.php";
require_once "../session-helper.php";

// Require any authenticated user
try {
    SessionValidator::requireRole(['admin', 'manager', 'pic_barang', 'user']);
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(["status" => false, "message" => "Unauthorized"]);
    exit;
}

$id = $_GET['id'] ?? null;
if (!$id) {
    echo json_encode(["status" => false, "message" => "ID is empty"]);
    exit;
}

// DATA BARANG — prepared statement
$stmt_barang = $conn->prepare("SELECT * FROM `barang` WHERE ID = ?");
if (!$stmt_barang) {
    echo json_encode(["status" => false, "message" => "Query error"]);
    exit;
}
$stmt_barang->bind_param("i", $id);
$stmt_barang->execute();
$result_barang = $stmt_barang->get_result();
$barang = $result_barang ? $result_barang->fetch_assoc() : null;

if (!$barang) {
    echo json_encode(["status" => false, "message" => "Item not found"]);
    exit;
}

// HISTORY PEMBELIAN — prepared statement
$hist = [];
$stmt_hist = $conn->prepare("
  SELECT p.tanggal_pembelian, v.nama_vendor, p.jumlah, p.harga_satuan
  FROM pembelian_barang p
  JOIN vendor v ON p.vendor_id = v.id
  WHERE p.barang_id = ?
  ORDER BY p.tanggal_pembelian DESC
");
if ($stmt_hist) {
    $stmt_hist->bind_param("i", $id);
    $stmt_hist->execute();
    $result_hist = $stmt_hist->get_result();
    while ($row = $result_hist->fetch_assoc()) {
        $hist[] = $row;
    }
}

// ================= DAFTAR PEMINJAM — prepared statement =================
$peminjam = [];
$stmt_pinjam = $conn->prepare("
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
  WHERE dp.barang_id = ?
    AND pm.status NOT IN ('Waiting for Approval', 'Rejected')
    AND pu.approval_status = 'Approved'
    AND pu.return_status NOT IN ('Returned', 'Damaged')
  GROUP BY pm.id, pm.kode_peminjaman, u.nama,
           pm.tanggal_pinjam, pm.rencana_kembali, pm.status
  ORDER BY pm.tanggal_pinjam DESC
");
if ($stmt_pinjam) {
    $stmt_pinjam->bind_param("i", $id);
    $stmt_pinjam->execute();
    $q = $stmt_pinjam->get_result();
    while ($row = $q->fetch_assoc()) {
        $nearest_expected = getNearestExpectedReturn($conn, $row['peminjaman_id']);
        $row['status'] = computeDueStatus($row['status'], $nearest_expected ?? $row['rencana_kembali']);
        $row['expected_return_nearest'] = $nearest_expected ? date('d/m/Y', strtotime($nearest_expected)) : date('d/m/Y', strtotime($row['rencana_kembali']));
        $peminjam[] = $row;
    }
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
