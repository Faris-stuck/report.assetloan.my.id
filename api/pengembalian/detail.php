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

// Calculate sudah_dikembalikan: total returned from OTHER completed pengembalian for same peminjaman
$peminjaman_id_for_query = (int)$header['peminjaman_id'];

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
        d.catatan,
        COALESCE((
            SELECT SUM(dk2.jumlah_kembali)
            FROM detail_pengembalian dk2
            JOIN pengembalian k2 ON k2.id = dk2.pengembalian_id
            WHERE k2.peminjaman_id = ?
            AND dk2.barang_id = d.barang_id
            AND k2.status = 'Selesai'
            AND k2.id != d.pengembalian_id
        ), 0) as sudah_dikembalikan_from_completed
    FROM detail_pengembalian d
    JOIN barang b ON b.id = d.barang_id
    LEFT JOIN detail_peminjaman dp ON dp.barang_id = d.barang_id AND dp.peminjaman_id = ?
    WHERE d.pengembalian_id = ?
    ORDER BY b.nama_barang ASC
");
$stmt->bind_param("iii", $peminjaman_id_for_query, $peminjaman_id_for_query, $pengembalian_id);
$stmt->execute();
$items = [];
$r = $stmt->get_result();
while ($row = $r->fetch_assoc()) {
    // Calculate sisa_dikembalikan correctly
    // sisa = max qty that CAN be returned in THIS submission
    // = total_pinjam - what's already returned and finalized (status='Selesai')
    $total_pinjam = (int)$row['jumlah_pinjam'];
    $sudah_dikembalikan_from_completed = (int)$row['sudah_dikembalikan_from_completed'];
    $jumlah_kembali_current = (int)$row['jumlah_kembali'];
    
    // Maximum qty that can be returned in THIS submission (not including already-returned qty)
    // This is the "remain item" to show PIC what they can still accept
    $max_untuk_submission_ini = max(0, $total_pinjam - $sudah_dikembalikan_from_completed);
    
    // After approval, total returned will be = completed + current
    // Remaining items not yet returned = total - (completed + current)
    $akan_sisa_setelah_approval = max(0, $total_pinjam - ($sudah_dikembalikan_from_completed + $jumlah_kembali_current));
    
    // sisa_dikembalikan = max qty that can be in this submission (for "remain item" display)
    $row['sisa_dikembalikan'] = $max_untuk_submission_ini;
    $row['sudah_dikembalikan'] = $sudah_dikembalikan_from_completed;
    
    $items[] = $row;
}

echo json_encode([
    "status" => true,
    "header" => $header,
    "items" => $items
]);

