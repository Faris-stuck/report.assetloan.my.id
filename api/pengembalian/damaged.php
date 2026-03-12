<?php
/**
 * API: List barang rusak dari proses pengembalian
 * Endpoint: /api/pengembalian/damaged.php
 *
 * Query params:
 * - limit (optional) default 50
 * - offset (optional) default 0
 */

require_once "../koneksi.php";
require_once "../session-helper.php";
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    SessionValidator::requireRole(['admin', 'pic_barang']);
} catch (Exception $e) {
    http_response_code(403);
    echo json_encode(["status" => false, "message" => $e->getMessage()]);
    exit;
}

$limit = max(1, (int)($_GET['limit'] ?? 50));
$offset = max(0, (int)($_GET['offset'] ?? 0));

$stmt = $conn->prepare("
    SELECT
        k.kode_pengembalian,
        p.kode_peminjaman,
        p.nama_peminjam,
        p.nrp,
        b.kode_barang,
        b.nama_barang,
        d.jumlah_kembali,
        d.jumlah_rusak,
        d.biaya_ganti_rugi,
        d.catatan,
        k.selesai_at
    FROM detail_pengembalian d
    JOIN pengembalian k ON k.id = d.pengembalian_id
    JOIN peminjaman p ON p.id = k.peminjaman_id
    JOIN barang b ON b.id = d.barang_id
    WHERE k.status = 'Completed' AND d.kondisi_kembali = 'Damaged'
    ORDER BY k.selesai_at DESC
    LIMIT ? OFFSET ?
");
$stmt->bind_param("ii", $limit, $offset);
$stmt->execute();
$res = $stmt->get_result();

$data = [];
while ($row = $res->fetch_assoc()) {
    $row['selesai_at_f'] = $row['selesai_at'] ? date('d/m/Y H:i', strtotime($row['selesai_at'])) : '-';
    $data[] = $row;
}

echo json_encode(["status" => true, "data" => $data]);

