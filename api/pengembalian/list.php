<?php
/**
 * API: List pengembalian yang Diajukan (Admin/PIC Barang)
 * Endpoint: /api/pengembalian/list.php
 *
 * Query params:
 * - status (optional) default 'Diajukan'
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

$status = $_GET['status'] ?? 'Diajukan';

$stmt = $conn->prepare("
    SELECT
        k.id AS pengembalian_id,
        k.kode_pengembalian,
        k.status AS status_pengembalian,
        k.diajukan_at,
        p.id AS peminjaman_id,
        p.kode_peminjaman,
        p.nama_peminjam,
        p.nrp,
        p.tanggal_pinjam,
        p.rencana_kembali,
        p.status AS status_peminjaman,
        COALESCE(SUM(dp.jumlah_rusak),0) AS total_rusak,
        COALESCE(SUM(dp.jumlah_kembali),0) AS total_kembali
    FROM pengembalian k
    JOIN peminjaman p ON p.id = k.peminjaman_id
    LEFT JOIN detail_pengembalian dp ON dp.pengembalian_id = k.id
    WHERE k.status = ?
    GROUP BY k.id
    ORDER BY k.diajukan_at DESC
" );
$stmt->bind_param("s", $status);
$stmt->execute();
$res = $stmt->get_result();

$data = [];
while ($row = $res->fetch_assoc()) {
    $row['diajukan_at_f'] = date('d/m/Y H:i', strtotime($row['diajukan_at']));
    $row['tanggal_pinjam_f'] = $row['tanggal_pinjam'] ? date('d/m/Y', strtotime($row['tanggal_pinjam'])) : '-';
    $row['rencana_kembali_f'] = $row['rencana_kembali'] ? date('d/m/Y', strtotime($row['rencana_kembali'])) : '-';
    // derive display status based on damaged counts
    $total_rusak = (int)($row['total_rusak'] ?? 0);
    $total_kembali = (int)($row['total_kembali'] ?? 0);
    $display_status = $row['status_pengembalian'];
    $display_status_en = $row['status_pengembalian'];
    if ($total_rusak > 0 && $total_kembali > 0) {
        if ($total_rusak >= $total_kembali) {
            $display_status = 'Semua Rusak';
            $display_status_en = 'Fully Damaged';
        } else {
            $display_status = 'Sebagian Rusak';
            $display_status_en = 'Partially Damaged';
        }
    }
    $row['display_status'] = $display_status;
    $row['display_status_en'] = $display_status_en;
    $row['total_rusak'] = $total_rusak;
    $row['total_kembali'] = $total_kembali;
    $data[] = $row;
}

echo json_encode(["status" => true, "data" => $data]);

