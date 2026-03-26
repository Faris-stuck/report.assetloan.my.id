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

$pengembalian_id = (int)($_GET['pengembalian_id'] ?? 0);
if (!$pengembalian_id) {
    http_response_code(400);
    echo json_encode(["status" => false, "message" => "pengembalian_id is required"]);
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
    echo json_encode(["status" => false, "message" => "Return data not found"]);
    exit;
}

// Calculate sudah_dikembalikan: total returned from OTHER completed pengembalian for same peminjaman
$peminjaman_id_for_query = (int)$header['peminjaman_id'];

$stmt = $conn->prepare("
    SELECT
        d.id,
        d.barang_id,
        b.kode_barang,
        b.nama_barang,
        COALESCE((
            SELECT COUNT(pu.id)
            FROM peminjaman_units pu
            WHERE pu.peminjaman_id = ?
              AND pu.barang_id = d.barang_id
              AND (pu.approval_status IS NULL OR pu.approval_status != 'Rejected')
        ), 0) AS jumlah_pinjam,
        d.jumlah_kembali,
        d.kondisi_kembali,
        d.jumlah_rusak,
        d.biaya_ganti_rugi,
        d.catatan,
        COALESCE((
            SELECT COUNT(pu2.id)
            FROM peminjaman_units pu2
            WHERE pu2.peminjaman_id = ?
              AND pu2.barang_id = d.barang_id
              AND pu2.return_status IN ('Returned', 'Damaged')
              AND (pu2.approval_status IS NULL OR pu2.approval_status != 'Rejected')
        ), 0) AS sudah_dikembalikan_from_units
    FROM detail_pengembalian d
    JOIN barang b ON b.id = d.barang_id
    WHERE d.pengembalian_id = ?
    ORDER BY b.nama_barang ASC
");
$stmt->bind_param("iii", $peminjaman_id_for_query, $peminjaman_id_for_query, $pengembalian_id);
$stmt->execute();
$items = [];
$r = $stmt->get_result();
while ($row = $r->fetch_assoc()) {
    $total_pinjam = (int)$row['jumlah_pinjam'];
    $jumlah_kembali_current = (int)$row['jumlah_kembali'];
    
    // sudah_dikembalikan = units already returned (from peminjaman_units)
    // But exclude the ones being returned in THIS submission
    // We need: units returned in OTHER completed returns, not this one
    // Use peminjaman_units returned count minus current submission's return qty
    $total_returned_units = (int)$row['sudah_dikembalikan_from_units'];
    
    // sisa_dikembalikan = approved units still active (not yet returned)
    $sisa = max(0, $total_pinjam - $total_returned_units);
    
    $row['jumlah_pinjam'] = $total_pinjam;
    $row['sisa_dikembalikan'] = $sisa;
    $row['sudah_dikembalikan'] = $total_returned_units;
    
    $items[] = $row;
}

echo json_encode([
    "status" => true,
    "header" => $header,
    "items" => $items
]);

