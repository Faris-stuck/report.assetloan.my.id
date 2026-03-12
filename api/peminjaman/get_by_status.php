<?php
require_once "../koneksi.php";
require_once "../session-helper.php";
header('Content-Type: application/json');

try {
    SessionValidator::requireRole(['admin', 'manager', 'pic_barang']);
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(["status" => false, "message" => "Unauthorized"]);
    exit;
}

$status = $_GET['status'] ?? 'Waiting for Approval';
$include_due = isset($_GET['include_due']) && $_GET['include_due'] === '1';

try {
    // Query untuk mengambil data peminjaman dengan detail
    if ($include_due) {
        // Include all active statuses: Borrowed + Partial Approved + Due% + Overdue
        $stmt = $conn->prepare("
            SELECT
                p.id,
                p.kode_peminjaman,
                p.nama_peminjam,
                p.nrp,
                p.tanggal_pinjam,
                p.rencana_kembali,
                p.status,
                p.catatan
            FROM peminjaman p
            WHERE (p.status IN ('Borrowed','Partial Approved','Overdue','Due Today')
                   OR p.status LIKE 'Due In%')
            ORDER BY p.rencana_kembali ASC
        ");
    } else {
        $stmt = $conn->prepare("
            SELECT
                p.id,
                p.kode_peminjaman,
                p.nama_peminjam,
                p.nrp,
                p.tanggal_pinjam,
                p.rencana_kembali,
                p.status,
                p.catatan
            FROM peminjaman p
            WHERE p.status = ?
            ORDER BY p.tanggal_pinjam DESC
        ");
        $stmt->bind_param("s", $status);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $data = [];
    while ($row = $result->fetch_assoc()) {
        // Ambil detail barang untuk peminjaman ini
        $stmt_detail = $conn->prepare("
            SELECT
                CONCAT(
                    CASE
                        WHEN (SELECT COUNT(*) FROM peminjaman_units pu WHERE pu.peminjaman_id = dp.peminjaman_id) > 0
                        THEN (SELECT COUNT(*) FROM peminjaman_units pu WHERE pu.detail_peminjaman_id = dp.id AND pu.approval_status = 'Approved')
                        ELSE dp.jumlah
                    END,
                    'x ', b.nama_barang, ' (', dp.lokasi, ')'
                ) as item,
                CASE
                    WHEN (SELECT COUNT(*) FROM peminjaman_units pu WHERE pu.peminjaman_id = dp.peminjaman_id) > 0
                    THEN (SELECT COUNT(*) FROM peminjaman_units pu WHERE pu.detail_peminjaman_id = dp.id AND pu.approval_status = 'Approved')
                    ELSE dp.jumlah
                END as jumlah
            FROM detail_peminjaman dp
            LEFT JOIN barang b ON dp.barang_id = b.id
            WHERE dp.peminjaman_id = ?
            HAVING jumlah > 0
        ");
        $stmt_detail->bind_param("i", $row['id']);
        $stmt_detail->execute();
        $result_detail = $stmt_detail->get_result();

        $barang_list = [];
        while ($detail_row = $result_detail->fetch_assoc()) {
            $barang_list[] = $detail_row['item'];
        }

        // REAL-TIME DUE STATUS: Hitung dari nearest expected_return (per-unit data)
        $nearest_expected = getNearestExpectedReturn($conn, $row['id']);
        $expected_for_status = $nearest_expected ?? $row['rencana_kembali'];
        $realTimeStatus = computeDueStatus($row['status'], $expected_for_status);

        $data[] = [
            'id' => $row['id'],
            'kode' => $row['kode_peminjaman'],
            'nama' => $row['nama_peminjam'],
            'nrp' => $row['nrp'],
            'tanggal' => ($row['tanggal_pinjam'] ? date('d/m/Y', strtotime($row['tanggal_pinjam'])) : '-'),
            'rencana_kembali' => ($row['rencana_kembali'] ? date('d/m/Y', strtotime($row['rencana_kembali'])) : '-'),
            'expected_return_nearest' => $nearest_expected ? date('d/m/Y', strtotime($nearest_expected)) : ($row['rencana_kembali'] ? date('d/m/Y', strtotime($row['rencana_kembali'])) : '-'),
            'status' => $realTimeStatus,
            'barang' => implode(', ', $barang_list),
            'catatan' => $row['catatan']
        ];
    }

    echo json_encode([
        "status" => true,
        "data" => $data
    ]);

} catch (Exception $e) {
    echo json_encode([
        "status" => false,
        "message" => $e->getMessage()
    ]);
}
?>