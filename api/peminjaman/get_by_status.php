<?php
require_once "../koneksi.php";
header('Content-Type: application/json');

$status = $_GET['status'] ?? 'Menunggu Persetujuan';

try {
    // Query untuk mengambil data peminjaman dengan detail
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
    $stmt->execute();
    $result = $stmt->get_result();

    $data = [];
    while ($row = $result->fetch_assoc()) {
        // Ambil detail barang untuk peminjaman ini
        $stmt_detail = $conn->prepare("
            SELECT
                CONCAT(dp.jumlah, 'x ', b.nama_barang, ' (', dp.lokasi, ')') as item
            FROM detail_peminjaman dp
            LEFT JOIN barang b ON dp.barang_id = b.id
            WHERE dp.peminjaman_id = ?
        ");
        $stmt_detail->bind_param("i", $row['id']);
        $stmt_detail->execute();
        $result_detail = $stmt_detail->get_result();

        $barang_list = [];
        while ($detail_row = $result_detail->fetch_assoc()) {
            $barang_list[] = $detail_row['item'];
        }

        $data[] = [
            'id' => $row['id'],
            'kode' => $row['kode_peminjaman'],
            'nama' => $row['nama_peminjam'],
            'nrp' => $row['nrp'],
            'tanggal' => ($row['tanggal_pinjam'] ? date('d/m/Y', strtotime($row['tanggal_pinjam'])) : '-'),
            'rencana_kembali' => ($row['rencana_kembali'] ? date('d/m/Y', strtotime($row['rencana_kembali'])) : '-'),
            'status' => $row['status'],
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