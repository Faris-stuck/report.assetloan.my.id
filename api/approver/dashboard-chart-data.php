<?php
/**
 * API: Manager/Approver Dashboard Chart Data
 * Returns system-wide status counts, per-user counts, per-barang counts (for bar charts)
 * Supports independent filters:
 *   - Status filter: ?tanggal_awal=YYYY-MM-DD&tanggal_akhir=YYYY-MM-DD (Status chart only)
 *   - Top Barang filter: ?kategori=CATEGORY_NAME (Top Barang chart only, INDEPENDENT)
 *   - Top User filter: ?tanggal_awal_user=YYYY-MM-DD&tanggal_akhir_user=YYYY-MM-DD (Top User chart only, INDEPENDENT)
 * Endpoint: /api/approver/dashboard-chart-data.php
 */
require_once "../koneksi.php";
require_once "../session-helper.php";
header('Content-Type: application/json');

try {
    SessionValidator::requireRole(['admin', 'manager']);

    $data = [];

    // Optional filters
    $tanggal_awal  = isset($_GET['tanggal_awal'])  ? trim($_GET['tanggal_awal'])  : null;
    $tanggal_akhir = isset($_GET['tanggal_akhir']) ? trim($_GET['tanggal_akhir']) : null;
    $tanggal_awal_user  = isset($_GET['tanggal_awal_user'])  ? trim($_GET['tanggal_awal_user'])  : null;
    $tanggal_akhir_user = isset($_GET['tanggal_akhir_user']) ? trim($_GET['tanggal_akhir_user']) : null;
    $kategori = isset($_GET['kategori']) ? trim($_GET['kategori']) : null;

    // Get list of categories for dropdown
    $stmt = $conn->prepare("SELECT DISTINCT kategori FROM barang ORDER BY kategori");
    $stmt->execute();
    $categoriesResult = $stmt->get_result();
    $categories = [];
    while ($row = $categoriesResult->fetch_assoc()) {
        if (!empty($row['kategori'])) {
            $categories[] = $row['kategori'];
        }
    }
    $data['categories'] = $categories;

    // ── 1. Status peminjaman (with optional date range) ──────────────────────
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
    // Active loans bucketed using nearest expected_return from peminjaman_units, falling back to rencana_kembali.
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

    // ── 2. Top Barang (with optional kategori filter, INDEPENDENT from Status date filter) ──────
    $barangQuery = "
        SELECT b.nama_barang AS nama,
               COUNT(dp.id)  AS jumlah_peminjaman,
               b.stok_tersedia,
               b.kategori
        FROM detail_peminjaman dp
        JOIN barang b ON b.id = dp.barang_id
        WHERE 1=1
    ";

    // Top Barang has independent kategori filter
    $barangParams = [];
    if ($kategori && $kategori !== 'all') {
        $barangQuery .= " AND b.kategori = ?";
        $barangParams[] = $kategori;
    }

    $barangQuery .= " GROUP BY dp.barang_id, b.nama_barang, b.stok_tersedia, b.kategori
                      ORDER BY jumlah_peminjaman DESC
                      LIMIT 10";

    $stmt = $conn->prepare($barangQuery);
    if (!empty($barangParams)) {
        $stmt->bind_param('s', ...$barangParams);
    }
    $stmt->execute();
    $topResult = $stmt->get_result();
    $per_barang = [];
    while ($r = $topResult->fetch_assoc()) {
        $per_barang[] = [
            'nama'              => $r['nama'],
            'jumlah_peminjaman' => (int)$r['jumlah_peminjaman'],
            'stok_tersedia'     => (int)$r['stok_tersedia']
        ];
    }
    $data['per_barang'] = $per_barang;

    // ── 3. Top User (INDEPENDENT date range filter) ──────────────────────────
    if ($tanggal_awal_user && $tanggal_akhir_user) {
        $stmt = $conn->prepare("
            SELECT nama_peminjam AS nama,
                   COUNT(*) AS jumlah
            FROM peminjaman
            WHERE tanggal_pinjam BETWEEN ? AND ?
            GROUP BY user_id, nama_peminjam
            ORDER BY jumlah DESC
            LIMIT 10
        ");
        $stmt->bind_param('ss', $tanggal_awal_user, $tanggal_akhir_user);
    } else {
        $stmt = $conn->prepare("
            SELECT nama_peminjam AS nama,
                   COUNT(*) AS jumlah
            FROM peminjaman
            GROUP BY user_id, nama_peminjam
            ORDER BY jumlah DESC
            LIMIT 10
        ");
    }
    $stmt->execute();
    $userResult = $stmt->get_result();
    $per_user = [];
    while ($r = $userResult->fetch_assoc()) {
        $per_user[] = [
            'nama'   => $r['nama'],
            'jumlah' => (int)$r['jumlah']
        ];
    }
    $data['per_user'] = $per_user;

    echo json_encode(['status' => true, 'data' => $data]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['status' => false, 'message' => $e->getMessage()]);
}
