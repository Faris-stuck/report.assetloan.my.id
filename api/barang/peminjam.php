<?php
require_once "../koneksi.php";
header('Content-Type: application/json');

$id_barang = $_GET['id_barang'] ?? 0;

if (!$id_barang) {
    echo json_encode([
        "status" => false,
        "message" => "Item ID is required"
    ]);
    exit;
}

try {
    // Query: count approved units from peminjaman_units per peminjaman.
    // - Only loans approved by Manager (exclude Menunggu Persetujuan, Ditolak)
    // - Only units still actively borrowed (exclude returned/damaged)
    $stmt = $conn->prepare("
        SELECT
            p.id as peminjaman_id,
            p.kode_peminjaman,
            p.nama_peminjam,
            p.nrp,
            COUNT(pu.id) AS jumlah,
            p.tanggal_pinjam,
            p.rencana_kembali,
            p.status,
            dp.kondisi_pinjam,
            dp.lokasi
        FROM peminjaman p
        JOIN detail_peminjaman dp ON p.id = dp.peminjaman_id
        JOIN peminjaman_units pu ON pu.peminjaman_id = p.id
            AND pu.barang_id = dp.barang_id
        WHERE dp.barang_id = ?
          AND p.status NOT IN ('Menunggu Persetujuan', 'Ditolak')
          AND pu.approval_status = 'Disetujui'
          AND pu.return_status NOT IN ('Dikembalikan', 'Rusak')
        GROUP BY p.id, p.kode_peminjaman, p.nama_peminjam, p.nrp,
                 p.tanggal_pinjam, p.rencana_kembali, p.status,
                 dp.kondisi_pinjam, dp.lokasi
        ORDER BY p.tanggal_pinjam DESC
    ");

    $stmt->bind_param("i", $id_barang);
    $stmt->execute();
    $result = $stmt->get_result();

    $data = [];
    while ($row = $result->fetch_assoc()) {
        $nearest_expected = getNearestExpectedReturn($conn, $row['peminjaman_id']);
        $data[] = [
            'peminjaman_id' => $row['peminjaman_id'],
            'kode_peminjaman' => $row['kode_peminjaman'],
            'nama_peminjam' => $row['nama_peminjam'] ?: '-',
            'nrp' => $row['nrp'] ?: '-',
            'jumlah' => $row['jumlah'],
            'tanggal_pinjam' => date('d/m/Y', strtotime($row['tanggal_pinjam'])),
            'rencana_kembali' => date('d/m/Y', strtotime($row['rencana_kembali'])),
            'expected_return_nearest' => $nearest_expected ? date('d/m/Y', strtotime($nearest_expected)) : date('d/m/Y', strtotime($row['rencana_kembali'])),
            'status' => computeDueStatus($row['status'], $nearest_expected ?? $row['rencana_kembali']),
            'kondisi_pinjam' => $row['kondisi_pinjam'] ?: '-',
            'lokasi' => $row['lokasi'] ?: '-'
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