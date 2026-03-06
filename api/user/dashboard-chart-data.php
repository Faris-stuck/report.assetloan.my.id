<?php
/**
 * API: User Dashboard Chart Data
 * Returns same data structure as admin/dashboard-stats.php but filtered by user_id
 * Supports: ?kategori=X  |  ?tanggal_awal=YYYY-MM-DD&tanggal_akhir=YYYY-MM-DD
 * Endpoint: /api/user/dashboard-chart-data.php
 */
require_once "../koneksi.php";
require_once "../session-helper.php";
header('Content-Type: application/json');

try {
    SessionValidator::requireRole(['user']);
    $user_id = SessionValidator::getUserId();
    if (!$user_id) throw new Exception("User ID not found");

    $data = [];

    // Optional filters
    $tanggal_awal  = isset($_GET['tanggal_awal'])  ? trim($_GET['tanggal_awal'])  : null;
    $tanggal_akhir = isset($_GET['tanggal_akhir']) ? trim($_GET['tanggal_akhir']) : null;
    $kategori      = isset($_GET['kategori'])      ? trim($_GET['kategori'])      : null;

    // ── 1. Status peminjaman counts (with optional date range, user-filtered) ──
    if ($tanggal_awal && $tanggal_akhir) {
        $stmt = $conn->prepare("
            SELECT
                SUM(CASE WHEN status = 'Menunggu Persetujuan' THEN 1 ELSE 0 END) AS menunggu,
                SUM(CASE WHEN status IN ('Sedang Dipinjam','Proses Return','Sebagian Dikembalikan')
                          OR status LIKE 'Due%' OR status = 'Overdue' THEN 1 ELSE 0 END) AS dipinjam,
                SUM(CASE WHEN status = 'Dikembalikan' THEN 1 ELSE 0 END) AS dikembalikan,
                SUM(CASE WHEN status = 'Ditolak'      THEN 1 ELSE 0 END) AS ditolak
            FROM peminjaman
            WHERE user_id = ? AND tanggal_pinjam BETWEEN ? AND ?
        ");
        $stmt->bind_param('iss', $user_id, $tanggal_awal, $tanggal_akhir);
    } else {
        $stmt = $conn->prepare("
            SELECT
                SUM(CASE WHEN status = 'Menunggu Persetujuan' THEN 1 ELSE 0 END) AS menunggu,
                SUM(CASE WHEN status IN ('Sedang Dipinjam','Proses Return','Sebagian Dikembalikan')
                          OR status LIKE 'Due%' OR status = 'Overdue' THEN 1 ELSE 0 END) AS dipinjam,
                SUM(CASE WHEN status = 'Dikembalikan' THEN 1 ELSE 0 END) AS dikembalikan,
                SUM(CASE WHEN status = 'Ditolak'      THEN 1 ELSE 0 END) AS ditolak
            FROM peminjaman
            WHERE user_id = ?
        ");
        $stmt->bind_param('i', $user_id);
    }
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    $data['menunggu_persetujuan'] = (int)($row['menunggu']    ?? 0);
    $data['sedang_dipinjam']      = (int)($row['dipinjam']    ?? 0);
    $data['dikembalikan']         = (int)($row['dikembalikan'] ?? 0);
    $data['ditolak']              = (int)($row['ditolak']      ?? 0);

    // per_status: DATEDIFF-based due-date classification for the Borrowing Status chart (user-filtered)
    // Active loans bucketed using nearest expected_return from peminjaman_units, falling back to rencana_kembali.
    $_dueCase = "CASE
            WHEN p.status IN ('Dikembalikan','Sebagian Rusak','Semua Rusak','Selesai') THEN 'Returned'
            WHEN p.status = 'Ditolak' THEN 'Rejected'
            WHEN p.status = 'Sebagian Dikembalikan' THEN 'Partially Returned'
            WHEN (p.status IN ('Sedang Dipinjam','Proses Return') OR p.status LIKE 'Due%' OR p.status = 'Overdue') THEN
                CASE
                    WHEN DATEDIFF(COALESCE(pu_near.nearest_return, p.rencana_kembali), CURDATE()) = 7  THEN 'Due 7 Day'
                    WHEN DATEDIFF(COALESCE(pu_near.nearest_return, p.rencana_kembali), CURDATE()) = 6  THEN 'Due 6 Day'
                    WHEN DATEDIFF(COALESCE(pu_near.nearest_return, p.rencana_kembali), CURDATE()) = 5  THEN 'Due 5 Day'
                    WHEN DATEDIFF(COALESCE(pu_near.nearest_return, p.rencana_kembali), CURDATE()) = 4  THEN 'Due 4 Day'
                    WHEN DATEDIFF(COALESCE(pu_near.nearest_return, p.rencana_kembali), CURDATE()) = 3  THEN 'Due 3 Day'
                    WHEN DATEDIFF(COALESCE(pu_near.nearest_return, p.rencana_kembali), CURDATE()) = 2  THEN 'Due 2 Day'
                    WHEN DATEDIFF(COALESCE(pu_near.nearest_return, p.rencana_kembali), CURDATE()) = 1  THEN 'Due Tomorrow'
                    WHEN DATEDIFF(COALESCE(pu_near.nearest_return, p.rencana_kembali), CURDATE()) = 0  THEN 'Due Today'
                    WHEN DATEDIFF(COALESCE(pu_near.nearest_return, p.rencana_kembali), CURDATE()) < 0  THEN 'Overdue'
                    ELSE 'Borrowed'
                END
            ELSE p.status
        END";
    $_joinPuNear = "LEFT JOIN (
            SELECT peminjaman_id, MIN(expected_return) AS nearest_return
            FROM peminjaman_units
            WHERE return_status NOT IN ('Dikembalikan','Rusak')
            GROUP BY peminjaman_id
        ) pu_near ON p.id = pu_near.peminjaman_id";
    if ($tanggal_awal && $tanggal_akhir) {
        $stmt = $conn->prepare("
            SELECT status_label AS status, COUNT(*) AS total
            FROM (SELECT {$_dueCase} AS status_label FROM peminjaman p {$_joinPuNear} WHERE p.user_id = ? AND p.tanggal_pinjam BETWEEN ? AND ?) t
            GROUP BY status_label ORDER BY total DESC
        ");
        $stmt->bind_param('iss', $user_id, $tanggal_awal, $tanggal_akhir);
    } else {
        $stmt = $conn->prepare("
            SELECT status_label AS status, COUNT(*) AS total
            FROM (SELECT {$_dueCase} AS status_label FROM peminjaman p {$_joinPuNear} WHERE p.user_id = ?) t
            GROUP BY status_label ORDER BY total DESC
        ");
        $stmt->bind_param('i', $user_id);
    }
    $stmt->execute();
    $perStatusResult = $stmt->get_result();
    $per_status = [];
    while ($r = $perStatusResult->fetch_assoc()) {
        $per_status[] = ['status' => $r['status'], 'total' => (int)$r['total']];
    }
    $data['per_status'] = $per_status;

    // ── 2. Distinct categories from items this user borrowed (for dropdown) ────
    $stmt = $conn->prepare("
        SELECT DISTINCT b.kategori FROM barang b
        INNER JOIN detail_peminjaman dp ON dp.barang_id = b.id
        INNER JOIN peminjaman p ON p.id = dp.peminjaman_id
        WHERE p.user_id = ? AND b.kategori IS NOT NULL AND b.kategori <> ''
        ORDER BY b.kategori ASC
    ");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $catResult = $stmt->get_result();
    $categories = [];
    while ($r = $catResult->fetch_assoc()) {
        $categories[] = $r['kategori'];
    }
    $data['categories'] = $categories;

    // ── 3. Top 10 items this user borrowed (optional kategori filter) ──────────
    if ($kategori && $kategori !== 'all') {
        $stmt = $conn->prepare("
            SELECT b.nama_barang AS nama,
                   COUNT(dp.id)  AS jumlah_peminjaman,
                   b.stok_tersedia
            FROM detail_peminjaman dp
            JOIN barang b ON b.id = dp.barang_id
            JOIN peminjaman p ON p.id = dp.peminjaman_id
            WHERE p.user_id = ? AND b.kategori = ?
            GROUP BY dp.barang_id, b.nama_barang, b.stok_tersedia
            ORDER BY jumlah_peminjaman DESC
            LIMIT 10
        ");
        $stmt->bind_param('is', $user_id, $kategori);
    } else {
        $stmt = $conn->prepare("
            SELECT b.nama_barang AS nama,
                   COUNT(dp.id)  AS jumlah_peminjaman,
                   b.stok_tersedia
            FROM detail_peminjaman dp
            JOIN barang b ON b.id = dp.barang_id
            JOIN peminjaman p ON p.id = dp.peminjaman_id
            WHERE p.user_id = ?
            GROUP BY dp.barang_id, b.nama_barang, b.stok_tersedia
            ORDER BY jumlah_peminjaman DESC
            LIMIT 10
        ");
        $stmt->bind_param('i', $user_id);
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

    // ── 4. All items this user ever borrowed (for Data Barang chart) ──────────
    $stmt = $conn->prepare("
        SELECT b.nama_barang AS nama,
               COUNT(dp.id) AS jumlah_peminjaman,
               b.stok_tersedia
        FROM detail_peminjaman dp
        JOIN barang b ON dp.barang_id = b.id
        JOIN peminjaman p ON p.id = dp.peminjaman_id
        WHERE p.user_id = ?
        GROUP BY dp.barang_id, b.nama_barang, b.stok_tersedia
        ORDER BY b.nama_barang ASC
    ");
    $stmt->bind_param('i', $user_id);
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

    // ── 5. Available items fallback (for Data Barang chart when user has no borrowing history) ──
    $stmt = $conn->prepare("
        SELECT nama_barang AS nama,
               0            AS jumlah_peminjaman,
               stok_tersedia
        FROM barang
        WHERE stok_tersedia > 0
        ORDER BY nama_barang ASC
    ");
    $stmt->execute();
    $availResult = $stmt->get_result();
    $available_barang = [];
    while ($r = $availResult->fetch_assoc()) {
        $available_barang[] = [
            'nama'              => $r['nama'],
            'jumlah_peminjaman' => 0,
            'stok_tersedia'     => (int)$r['stok_tersedia']
        ];
    }
    $data['available_barang'] = $available_barang;

    echo json_encode(['status' => true, 'data' => $data]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['status' => false, 'message' => $e->getMessage()]);
}
