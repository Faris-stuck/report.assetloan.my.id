<?php
/**
 * API: Pic Barang - Update Barang (hanya role pic_barang, hanya edit)
 * Endpoint: /api/pic_barang/barang-update.php
 */

session_start();
require_once "../koneksi.php";
require_once "../session-helper.php";
header("Content-Type: application/json");

try {
    SessionValidator::requireRole(['pic_barang']);
} catch (Exception $e) {
    http_response_code(403);
    echo json_encode(["status" => false, "message" => $e->getMessage()]);
    exit;
}

$id             = $_POST['id'] ?? null;
$kategori       = $_POST['kategori'] ?? null;
$lokasi         = $_POST['lokasi'] ?? null;
$stok_total     = isset($_POST['stok_total']) ? (int)$_POST['stok_total'] : null;
$stok_tersedia  = isset($_POST['stok_tersedia']) ? (int)$_POST['stok_tersedia'] : null;
$safety_stock   = isset($_POST['safety_stock']) ? (int)$_POST['safety_stock'] : 1;
$kondisi        = $_POST['kondisi'] ?? 'Good';
$keterangan     = $_POST['keterangan'] ?? null;

if (!$id) {
    echo json_encode(["status" => false, "message" => "Item ID is required"]);
    exit;
}
if ($stok_tersedia === null) {
    $stok_tersedia = $stok_total !== null ? $stok_total : 0;
}
if ($stok_total === null) {
    $stok_total = $stok_tersedia;
}

$stmt = $conn->prepare("
    UPDATE barang SET
    kategori = ?, lokasi = ?, stok_total = ?, stok_tersedia = ?, safety_stock = ?, kondisi = ?, keterangan = ?
    WHERE id = ?
");
if (!$stmt) {
    echo json_encode(["status" => false, "message" => "Prepare error: " . $conn->error]);
    exit;
}

$stmt->bind_param("ssiiissi", $kategori, $lokasi, $stok_total, $stok_tersedia, $safety_stock, $kondisi, $keterangan, $id);

if ($stmt->execute()) {
    echo json_encode(["status" => true, "message" => "Item updated successfully"]);
} else {
    echo json_encode(["status" => false, "message" => "Item update failed: " . $stmt->error]);
}
