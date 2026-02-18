<?php
require_once "../koneksi.php";
header('Content-Type: application/json');

// Parameters for filtering
$user_id = $_GET['user_id'] ?? null;
$start_date = $_GET['start_date'] ?? null;
$end_date = $_GET['end_date'] ?? null;

try {
    $where_clause = "";
    $params = [];
    $types = "";

    if ($user_id) {
        $where_clause = "WHERE p.user_id = ?";
        $params[] = $user_id;
        $types = "i";
    }

    // Add date range filtering
    if ($start_date && $end_date) {
        if ($where_clause) {
            $where_clause .= " AND DATE(p.tanggal_pinjam) >= ? AND DATE(p.tanggal_pinjam) <= ?";
        } else {
            $where_clause = "WHERE DATE(p.tanggal_pinjam) >= ? AND DATE(p.tanggal_pinjam) <= ?";
        }
        $params[] = $start_date;
        $params[] = $end_date;
        $types .= "ss";
    }

    // Query untuk mengambil semua data peminjaman dengan detail
    $stmt = $conn->prepare("
        SELECT
            p.id,
            p.kode_peminjaman,
            p.user_id,
            p.nama_peminjam,
            p.nrp,
            p.tanggal_pinjam,
            p.rencana_kembali,
            p.status,
            p.catatan
        FROM peminjaman p
        $where_clause
        ORDER BY p.tanggal_pinjam DESC
    ");

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $data = [];
    while ($row = $result->fetch_assoc()) {
        // Ambil detail barang untuk peminjaman ini
        $stmt_detail = $conn->prepare("
            SELECT
                dp.barang_id as barang_id,
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
        while ($detail_row = $result_detail->fetch_assoc()) {
            $barang_list[] = [
                'barang_id' => (int)$detail_row['barang_id'],
                'nama' => $detail_row['nama_barang'],
                'jumlah' => (int)$detail_row['jumlah'],
                'lokasi' => $detail_row['lokasi'],
                'jumlah_kembali' => 0,
                'jumlah_rusak' => 0,
                'kondisi_kembali' => ''
            ];
        }

        // Attach latest pengembalian inspection details if any
        $qk = $conn->prepare("SELECT id, status FROM pengembalian WHERE peminjaman_id = ? ORDER BY id DESC LIMIT 1");
        $qk->bind_param("i", $row['id']);
        $qk->execute();
        $hk = $qk->get_result()->fetch_assoc();
        $pengembalian_status = null;
        $has_pengembalian = false;
        if ($hk) {
            $has_pengembalian = true;
            $peng_id = (int)$hk['id'];
            $pengembalian_status = $hk['status'];
            $sd = $conn->prepare("SELECT barang_id, jumlah_kembali, kondisi_kembali, jumlah_rusak FROM detail_pengembalian WHERE pengembalian_id = ?");
            $sd->bind_param("i", $peng_id);
            $sd->execute();
            $rd = $sd->get_result();
            $map = [];
            while ($r = $rd->fetch_assoc()) {
                $map[(int)$r['barang_id']] = $r;
            }
            $total_items = 0;
            $total_rusak = 0;
            foreach ($barang_list as &$bi) {
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

        $data[] = [
            'id' => $row['id'],
            'kode' => $row['kode_peminjaman'],
            'user_id' => $row['user_id'],
            'nama' => $row['nama_peminjam'] ?: '-',
            'nrp' => $row['nrp'] ?: '-',
            'tanggal' => ($row['tanggal_pinjam'] ? date('d/m/Y', strtotime($row['tanggal_pinjam'])) : '-'),
            'rencana_kembali' => ($row['rencana_kembali'] ? date('d/m/Y', strtotime($row['rencana_kembali'])) : '-'),
            'status' => $row['status'],
            'barang' => implode(', ', array_map(function($x){ return $x['jumlah'] . 'x ' . $x['nama'] . ' (' . $x['lokasi'] . ')'; }, $barang_list)),
            'catatan' => $row['catatan'] ?: ''
        ];
        // Include pengembalian status and flag
        if (isset($pengembalian_status)) $data[count($data)-1]['pengembalian_status'] = $pengembalian_status;
        $data[count($data)-1]['has_pengembalian'] = $has_pengembalian;
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