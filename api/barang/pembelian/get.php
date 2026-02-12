<?php
require_once "../../koneksi.php";

$id_barang = $_GET['id_barang'] ?? null;

if (!$id_barang) {
    echo json_encode([
        "status" => false,
        "data" => []
    ]);
    exit;
}

$q = $conn->prepare("
    SELECT 
        p.tanggal_pembelian,
        v.nama_vendor,
        p.jumlah,
        p.harga_satuan
    FROM pembelian_barang p
    JOIN vendor v ON v.id = p.vendor_id
    WHERE p.barang_id = ?
    ORDER BY p.tanggal_pembelian DESC
");

$q->bind_param("i", $id_barang);
$q->execute();
$res = $q->get_result();

$data = [];
while ($r = $res->fetch_assoc()) {
    $r['total'] = $r['jumlah'] * $r['harga_satuan'];
    $data[] = $r;
}

echo json_encode([
    "status" => true,
    "data" => $data
]);
