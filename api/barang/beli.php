<?php
header('Content-Type: application/json');
require_once "../koneksi.php";

// Server-side session validation
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once "../session-helper.php";

// Validate session and require authorized roles for creating purchases
try {
    SessionValidator::requireRole(['admin', 'manager', 'pic_barang']);
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode([
        "status" => false,
        "message" => "Unauthorized: " . $e->getMessage()
    ]);
    exit;
}

$id_barang = $_POST['id_barang'] ?? null;

if(!$id_barang){
    echo json_encode([
        "status"=>false,
        "message"=>"ID barang tidak ada"
    ]);
    exit;
}

$vendor = $_POST['vendor'];
$vendor_baru = $_POST['vendor_baru'] ?? null;
$tanggal = $_POST['tanggal_pembelian'];
$jumlah = isset($_POST['jumlah']) ? (int)$_POST['jumlah'] : 0;
$harga = isset($_POST['harga_satuan']) ? (float)$_POST['harga_satuan'] : 0;
$keterangan = $_POST['keterangan'];

// VALIDASI: tanggal pembelian tidak boleh kurang dari hari ini
$today = date('Y-m-d');
if (strtotime($tanggal) < strtotime($today)) {
    echo json_encode([
        "status"=>false,
        "message"=>"Tanggal pembelian tidak boleh kurang dari tanggal hari ini ($today)"
    ]);
    exit;
}

// VALIDASI: jumlah pembelian harus positif
if ($jumlah <= 0) {
    echo json_encode([
        "status"=>false,
        "message"=>"Jumlah pembelian harus lebih dari 0"
    ]);
    exit;
}

// VALIDASI: harga satuan harus positif
if ($harga <= 0) {
    echo json_encode([
        "status"=>false,
        "message"=>"Harga satuan harus lebih dari 0"
    ]);
    exit;
}

$total = $jumlah * $harga;

// vendor baru
if($vendor === "baru"){
    $stmt = $conn->prepare("INSERT INTO vendor(nama_vendor) VALUES(?)");
    $stmt->bind_param("s", $vendor_baru);
    $stmt->execute();
    $vendor = $conn->insert_id;
}

// insert pembelian
$stmt = $conn->prepare("
INSERT INTO pembelian_barang
(barang_id, vendor_id, tanggal_pembelian, jumlah, harga_satuan, keterangan)
VALUES (?,?,?,?,?,?)
");

$stmt->bind_param(
    "iisids",
    $id_barang,
    $vendor,
    $tanggal,
    $jumlah,
    $harga,
    $keterangan
);
$stmt->execute();

// update stok - using prepared statement
$stmtStok = $conn->prepare("UPDATE barang SET stok_total = stok_total + ?, stok_tersedia = stok_tersedia + ? WHERE id = ?");
$stmtStok->bind_param("iii", $jumlah, $jumlah, $id_barang);
$stmtStok->execute();

echo json_encode([
    "status"=>true,
    "message"=>"Pembelian berhasil"
]);
