<?php
header('Content-Type: application/json');
require_once "../koneksi.php";

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
$jumlah = $_POST['jumlah'];
$harga = $_POST['harga_satuan'];
$keterangan = $_POST['keterangan'];

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
    "iisiis",
    $id_barang,
    $vendor,
    $tanggal,
    $jumlah,
    $harga,
    $keterangan
);
$stmt->execute();

// update stok
$conn->query("
UPDATE barang
SET stok_total = stok_total + $jumlah,
    stok_tersedia = stok_tersedia + $jumlah
WHERE id = $id_barang
");

echo json_encode([
    "status"=>true,
    "message"=>"Pembelian berhasil"
]);
