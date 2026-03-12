<?php
session_start();
require_once "../koneksi.php";
header("Content-Type: application/json");
// Server-side session validation
require_once "../session-helper.php";

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



// Set error handler to catch all errors as JSON
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    http_response_code(500);
    echo json_encode([
        "status" => false,
        "message" => "PHP Error: " . $errstr,
        "file" => $errfile,
        "line" => $errline
    ]);
    exit;
});

// ============================
// AMBIL DATA
// =============================
$id            = $_POST['id'] ?? null;
$kode_barang   = $_POST['kode_barang'] ?? null;
$nama_barang   = $_POST['nama_barang'] ?? null;
$kategori      = $_POST['kategori'] ?? null;
$lokasi        = $_POST['lokasi'] ?? null;
$stok_total    = isset($_POST['stok_total']) ? (int)$_POST['stok_total'] : null;
$safety_stock  = isset($_POST['safety_stock']) ? (int)$_POST['safety_stock'] : 1;
$kondisi       = $_POST['kondisi'] ?? 'Good';
$keterangan    = $_POST['keterangan'] ?? null;

// VALIDASI: stok tidak boleh negatif
if ($stok_total !== null && $stok_total < 0) {
    echo json_encode([
        "status" => false,
        "message" => "Stock cannot be negative"
    ]);
    exit;
}

// VALIDASI: safety stock minimal 1
if ($safety_stock < 1) {
    echo json_encode([
        "status" => false,
        "message" => "Safety Stock must be at least 1"
    ]);
    exit;
}

try {

// ============================
// MODE TAMBAH BARANG
// ============================
$stok_tersedia = $stok_total;

if (!$id) {
    if (!$kode_barang || !$nama_barang) {
        echo json_encode([
            "status" => false,
            "message" => "Item code and name are required"
        ]);
        exit;
    }

    // CEK KODE BARANG DUPLIKAT
    $cek = $conn->prepare("SELECT id FROM barang WHERE kode_barang = ?");
    $cek->bind_param("s", $kode_barang);
    $cek->execute();
    $cek->store_result();

    if ($cek->num_rows > 0) {
        echo json_encode([
            "status" => false,
            "message" => "Item code already in use"
        ]);
        exit;
    }

    // CEK NAMA BARANG DUPLIKAT
    $cek2 = $conn->prepare("SELECT id FROM barang WHERE nama_barang = ?");
    $cek2->bind_param("s", $nama_barang);
    $cek2->execute();
    $cek2->store_result();

    if ($cek2->num_rows > 0) {
        echo json_encode([
            "status" => false,
            "message" => "Item name already in use"
        ]);
        exit;
    }

    // PENTING: stok tersedia = stok total
    $stok_tersedia = $stok_total;

    try {
        $stmt = $conn->prepare("
            INSERT INTO barang
            (kode_barang, nama_barang, kategori, lokasi, stok_total, stok_tersedia, safety_stock, kondisi, keterangan)
            VALUES (?,?,?,?,?,?,?,?,?)
        ");

        if (!$stmt) {
            echo json_encode([
                "status" => false,
                "message" => "Prepare error: " . $conn->error
            ]);
            exit;
        }
    } catch (Exception $e) {
        echo json_encode([
            "status" => false,
            "message" => "Prepare failed: " . $e->getMessage()
        ]);
        exit;
    }

    $stmt->bind_param(
        "ssssiiiss",
        $kode_barang,
        $nama_barang,
        $kategori,
        $lokasi,
        $stok_total,
        $stok_tersedia,
        $safety_stock,
        $kondisi,
        $keterangan
    );

    if ($stmt->execute()) {
        echo json_encode(["status" => true, "message" => "Item data added successfully"]);
    } else {
        $errno = $stmt->errno ?: $conn->errno;
        $error_msg = $stmt->error ?: $conn->error;
        if ($errno == 1062) {
            echo json_encode(["status" => false, "message" => "Duplicate data: code or name already exists", "sql_error" => $error_msg]);
        } else {
            echo json_encode(["status" => false, "message" => "Failed to add item", "error_no" => $errno, "sql_error" => $error_msg]);
        }
    }
    exit;
}

// ============================
// MODE EDIT BARANG
// ============================
$stok_tersedia = $stok_total;

$stmt = $conn->prepare("
    UPDATE barang SET
    kategori = ?,
    lokasi = ?,
    stok_total = ?,
    stok_tersedia = ?,
    safety_stock = ?,
    kondisi = ?,
    keterangan = ?
    WHERE id = ?
");

if (!$stmt) {
    throw new Exception("Prepare error (UPDATE): " . $conn->error);
}

$stmt->bind_param(
    "ssiiissi",
    $kategori,
    $lokasi,
    $stok_total,
    $stok_tersedia,
    $safety_stock,
    $kondisi,
    $keterangan,
    $id
);

if ($stmt->execute()) {
    echo json_encode([
        "status" => true,
        "message" => "Item updated successfully"
    ]);
} else {
    $errno = $stmt->errno ?: $conn->errno;
    $error_msg = $stmt->error ?: $conn->error;
    if ($errno == 1062) {
        echo json_encode(["status" => false, "message" => "Duplicate data during update", "sql_error" => $error_msg]);
    } else {
        echo json_encode(["status" => false, "message" => "Item update failed", "error_no" => $errno, "sql_error" => $error_msg]);
    }
}

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status" => false,
        "message" => "Exception: " . $e->getMessage(),
        "trace" => $e->getTraceAsString()
    ]);
}

// Restore original error handler
restore_error_handler();

exit;
