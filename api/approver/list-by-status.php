<?php
require_once "../koneksi.php";
require_once "../session-helper.php";
header('Content-Type: application/json');

try {
    SessionValidator::requireRole(['admin', 'manager']);
    
    $status = $_GET['status'] ?? 'Menunggu Persetujuan';
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
            p.catatan,
            p.lokasi_umum
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
                dp.id AS detail_peminjaman_id,
                dp.barang_id,
                b.nama_barang,
                dp.jumlah,
                dp.lokasi
            FROM detail_peminjaman dp
            LEFT JOIN barang b ON dp.barang_id = b.id
            WHERE dp.peminjaman_id = ?
        ");
        $stmt_detail->bind_param("i", $row['id']);
        $stmt_detail->execute();
        $result_detail = $stmt_detail->get_result();

        $barang_list = [];
        $detail_barang = [];
        while ($detail_row = $result_detail->fetch_assoc()) {
            $barang_list[] = $detail_row['nama_barang'];
            $detail_barang[] = [
                'detail_peminjaman_id' => $detail_row['detail_peminjaman_id'],
                'barang_id' => $detail_row['barang_id'],
                'nama_barang' => $detail_row['nama_barang'],
                'jumlah' => $detail_row['jumlah'],
                'lokasi' => $detail_row['lokasi']
            ];
        }

        // Merge pengembalian details (if any) to surface damaged counts for approvers
        $qk = $conn->prepare("SELECT id FROM pengembalian WHERE peminjaman_id = ? ORDER BY id DESC LIMIT 1");
        $qk->bind_param("i", $row['id']);
        $qk->execute();
        $hk = $qk->get_result()->fetch_assoc();
        if ($hk) {
            $peng_id = (int)$hk['id'];
            $sd = $conn->prepare("SELECT barang_id, jumlah_kembali, kondisi_kembali, jumlah_rusak FROM detail_pengembalian WHERE pengembalian_id = ?");
            $sd->bind_param("i", $peng_id);
            $sd->execute();
            $rd = $sd->get_result();
            $map = [];
            $total_items = 0;
            $total_rusak = 0;
            while ($r = $rd->fetch_assoc()) {
                $map[(int)$r['barang_id']] = $r;
            }
            foreach ($detail_barang as &$bi) {
                $total_items += $bi['jumlah'];
                $bid = (int)$bi['barang_id'];
                if (isset($map[$bid])) {
                    $bi['jumlah_kembali'] = (int)$map[$bid]['jumlah_kembali'];
                    $bi['jumlah_rusak'] = (int)$map[$bid]['jumlah_rusak'];
                    $bi['kondisi_kembali'] = $map[$bid]['kondisi_kembali'];
                    $total_rusak += (int)$map[$bid]['jumlah_rusak'];
                }
            }
            if ($total_rusak > 0) {
                if ($total_rusak < $total_items) {
                    $row['status'] = 'Sebagian Rusak';
                    $row['status_en'] = 'Partially Damaged';
                } else {
                    $row['status'] = 'Semua Rusak';
                    $row['status_en'] = 'Fully Damaged';
                }
            } else {
                $row['status_en'] = $row['status'];
            }
        }

        // REAL-TIME DUE STATUS (use nearest expected return considering extends)
        $nearest_expected = getNearestExpectedReturn($conn, $row['id']);
        $row['status'] = computeDueStatus($row['status'], $nearest_expected ?? $row['rencana_kembali']);

        $data[] = [
            'id' => $row['id'],
            'kode' => $row['kode_peminjaman'],
            'kode_peminjaman' => $row['kode_peminjaman'],
            'nama' => $row['nama_peminjam'],
            'nama_peminjam' => $row['nama_peminjam'],
            'nrp' => $row['nrp'],
            'tanggal' => ($row['tanggal_pinjam'] ? date('d/m/Y', strtotime($row['tanggal_pinjam'])) : '-'),
            'tanggal_pinjam' => ($row['tanggal_pinjam'] ? $row['tanggal_pinjam'] : ''),
            'rencana_kembali' => ($row['rencana_kembali'] ? date('d/m/Y', strtotime($row['rencana_kembali'])) : '-'),
            'expected_return_nearest' => $nearest_expected ? date('d/m/Y', strtotime($nearest_expected)) : ($row['rencana_kembali'] ? date('d/m/Y', strtotime($row['rencana_kembali'])) : '-'),
            'status' => $row['status'],
            'barang' => implode(', ', $barang_list),
            'catatan' => $row['catatan'],
            'lokasi_umum' => $row['lokasi_umum'],
            'detail_barang' => $detail_barang
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
