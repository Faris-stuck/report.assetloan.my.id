<?php
require_once "../../koneksi.php";
require_once "../../session-helper.php";
header('Content-Type: application/json');

try {
    SessionValidator::requireRole(['admin', 'pic_barang']);
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(["status" => false, "message" => "Unauthorized"]);
    exit;
}

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
        p.id,
        p.vendor_id,
        p.tanggal_pembelian,
        v.nama_vendor,
        p.jumlah,
        p.harga_satuan,
        p.keterangan
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
    // Convert to proper types and ensure decimal precision
    $harga_satuan = (float)$r['harga_satuan'];
    $jumlah = (int)$r['jumlah'];
    $total = $harga_satuan * $jumlah;
    
    $data[] = [
        'id' => (int)$r['id'],
        'vendor_id' => (int)$r['vendor_id'],
        'tanggal_pembelian' => $r['tanggal_pembelian'],
        'nama_vendor' => $r['nama_vendor'],
        'jumlah' => $jumlah,
        'harga_satuan' => $harga_satuan,
        'keterangan' => $r['keterangan'],
        'total' => $total
    ];
}

echo json_encode([
    "status" => true,
    "data" => $data
]);
