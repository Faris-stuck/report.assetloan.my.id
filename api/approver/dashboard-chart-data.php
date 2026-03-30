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
                SUM(CASE WHEN status = 'Waiting for Approval' THEN 1 ELSE 0 END) AS menunggu,
                SUM(CASE WHEN status IN ('Borrowed','Return in Process','Partially Returned')
                          OR status LIKE 'Due%' OR status = 'Overdue' THEN 1 ELSE 0 END) AS dipinjam,
                SUM(CASE WHEN status = 'Returned' THEN 1 ELSE 0 END) AS dikembalikan,
                SUM(CASE WHEN status = 'Rejected'      THEN 1 ELSE 0 END) AS ditolak
            FROM peminjaman
            WHERE tanggal_pinjam BETWEEN ? AND ?
        ");
        $stmt->bind_param('ss', $tanggal_awal, $tanggal_akhir);
    } else {
        $stmt = $conn->prepare("
            SELECT
                SUM(CASE WHEN status = 'Waiting for Approval' THEN 1 ELSE 0 END) AS menunggu,
                SUM(CASE WHEN status IN ('Borrowed','Return in Process','Partially Returned')
                          OR status LIKE 'Due%' OR status = 'Overdue' THEN 1 ELSE 0 END) AS dipinjam,
                SUM(CASE WHEN status = 'Returned' THEN 1 ELSE 0 END) AS dikembalikan,
                SUM(CASE WHEN status = 'Rejected'      THEN 1 ELSE 0 END) AS ditolak
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
            WHEN p.status = 'Waiting for Approval' THEN 'Pending Approval'
            WHEN p.status = 'Rejected' THEN 'Rejected'
            WHEN p.status IN ('Returned','Partially Damaged','Fully Damaged','Completed') THEN 'Returned'
            WHEN DATEDIFF(COALESCE(pu_near.nearest_return, p.rencana_kembali), CURDATE()) < 0  THEN 'Overdue'
            WHEN DATEDIFF(COALESCE(pu_near.nearest_return, p.rencana_kembali), CURDATE()) = 0  THEN 'Due Today'
            WHEN DATEDIFF(COALESCE(pu_near.nearest_return, p.rencana_kembali), CURDATE()) = 1  THEN 'Due Tomorrow'
            WHEN DATEDIFF(COALESCE(pu_near.nearest_return, p.rencana_kembali), CURDATE()) BETWEEN 2 AND 7
                THEN CONCAT('Due In ', DATEDIFF(COALESCE(pu_near.nearest_return, p.rencana_kembali), CURDATE()), ' Days')
            ELSE p.status
        END";
    $_joinPuNear = "LEFT JOIN (
            SELECT peminjaman_id, MIN(expected_return) AS nearest_return
            FROM peminjaman_units
            WHERE return_status NOT IN ('Returned','Damaged','Rejected')
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

    // ── 2. Top 5 Most Frequently Borrowed Items (lifetime, no filters) ──────
    $stmtTop = $conn->prepare("
        SELECT b.nama_barang, SUM(dp.jumlah) AS total_qty_dipinjam, MAX(p.created_at) AS last_borrowed
        FROM detail_peminjaman dp
        JOIN barang b ON b.id = dp.barang_id
        JOIN peminjaman p ON p.id = dp.peminjaman_id
        GROUP BY b.id, b.nama_barang
        ORDER BY total_qty_dipinjam DESC, last_borrowed DESC
        LIMIT 5
    ");
    $stmtTop->execute();
    $topResult = $stmtTop->get_result();
    $top_barang = [];
    while ($r = $topResult->fetch_assoc()) {
        $top_barang[] = ['nama' => $r['nama_barang'], 'total_qty' => (int)$r['total_qty_dipinjam']];
    }
    $data['top_barang'] = $top_barang;
    $stmtTop->close();

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

    // ===== LOAN VS RETURN RATIO PIE CHART =====
    $ratioStart = isset($_GET['ratio_start']) ? $_GET['ratio_start'] : date('Y-m-d', strtotime('-29 days'));
    $ratioEnd = isset($_GET['ratio_end']) ? $_GET['ratio_end'] : date('Y-m-d');

    $stmtBorrowed = $conn->prepare("
        SELECT COUNT(*) as total
        FROM peminjaman
        WHERE (
            status IN ('Borrowed', 'Partial Approved', 'Return in Process', 'Partially Returned')
            OR status LIKE 'Due%'
            OR status = 'Overdue'
        )
        AND DATE(tanggal_pinjam) BETWEEN ? AND ?
    ");
    $stmtBorrowed->bind_param('ss', $ratioStart, $ratioEnd);
    $stmtBorrowed->execute();
    $borrowedCount = (int)$stmtBorrowed->get_result()->fetch_assoc()['total'];
    $stmtBorrowed->close();

    $stmtPartialApproved = $conn->prepare("
        SELECT COUNT(*) as total
        FROM peminjaman
        WHERE status = 'Partial Approved'
          AND DATE(tanggal_pinjam) BETWEEN ? AND ?
    ");
    $stmtPartialApproved->bind_param('ss', $ratioStart, $ratioEnd);
    $stmtPartialApproved->execute();
    $partialApprovedCount = (int)$stmtPartialApproved->get_result()->fetch_assoc()['total'];
    $stmtPartialApproved->close();

    $stmtReturned = $conn->prepare("
        SELECT COUNT(*) as total
        FROM peminjaman
        WHERE status IN ('Returned', 'Partially Damaged', 'Fully Damaged', 'Completed')
          AND DATE(tanggal_pinjam) BETWEEN ? AND ?
    ");
    $stmtReturned->bind_param('ss', $ratioStart, $ratioEnd);
    $stmtReturned->execute();
    $returnedCount = (int)$stmtReturned->get_result()->fetch_assoc()['total'];
    $stmtReturned->close();

    $data['loan_vs_return_ratio'] = [
        'borrowed' => $borrowedCount,
        'partial_approved' => $partialApprovedCount,
        'returned' => $returnedCount
    ];

    // ===== APPROVAL STATUS PIE CHART =====
    $approvalStart = isset($_GET['approval_start']) ? $_GET['approval_start'] : date('Y-m-d', strtotime('-29 days'));
    $approvalEnd = isset($_GET['approval_end']) ? $_GET['approval_end'] : date('Y-m-d');

    $stmtApprove = $conn->prepare("SELECT COUNT(*) as total FROM peminjaman WHERE status NOT IN ('Waiting for Approval','Partial Approved','Rejected') AND tanggal_disetujui BETWEEN ? AND ?");
    $stmtApprove->bind_param('ss', $approvalStart, $approvalEnd);
    $stmtApprove->execute();
    $approveCount = (int)$stmtApprove->get_result()->fetch_assoc()['total'];
    $stmtApprove->close();

    $stmtPartial = $conn->prepare("SELECT COUNT(*) as total FROM peminjaman WHERE status = 'Partial Approved' AND tanggal_disetujui BETWEEN ? AND ?");
    $stmtPartial->bind_param('ss', $approvalStart, $approvalEnd);
    $stmtPartial->execute();
    $partialCount = (int)$stmtPartial->get_result()->fetch_assoc()['total'];
    $stmtPartial->close();

    $rejectedEnd = $approvalEnd . ' 23:59:59';
    $stmtRejected = $conn->prepare("SELECT COUNT(*) as total FROM peminjaman WHERE status = 'Rejected' AND created_at BETWEEN ? AND ?");
    $stmtRejected->bind_param('ss', $approvalStart, $rejectedEnd);
    $stmtRejected->execute();
    $rejectedCount = (int)$stmtRejected->get_result()->fetch_assoc()['total'];
    $stmtRejected->close();

    $data['approval_status'] = [
        'approve' => $approveCount,
        'partial_approved' => $partialCount,
        'rejected' => $rejectedCount
    ];

    // ===== LOAN STATUS TREND =====
    $trendMonth = isset($_GET['trend_month']) ? $_GET['trend_month'] : date('Y-m');
    $trendMonthStart = $trendMonth . '-01';
    $trendMonthEnd = date('Y-m-t', strtotime($trendMonthStart)) . ' 23:59:59';

    $trendCase = "CASE
        WHEN p.status IN ('Borrowed','Returned') THEN 'Approve'
        WHEN p.status = 'Partial Approved' THEN 'Partial Approved'
        WHEN p.status = 'Rejected' THEN 'Rejected'
        ELSE NULL END";

    $trendDateCol = "CASE
        WHEN p.status IN ('Borrowed','Returned','Partial Approved') THEN p.tanggal_disetujui
        WHEN p.status = 'Rejected' THEN p.created_at
        ELSE NULL END";

    $baseline = [];
    $sqlB = "SELECT $trendCase AS status_group, COUNT(*) AS cnt
             FROM peminjaman p
             WHERE ($trendCase) IS NOT NULL
               AND ($trendDateCol) < ?
             GROUP BY status_group";
    $stmtB = $conn->prepare($sqlB);
    $stmtB->bind_param('s', $trendMonthStart);
    $stmtB->execute();
    $resB = $stmtB->get_result();
    while ($row = $resB->fetch_assoc()) {
        $baseline[$row['status_group']] = (int)$row['cnt'];
    }
    $stmtB->close();

    $weekRaw = [];
    $sqlW = "SELECT LEAST(CEIL(DAY(($trendDateCol)) / 7.0), 4) AS week_num,
                    $trendCase AS status_group, COUNT(*) AS cnt
             FROM peminjaman p
             WHERE ($trendCase) IS NOT NULL
               AND ($trendDateCol) >= ?
               AND ($trendDateCol) <= ?
             GROUP BY week_num, status_group
             ORDER BY week_num, status_group";
    $stmtW = $conn->prepare($sqlW);
    $stmtW->bind_param('ss', $trendMonthStart, $trendMonthEnd);
    $stmtW->execute();
    $resW = $stmtW->get_result();
    while ($row = $resW->fetch_assoc()) {
        $w = (int)$row['week_num'];
        if (!isset($weekRaw[$w])) $weekRaw[$w] = [];
        $weekRaw[$w][$row['status_group']] = (int)$row['cnt'];
    }
    $stmtW->close();

    $data['loan_status_trend'] = [
        'month' => $trendMonth,
        'baseline' => $baseline,
        'raw' => $weekRaw
    ];

    // New Products This Month
    $stmtNewProducts = $conn->prepare("
        SELECT 
            nama_barang,
            stok_tersedia,
            stok_total,
            lokasi
        FROM barang
        WHERE DATE_FORMAT(created_at, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')
        ORDER BY created_at DESC
    ");
    $stmtNewProducts->execute();
    $newProductsResult = $stmtNewProducts->get_result();
    $new_products = [];
    while ($row = $newProductsResult->fetch_assoc()) {
        $new_products[] = [
            'nama_barang' => $row['nama_barang'],
            'stok_tersedia' => (int)$row['stok_tersedia'],
            'stok_total' => (int)$row['stok_total'],
            'lokasi' => $row['lokasi']
        ];
    }
    $data['new_products'] = $new_products;
    $stmtNewProducts->close();

    echo json_encode(['status' => true, 'data' => $data]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['status' => false, 'message' => $e->getMessage()]);
}
