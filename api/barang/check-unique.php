<?php
require_once "../koneksi.php";
header('Content-Type: application/json');

$kode = $_GET['kode'] ?? null;
$nama = $_GET['nama'] ?? null;

$response = [
    'status' => true,
    'kode_exists' => false,
    'nama_exists' => false
];

if ($kode) {
    $stmt = $conn->prepare("SELECT id FROM barang WHERE kode_barang = ?");
    $stmt->bind_param('s', $kode);
    $stmt->execute();
    $stmt->store_result();
    $response['kode_exists'] = $stmt->num_rows > 0;
}

if ($nama) {
    $stmt = $conn->prepare("SELECT id FROM barang WHERE nama_barang = ?");
    $stmt->bind_param('s', $nama);
    $stmt->execute();
    $stmt->store_result();
    $response['nama_exists'] = $stmt->num_rows > 0;
}

echo json_encode($response);
