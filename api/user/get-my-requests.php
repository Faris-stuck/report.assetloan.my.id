<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once "../koneksi.php";
header('Content-Type: application/json');
require_once "../session-helper.php";

// Validate user role
try {
    SessionValidator::requireRole(['user']);
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode([
        "status" => false,
        "message" => "Unauthorized: " . $e->getMessage()
    ]);
    exit;
}

$user_id = $_GET['user_id'] ?? 0;

if (!$user_id) {
    echo json_encode([
        "status" => false,
        "message" => "User ID is required"
    ]);
    exit;
}

try {
    $stmt = $conn->prepare("
        SELECT
            p.id,
            p.kode_peminjaman,
            p.user_id,
            p.nama_peminjam,
            p.nrp,
            p.tanggal_pinjam,
            p.rencana_kembali,
            p.tanggal_kembali,
            p.status,
            p.catatan
        FROM peminjaman p
        WHERE p.user_id = ?
        ORDER BY p.tanggal_pinjam DESC
    ");

    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $data = [];
    while ($row = $result->fetch_assoc()) {
        $stmt_detail = $conn->prepare("
            SELECT
                b.id AS barang_id,
                b.nama_barang,
                CASE
                    WHEN (SELECT COUNT(*) FROM peminjaman_units pu WHERE pu.peminjaman_id = dp.peminjaman_id) > 0
                    THEN (SELECT COUNT(*) FROM peminjaman_units pu WHERE pu.detail_peminjaman_id = dp.id AND pu.approval_status = 'Disetujui')
                    ELSE dp.jumlah
                END as jumlah,
                dp.lokasi,
                dp.kondisi_pinjam
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
            $barang_list[] = [
                'barang_id' => (int)$detail_row['barang_id'],
                'nama' => $detail_row['nama_barang'],
                'jumlah' => (int)$detail_row['jumlah'],
                'lokasi' => $detail_row['lokasi'],
                'kondisi' => $detail_row['kondisi_pinjam'],
                // default return inspection fields
                'jumlah_kembali' => 0,
                'jumlah_rusak' => 0,
                'kondisi_kembali' => ''
            ];
        }

        // Initialize status_en
        $row['status_en'] = $row['status'];

        // Merge pengembalian inspection info using AGGREGATE from ALL finalized pengembalian
        $qk = $conn->prepare("SELECT id, status FROM pengembalian WHERE peminjaman_id = ? ORDER BY id DESC LIMIT 1");
        $qk->bind_param("i", $row['id']);
        $qk->execute();
        $hk = $qk->get_result()->fetch_assoc();
        $pengembalian_status = null;
        $has_pengembalian = false;
        
        if ($hk) {
            $has_pengembalian = true;
            $pengembalian_status = $hk['status'];
        }
        
        // Get AGGREGATE return data from ALL finalized pengembalian (status='Selesai')
        $agg_per_barang = $conn->prepare("
            SELECT dr.barang_id, 
                   SUM(dr.jumlah_kembali) as total_kembali, 
                   SUM(dr.jumlah_rusak) as total_rusak,
                   MAX(dr.kondisi_kembali) as kondisi_kembali
            FROM detail_pengembalian dr
            JOIN pengembalian p ON dr.pengembalian_id = p.id
            WHERE p.peminjaman_id = ? AND p.status = 'Selesai'
            GROUP BY dr.barang_id
        ");
        $agg_per_barang->bind_param("i", $row['id']);
        $agg_per_barang->execute();
        $agg_result = $agg_per_barang->get_result();
        $agg_map = [];
        while ($r = $agg_result->fetch_assoc()) {
            $agg_map[(int)$r['barang_id']] = $r;
        }
        
        $total_items = 0;
        $total_kembali_agg = 0;
        $total_rusak_agg = 0;
        foreach ($barang_list as &$bi) {
            $bid = (int)$bi['barang_id'];
            $total_items += $bi['jumlah'];
            if (isset($agg_map[$bid])) {
                $bi['jumlah_kembali'] = (int)$agg_map[$bid]['total_kembali'];
                $bi['jumlah_rusak'] = (int)$agg_map[$bid]['total_rusak'];
                $bi['kondisi_kembali'] = $agg_map[$bid]['kondisi_kembali'];
                $total_kembali_agg += (int)$agg_map[$bid]['total_kembali'];
                $total_rusak_agg += (int)$agg_map[$bid]['total_rusak'];
            }
        }
        unset($bi);
        
        // Compute user-friendly status override based on aggregate data
        if ($total_kembali_agg > 0) {
            $sisa = $total_items - $total_kembali_agg;
            if ($sisa <= 0 && $total_items > 0) {
                // All items returned
                if ($total_rusak_agg > 0) {
                    if ($total_rusak_agg >= $total_items) {
                        $row['status'] = 'Semua Rusak';
                        $row['status_en'] = 'Fully Damaged';
                    } else {
                        $row['status'] = 'Sebagian Rusak';
                        $row['status_en'] = 'Partially Damaged';
                    }
                } else {
                    $row['status'] = 'Dikembalikan';
                    $row['status_en'] = 'Returned';
                }
            } else if ($sisa > 0) {
                // Partial return
                if ($has_pengembalian && in_array($pengembalian_status, ['Diajukan', 'Dicek'])) {
                    $row['status'] = 'Proses Return';
                    $row['status_en'] = 'Return In Progress';
                } else {
                    $row['status'] = 'Sebagian Dikembalikan';
                    $row['status_en'] = 'Partially Returned';
                }
            }
        }

        // REAL-TIME DUE STATUS: Hitung dari nearest expected_return (per-unit data)
        $nearest_expected = getNearestExpectedReturn($conn, $row['id']);
        $expected_for_status = $nearest_expected ?? $row['rencana_kembali'];
        $row['status'] = computeDueStatus($row['status'], $expected_for_status);
        $row['status_en'] = $row['status'];

        // Per-unit expected return (nearest unreturned unit)
        $expectedReturnNearest = $nearest_expected ? date('d/m/Y', strtotime($nearest_expected)) : ($row['rencana_kembali'] ? date('d/m/Y', strtotime($row['rencana_kembali'])) : '-');

        $data[] = [
            'id' => $row['id'],
            'kode_peminjaman' => $row['kode_peminjaman'],
            'user_id' => $row['user_id'],
            'nama_peminjam' => $row['nama_peminjam'],
            'nrp' => $row['nrp'],
            'tanggal_pinjam' => $row['tanggal_pinjam'] ? date('d/m/Y', strtotime($row['tanggal_pinjam'])) : '-',
            'rencana_kembali' => $row['rencana_kembali'] ? date('d/m/Y', strtotime($row['rencana_kembali'])) : '-',
            'expected_return_nearest' => $expectedReturnNearest,
            'tanggal_kembali' => $row['tanggal_kembali'] ? date('d/m/Y', strtotime($row['tanggal_kembali'])) : '-',
            'status' => $row['status'],
            'status_en' => $row['status_en'],
            'catatan' => $row['catatan'],
            'detail_barang' => $barang_list
        ];
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
