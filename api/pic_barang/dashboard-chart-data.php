<?php
/**
 * API: PIC Barang Dashboard Chart Data
 * Returns same data structure as admin/dashboard-stats.php
 * Supports: ?kategori=X  |  ?tanggal_awal=YYYY-MM-DD&tanggal_akhir=YYYY-MM-DD
 * Endpoint: /api/pic_barang/dashboard-chart-data.php
 */
require_once "../koneksi.php";
require_once "../session-helper.php";
header('Content-Type: application/json');

try {
    SessionValidator::requireRole(['pic_barang']);

    $data = [];

    // Optional filters
    $tanggal_awal  = isset($_GET['tanggal_awal'])  ? trim($_GET['tanggal_awal'])  : null;
    $tanggal_akhir = isset($_GET['tanggal_akhir']) ? trim($_GET['tanggal_akhir']) : null;
    $kategori      = isset($_GET['kategori'])      ? trim($_GET['kategori'])      : null;

    // ── 1. Status peminjaman counts (with optional date range) ───────────────
    if ($tanggal_awal && $tanggal_akhir) {
        $stmt = $conn->prepare("
            SELECT
                SUM(CASE WHEN status = 'Menunggu Persetujuan' THEN 1 ELSE 0 END) AS menunggu,
                SUM(CASE WHEN status IN ('Sedang Dipinjam','Proses Return','Sebagian Dikembalikan')
                          OR status LIKE 'Due%' OR status = 'Overdue' THEN 1 ELSE 0 END) AS dipinjam,
                SUM(CASE WHEN status = 'Dikembalikan' THEN 1 ELSE 0 END) AS dikembalikan,
                SUM(CASE WHEN status = 'Ditolak'      THEN 1 ELSE 0 END) AS ditolak
            FROM peminjaman
            WHERE tanggal_pinjam BETWEEN ? AND ?
        ");
        $stmt->bind_param('ss', $tanggal_awal, $tanggal_akhir);
    } else {
        $stmt = $conn->prepare("
            SELECT
                SUM(CASE WHEN status = 'Menunggu Persetujuan' THEN 1 ELSE 0 END) AS menunggu,
                SUM(CASE WHEN status IN ('Sedang Dipinjam','Proses Return','Sebagian Dikembalikan')
                          OR status LIKE 'Due%' OR status = 'Overdue' THEN 1 ELSE 0 END) AS dipinjam,
                SUM(CASE WHEN status = 'Dikembalikan' THEN 1 ELSE 0 END) AS dikembalikan,
                SUM(CASE WHEN status = 'Ditolak'      THEN 1 ELSE 0 END) AS ditolak
            FROM peminjaman
        ");
    }
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    $data['menunggu_persetujuan'] = (int)($row['menunggu']    ?? 0);
    $data['sedang_dipinjam']      = (int)($row['dipinjam']    ?? 0);
    $data['dikembalikan']         = (int)($row['dikembalikan'] ?? 0);
    $data['ditolak']              = (int)($row['ditolak']      ?? 0);

    // per_status: DATEDIFF-based due-date classification for the Borrowing Status chart
    // All non-final statuses bucketed by due-date proximity using nearest expected_return from peminjaman_units.
    $_dueCase = "CASE
            WHEN p.status = 'Menunggu Persetujuan' THEN 'Pending Approval'
            WHEN p.status = 'Ditolak' THEN 'Rejected'
            WHEN p.status IN ('Dikembalikan','Sebagian Rusak','Semua Rusak','Selesai') THEN 'Returned'
            WHEN DATEDIFF(COALESCE(pu_near.nearest_return, p.rencana_kembali), CURDATE()) < 0  THEN 'Overdue'
            WHEN DATEDIFF(COALESCE(pu_near.nearest_return, p.rencana_kembali), CURDATE()) = 0  THEN 'Due Today'
            WHEN DATEDIFF(COALESCE(pu_near.nearest_return, p.rencana_kembali), CURDATE()) = 1  THEN 'Due Tomorrow'
            WHEN DATEDIFF(COALESCE(pu_near.nearest_return, p.rencana_kembali), CURDATE()) BETWEEN 2 AND 7
                THEN CONCAT('Due ', DATEDIFF(COALESCE(pu_near.nearest_return, p.rencana_kembali), CURDATE()), ' Day')
            WHEN DATEDIFF(COALESCE(pu_near.nearest_return, p.rencana_kembali), CURDATE()) > 7
                THEN CONCAT('Due In ', DATEDIFF(COALESCE(pu_near.nearest_return, p.rencana_kembali), CURDATE()), ' Days')
            ELSE p.status
        END";
    $_joinPuNear = "LEFT JOIN (
            SELECT peminjaman_id, MIN(expected_return) AS nearest_return
            FROM peminjaman_units
            WHERE return_status NOT IN ('Dikembalikan','Rusak','Ditolak')
            GROUP BY peminjaman_id
        ) pu_near ON p.id = pu_near.peminjaman_id";
    if ($tanggal_awal && $tanggal_akhir) {
        $stmt = $conn->prepare("
            SELECT status_label AS status, COUNT(*) AS total
            FROM (SELECT {$_dueCase} AS status_label FROM peminjaman p {$_joinPuNear} WHERE p.tanggal_pinjam BETWEEN ? AND ?) t
            GROUP BY status_label ORDER BY total DESC
        ");
        $stmt->bind_param('ss', $tanggal_awal, $tanggal_akhir);
    } else {
        $stmt = $conn->prepare("
            SELECT status_label AS status, COUNT(*) AS total
            FROM (SELECT {$_dueCase} AS status_label FROM peminjaman p {$_joinPuNear}) t
            GROUP BY status_label ORDER BY total DESC
        ");
    }
    $stmt->execute();
    $perStatusResult = $stmt->get_result();
    $per_status = [];
    while ($r = $perStatusResult->fetch_assoc()) {
        $per_status[] = ['status' => $r['status'], 'total' => (int)$r['total']];
    }
    $data['per_status'] = $per_status;

    // ── 2. Distinct categories for dropdown filter ───────────────────────────
    $stmt = $conn->prepare("
        SELECT DISTINCT kategori FROM barang
        WHERE kategori IS NOT NULL AND kategori <> ''
        ORDER BY kategori ASC
    ");
    $stmt->execute();
    $catResult = $stmt->get_result();
    $categories = [];
    while ($r = $catResult->fetch_assoc()) {
        $categories[] = $r['kategori'];
    }
    $data['categories'] = $categories;

    // ── 3. Top 10 barang dipinjam (optional kategori filter, includes stok) ──
    if ($kategori && $kategori !== 'all') {
        $stmt = $conn->prepare("
            SELECT b.nama_barang AS nama,
                   COUNT(dp.id)  AS jumlah_peminjaman,
                   b.stok_tersedia
            FROM detail_peminjaman dp
            JOIN barang b ON b.id = dp.barang_id
            WHERE b.kategori = ?
            GROUP BY dp.barang_id, b.nama_barang, b.stok_tersedia
            ORDER BY jumlah_peminjaman DESC
            LIMIT 10
        ");
        $stmt->bind_param('s', $kategori);
    } else {
        $stmt = $conn->prepare("
            SELECT b.nama_barang AS nama,
                   COUNT(dp.id)  AS jumlah_peminjaman,
                   b.stok_tersedia
            FROM detail_peminjaman dp
            JOIN barang b ON b.id = dp.barang_id
            GROUP BY dp.barang_id, b.nama_barang, b.stok_tersedia
            ORDER BY jumlah_peminjaman DESC
            LIMIT 10
        ");
    }
    $stmt->execute();
    $topResult = $stmt->get_result();
    $top_barang = [];
    while ($r = $topResult->fetch_assoc()) {
        $top_barang[] = [
            'nama'              => $r['nama'],
            'jumlah_peminjaman' => (int)$r['jumlah_peminjaman'],
            'stok_tersedia'     => (int)$r['stok_tersedia']
        ];
    }
    $data['top_barang'] = $top_barang;

    // ── 4. All barang data (for Data Barang chart) ───────────────────────────
    $stmt = $conn->prepare("
        SELECT b.nama_barang AS nama,
               IFNULL(COUNT(dp.id), 0) AS jumlah_peminjaman,
               b.stok_tersedia
        FROM barang b
        LEFT JOIN detail_peminjaman dp ON dp.barang_id = b.id
        GROUP BY b.id, b.nama_barang, b.stok_tersedia
        ORDER BY b.nama_barang ASC
    ");
    $stmt->execute();
    $allResult = $stmt->get_result();
    $all_barang = [];
    while ($r = $allResult->fetch_assoc()) {
        $all_barang[] = [
            'nama'              => $r['nama'],
            'jumlah_peminjaman' => (int)$r['jumlah_peminjaman'],
            'stok_tersedia'     => (int)$r['stok_tersedia']
        ];
    }
    $data['all_barang'] = $all_barang;

    echo json_encode(['status' => true, 'data' => $data]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['status' => false, 'message' => $e->getMessage()]);
}
