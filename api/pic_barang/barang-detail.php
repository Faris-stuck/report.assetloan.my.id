<?php
/**
 * API: Pic Barang - Detail Barang by ID
 * Endpoint: /api/pic_barang/barang-detail.php?id=
 */

require_once "../koneksi.php";
require_once "../session-helper.php";
header('Content-Type: application/json');

try {
    SessionValidator::requireRole(['pic_barang']);
} catch (Exception $e) {
    http_response_code(403);
    echo json_encode(["status" => false, "message" => $e->getMessage()]);
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    echo json_encode(["status" => false, "message" => "Invalid ID"]);
    exit;
}

$stmt = $conn->prepare("
  SELECT id, kode_barang, nama_barang, kategori, lokasi, stok_total, stok_tersedia, safety_stock, kondisi, keterangan, created_at
  FROM barang WHERE id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    echo json_encode(["status" => true, "data" => $row]);
} else {
    http_response_code(404);
    echo json_encode(["status" => false, "message" => "Item not found"]);
}
