<?php
require_once "../koneksi.php";
header('Content-Type: application/json');

// Parameters for filtering
$user_id = $_GET['user_id'] ?? null;
$peminjaman_id = $_GET['id'] ?? null;
$kode_peminjaman = $_GET['kode'] ?? null;
$start_date = $_GET['start_date'] ?? null;
$end_date = $_GET['end_date'] ?? null;

try {
    $where_clause = "";
    $params = [];
    $types = "";

    // Filter by peminjaman ID
    if ($peminjaman_id) {
        $where_clause = "WHERE p.id = ?";
        $params[] = (int)$peminjaman_id;
        $types = "i";
    }
    
    // Filter by kode_peminjaman
    if ($kode_peminjaman) {
        if ($where_clause) {
            $where_clause .= " AND p.kode_peminjaman = ?";
        } else {
            $where_clause = "WHERE p.kode_peminjaman = ?";
        }
        $params[] = $kode_peminjaman;
        $types .= "s";
    }

    if ($user_id) {
        if ($where_clause) {
            $where_clause .= " AND p.user_id = ?";
        } else {
            $where_clause = "WHERE p.user_id = ?";
        }
        $params[] = (int)$user_id;
        $types .= "i";
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

        // Aggregate returns from ALL pengembalian records for this peminjaman
        // Calculate total returned and damaged across all submissions
        $agg = $conn->prepare("
            SELECT 
                SUM(dp.jumlah_kembali) as total_kembali,
                SUM(dp.jumlah_rusak) as total_rusak,
                SUM(CASE WHEN p.status = 'Selesai' THEN 1 ELSE 0 END) as has_selesai
            FROM detail_pengembalian dp
            JOIN pengembalian p ON dp.pengembalian_id = p.id
            WHERE p.peminjaman_id = ?
        ");
        $agg->bind_param("i", $row['id']);
        $agg->execute();
        $agg_result = $agg->get_result()->fetch_assoc();
        
        $total_items = 0;
        $total_kembali = 0;
        $total_rusak = 0;
        $has_selesai = 0;
        
        // Sum all detail_peminjaman quantities
        foreach ($barang_list as $bi) {
            $total_items += (int)$bi['jumlah'];
        }
        
        if ($agg_result) {
            $total_kembali = (int)($agg_result['total_kembali'] ?? 0);
            $total_rusak = (int)($agg_result['total_rusak'] ?? 0);
            $has_selesai = (int)($agg_result['has_selesai'] ?? 0);
        }
        
        // Get detail for latest pengembalian for display purposes
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
            
            // Get detail from latest pengembalian for display in barang_list
            $sd = $conn->prepare("SELECT barang_id, jumlah_kembali, kondisi_kembali, jumlah_rusak FROM detail_pengembalian WHERE pengembalian_id = ?");
            $sd->bind_param("i", $peng_id);
            $sd->execute();
            $rd = $sd->get_result();
            $map = [];
            while ($r = $rd->fetch_assoc()) {
                $map[(int)$r['barang_id']] = $r;
            }
            foreach ($barang_list as &$bi) {
                $bid = (int)$bi['barang_id'];
                if (isset($map[$bid])) {
                    $bi['jumlah_kembali'] = (int)$map[$bid]['jumlah_kembali'];
                    $bi['jumlah_rusak'] = (int)$map[$bid]['jumlah_rusak'];
                    $bi['kondisi_kembali'] = $map[$bid]['kondisi_kembali'];
                }
            }
        }
        
        // STATUS CALCULATION: Use aggregate totals to determine accurate status
        if ($total_kembali >= $total_items && $total_items > 0) {
            // All items have been returned
            if ($total_rusak > 0) {
                $row['status'] = ($total_rusak >= $total_items) ? 'Semua Rusak' : 'Sebagian Rusak';
                $row['status_en'] = ($total_rusak >= $total_items) ? 'Fully Damaged' : 'Partially Damaged';
            } else {
                $row['status'] = 'Dikembalikan';
                $row['status_en'] = 'Returned';
            }
        } else if ($total_kembali > 0 && $total_kembali < $total_items) {
            // Partial return
            $row['status'] = 'Sebagian Dikembalikan';
            $row['status_en'] = 'Partially Returned';
        } else {
            // No items returned yet
            if (!isset($row['status_en'])) {
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