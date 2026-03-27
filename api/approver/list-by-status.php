<?php
require_once "../koneksi.php";
require_once "../session-helper.php";
header('Content-Type: application/json');

try {
    SessionValidator::requireRole(['admin', 'manager']);
    
    $status = $_GET['status'] ?? 'Waiting for Approval';
    $include_due = isset($_GET['include_due']) && $_GET['include_due'] === '1';
    $approval_history = isset($_GET['approval_history']) && $_GET['approval_history'] === '1';

    // Query untuk mengambil data peminjaman dengan detail
    if ($approval_history) {
        // Manager approval history view:
        // include requests that have at least one unit/detail approved by manager,
        // regardless of current runtime status (due/overdue/returned/etc).
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
            WHERE (
                EXISTS (
                    SELECT 1
                    FROM peminjaman_units pu
                    JOIN users u ON u.id = pu.approved_by
                    WHERE pu.peminjaman_id = p.id
                      AND pu.approval_status = 'Approved'
                      AND u.role = 'manager'
                )
                OR (
                    NOT EXISTS (SELECT 1 FROM peminjaman_units pu2 WHERE pu2.peminjaman_id = p.id)
                    AND EXISTS (
                        SELECT 1
                        FROM detail_peminjaman dp
                        JOIN users u2 ON u2.id = dp.approved_by
                        WHERE dp.peminjaman_id = p.id
                          AND dp.approval_status = 'approved'
                          AND u2.role = 'manager'
                    )
                )
            )
            ORDER BY p.tanggal_pinjam DESC, p.id DESC
        ");
    } elseif ($include_due) {
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
                p.catatan,
                p.lokasi_umum
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
                p.catatan,
                p.lokasi_umum
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
                dp.id AS detail_peminjaman_id,
                dp.barang_id,
                b.nama_barang,
                CASE
                    WHEN (SELECT COUNT(*) FROM peminjaman_units pu WHERE pu.peminjaman_id = dp.peminjaman_id) > 0
                    THEN (SELECT COUNT(*) FROM peminjaman_units pu WHERE pu.detail_peminjaman_id = dp.id AND pu.approval_status = 'Approved')
                    ELSE dp.jumlah
                END as jumlah,
                dp.lokasi
            FROM detail_peminjaman dp
            LEFT JOIN barang b ON dp.barang_id = b.id
            WHERE dp.peminjaman_id = ?
            HAVING jumlah > 0
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

        if ($approval_history) {
            // Force history-only status label:
            // - Approved: all reviewed units approved
            // - Partial Approved: mix approved with rejected/pending units
            $approved_count = 0;
            $rejected_count = 0;
            $pending_count = 0;
            $unit_total = 0;

            $sq = $conn->prepare("
                SELECT
                    SUM(CASE WHEN pu.approval_status = 'Approved' THEN 1 ELSE 0 END) AS approved_count,
                    SUM(CASE WHEN pu.approval_status = 'Rejected' THEN 1 ELSE 0 END) AS rejected_count,
                    SUM(CASE WHEN pu.approval_status = 'Pending' OR pu.approval_status IS NULL THEN 1 ELSE 0 END) AS pending_count,
                    COUNT(*) AS unit_total
                FROM peminjaman_units pu
                WHERE pu.peminjaman_id = ?
            ");
            $sq->bind_param("i", $row['id']);
            $sq->execute();
            $sum = $sq->get_result()->fetch_assoc();
            if ($sum) {
                $approved_count = (int)($sum['approved_count'] ?? 0);
                $rejected_count = (int)($sum['rejected_count'] ?? 0);
                $pending_count = (int)($sum['pending_count'] ?? 0);
                $unit_total = (int)($sum['unit_total'] ?? 0);
            }

            // Fallback for legacy records without peminjaman_units
            if ($unit_total === 0) {
                $sq2 = $conn->prepare("
                    SELECT
                        SUM(CASE WHEN dp.approval_status = 'approved' THEN 1 ELSE 0 END) AS approved_count,
                        SUM(CASE WHEN dp.approval_status = 'rejected' THEN 1 ELSE 0 END) AS rejected_count,
                        SUM(CASE WHEN dp.approval_status = 'pending' OR dp.approval_status IS NULL THEN 1 ELSE 0 END) AS pending_count,
                        COUNT(*) AS unit_total
                    FROM detail_peminjaman dp
                    WHERE dp.peminjaman_id = ?
                ");
                $sq2->bind_param("i", $row['id']);
                $sq2->execute();
                $sum2 = $sq2->get_result()->fetch_assoc();
                if ($sum2) {
                    $approved_count = (int)($sum2['approved_count'] ?? 0);
                    $rejected_count = (int)($sum2['rejected_count'] ?? 0);
                    $pending_count = (int)($sum2['pending_count'] ?? 0);
                    $unit_total = (int)($sum2['unit_total'] ?? 0);
                }
            }

            // Safety: if somehow no approved items, skip from history result
            if ($approved_count <= 0) {
                continue;
            }

            $is_partial = ($rejected_count > 0 || $pending_count > 0);
            $row['status'] = $is_partial ? 'Partial Approved' : 'Approved';
            $row['status_en'] = $row['status'];
            $nearest_expected = getNearestExpectedReturn($conn, $row['id']);
        } else {
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
                        $row['status'] = 'Partially Damaged';
                        $row['status_en'] = 'Partially Damaged';
                    } else {
                        $row['status'] = 'Fully Damaged';
                        $row['status_en'] = 'Fully Damaged';
                    }
                } else {
                    $row['status_en'] = $row['status'];
                }
            }

            // REAL-TIME DUE STATUS (use nearest expected return considering extends)
            $nearest_expected = getNearestExpectedReturn($conn, $row['id']);
            $row['status'] = computeDueStatus($row['status'], $nearest_expected ?? $row['rencana_kembali']);
        }

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
