<?php
/**
 * API: Detail pengembalian (items) untuk inspeksi
 * Endpoint: /api/pengembalian/detail.php
 *
 * Query params:
 * - pengembalian_id (int) required
 */

require_once "../koneksi.php";
require_once "../session-helper.php";
header('Content-Type: application/json');

try {
    SessionValidator::requireRole(['admin', 'pic_barang']);
} catch (Exception $e) {
    http_response_code(403);
    echo json_encode(["status" => false, "message" => $e->getMessage()]);
    exit;
}

$pengembalian_id = (int)($_GET['pengembalian_id'] ?? 0);
if (!$pengembalian_id) {
    http_response_code(400);
    echo json_encode(["status" => false, "message" => "pengembalian_id wajib"]);
    exit;
}

$h = $conn->prepare("
    SELECT k.*, p.kode_peminjaman, p.nama_peminjam, p.nrp
    FROM pengembalian k
    JOIN peminjaman p ON p.id = k.peminjaman_id
    WHERE k.id = ?
    LIMIT 1
");
$h->bind_param("i", $pengembalian_id);
$h->execute();
$header = $h->get_result()->fetch_assoc();
if (!$header) {
    http_response_code(404);
    echo json_encode(["status" => false, "message" => "Data pengembalian tidak ditemukan"]);
    exit;
}

$stmt = $conn->prepare("
    SELECT
        d.id,
        d.barang_id,
        b.kode_barang,
        b.nama_barang,
        COALESCE(dp.jumlah, 0) as jumlah_pinjam,
        d.jumlah_kembali,
        d.kondisi_kembali,
        d.jumlah_rusak,
        d.biaya_ganti_rugi,
        d.catatan
    FROM detail_pengembalian d
    JOIN barang b ON b.id = d.barang_id
    LEFT JOIN detail_peminjaman dp ON dp.barang_id = d.barang_id AND dp.peminjaman_id = ?
    WHERE d.pengembalian_id = ?
    ORDER BY b.nama_barang ASC
");
$stmt->bind_param("ii", $header['peminjaman_id'], $pengembalian_id);
$stmt->execute();
$items = [];
$r = $stmt->get_result();
while ($row = $r->fetch_assoc()) {
    $items[] = $row;
}

echo json_encode([
    "status" => true,
    "header" => $header,
    "items" => $items
]);

