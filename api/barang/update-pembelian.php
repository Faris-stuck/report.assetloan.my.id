<?php
require_once "../koneksi.php";
header("Content-Type: application/json");

// Server-side session validation using proper session keys
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once "../session-helper.php";

// Validate session and require authorized roles
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

$id = $_POST['id'] ?? null;
$id_barang = $_POST['id_barang'] ?? null;
$tanggal_pembelian = $_POST['tanggal_pembelian'] ?? null;
$vendor_id = $_POST['vendor_id'] ?? null;
$jumlah = $_POST['jumlah'] ?? null;
$harga_satuan = $_POST['harga_satuan'] ?? null;
$keterangan = $_POST['keterangan'] ?? '';

if (!$id || !$id_barang || !$tanggal_pembelian || !$vendor_id || !$jumlah || !$harga_satuan) {
    echo json_encode([
        "status" => false,
        "message" => "Missing required fields"
    ]);
    exit;
}

// Validate date (allow past dates for editing)
$tanggal = strtotime($tanggal_pembelian);
if (!$tanggal) {
    echo json_encode([
        "status" => false,
        "message" => "Invalid date format"
    ]);
    exit;
}

// Validate quantity and price
$jumlah = (int)$jumlah;
$harga_satuan = (float)$harga_satuan;

if ($jumlah <= 0) {
    echo json_encode([
        "status" => false,
        "message" => "Quantity must be greater than 0"
    ]);
    exit;
}

if ($harga_satuan <= 0) {
    echo json_encode([
        "status" => false,
        "message" => "Price must be greater than 0"
    ]);
    exit;
}

// Update purchase record
$q = $conn->prepare("
    UPDATE pembelian_barang 
    SET tanggal_pembelian = ?, 
        vendor_id = ?, 
        jumlah = ?, 
        harga_satuan = ?, 
        keterangan = ?
    WHERE id = ? AND barang_id = ?
");

$q->bind_param("siidisi", $tanggal_pembelian, $vendor_id, $jumlah, $harga_satuan, $keterangan, $id, $id_barang);

if ($q->execute()) {
    echo json_encode([
        "status" => true,
        "message" => "Purchase updated successfully"
    ]);
} else {
    echo json_encode([
        "status" => false,
        "message" => "Database error: " . $q->error
    ]);
}
