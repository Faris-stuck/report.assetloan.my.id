<?php
/**
 * API: Pic Barang - Daftar Peminjaman Sedang Dipinjam (untuk pengembalian)
 * Endpoint: /api/pic_barang/pengembalian-list.php
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

$stmt = $conn->prepare("
    SELECT p.id, p.kode_peminjaman, p.nama_peminjam, p.nrp, p.tanggal_pinjam, p.rencana_kembali, p.status, p.catatan
    FROM peminjaman p
    WHERE (p.status = 'Borrowed' OR p.status LIKE 'Due%' OR p.status = 'Overdue' 
           OR p.status = 'Partially Returned' OR p.status = 'Return in Process')
    ORDER BY p.rencana_kembali ASC
");
$stmt->execute();
$result = $stmt->get_result();
$list = [];
while ($row = $result->fetch_assoc()) {
    $row['tanggal_pinjam_f'] = date('d/m/Y', strtotime($row['tanggal_pinjam']));
    $row['rencana_kembali_f'] = date('d/m/Y', strtotime($row['rencana_kembali']));
    // REAL-TIME DUE STATUS (use nearest expected return considering extends)
    $nearest_expected = getNearestExpectedReturn($conn, $row['id']);
    $row['status'] = computeDueStatus($row['status'], $nearest_expected ?? $row['rencana_kembali']);
    $row['expected_return_nearest'] = $nearest_expected ? date('d/m/Y', strtotime($nearest_expected)) : $row['rencana_kembali_f'];
    $list[] = $row;
}

echo json_encode([
    "status" => true,
    "data" => $list
]);
