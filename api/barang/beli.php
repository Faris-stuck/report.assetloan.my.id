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

if (!$id_barang) {
    http_response_code(400);
    echo json_encode([
        "status" => false,
        "message" => "Item ID is required"
    ]);
    exit;
}

$vendor = $_POST['vendor'];
$vendor_baru = $_POST['vendor_baru'] ?? null;
$alamat = $_POST['alamat'] ?? null;
$kontak = $_POST['kontak'] ?? null;
$tanggal = $_POST['tanggal_pembelian'];
$jumlah = isset($_POST['jumlah']) ? (int)$_POST['jumlah'] : 0;
$harga = isset($_POST['harga_satuan']) ? (float)$_POST['harga_satuan'] : 0;
$keterangan = $_POST['keterangan'];

// VALIDASI: tanggal pembelian tidak boleh kurang dari hari ini
$today = date('Y-m-d');
if (strtotime($tanggal) < strtotime($today)) {
    echo json_encode([
        "status" => false,
        "message" => "Purchase date cannot be earlier than today ($today)"
    ]);
    exit;
}

// VALIDASI: jumlah pembelian harus positif
if ($jumlah <= 0) {
    echo json_encode([
        "status" => false,
        "message" => "Purchase quantity must be greater than 0"
    ]);
    exit;
}

// VALIDASI: harga satuan harus positif
if ($harga <= 0) {
    echo json_encode([
        "status" => false,
        "message" => "Unit price must be greater than 0"
    ]);
    exit;
}

$total = $jumlah * $harga;

// Validate that item exists in barang table
$stmt_check = $conn->prepare("SELECT id FROM barang WHERE id = ?");
$stmt_check->bind_param("i", $id_barang);
$stmt_check->execute();
$check_result = $stmt_check->get_result();
if ($check_result->num_rows === 0) {
    http_response_code(404);
    echo json_encode([
        "status" => false,
        "message" => "Item not found"
    ]);
    exit;
}
$stmt_check->close();

// vendor baru
if ($vendor === "baru") {
    $stmt = $conn->prepare("INSERT INTO vendor(nama_vendor, alamat, kontak) VALUES(?,?,?)");
    $stmt->bind_param("sss", $vendor_baru, $alamat, $kontak);
    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode([
            "status" => false,
            "message" => "Database error occurred"
        ]);
        exit;
    }
    $vendor = $conn->insert_id;
} else {
    // Update existing vendor with address and contact if provided
    if (!empty($alamat) || !empty($kontak)) {
        $updateVendorStmt = $conn->prepare("UPDATE vendor SET alamat = COALESCE(?, alamat), kontak = COALESCE(?, kontak) WHERE id = ?");
        $updateVendorStmt->bind_param("ssi", $alamat, $kontak, $vendor);
        $updateVendorStmt->execute();
        $updateVendorStmt->close();
    }
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
    "status" => true,
    "message" => "Purchase successful"
]);
