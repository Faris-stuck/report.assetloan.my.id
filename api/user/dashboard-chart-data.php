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

    // ── 1. Status peminjaman counts (with optional date range, user-filtered) ──
    if ($tanggal_awal && $tanggal_akhir) {
        $stmt = $conn->prepare("
            SELECT
                SUM(CASE WHEN status = 'Waiting for Approval' THEN 1 ELSE 0 END) AS menunggu,
                SUM(CASE WHEN status IN ('Borrowed','Partial Approved','Return in Process','Partially Returned')
                          OR status LIKE 'Due%' OR status = 'Overdue' THEN 1 ELSE 0 END) AS dipinjam,
                SUM(CASE WHEN status = 'Returned' THEN 1 ELSE 0 END) AS dikembalikan,
                SUM(CASE WHEN status = 'Rejected'      THEN 1 ELSE 0 END) AS ditolak
            FROM peminjaman
            WHERE user_id = ? AND tanggal_pinjam BETWEEN ? AND ?
        ");
        $stmt->bind_param('iss', $user_id, $tanggal_awal, $tanggal_akhir);
    } else {
        $stmt = $conn->prepare("
            SELECT
                SUM(CASE WHEN status = 'Waiting for Approval' THEN 1 ELSE 0 END) AS menunggu,
                SUM(CASE WHEN status IN ('Borrowed','Partial Approved','Return in Process','Partially Returned')
                          OR status LIKE 'Due%' OR status = 'Overdue' THEN 1 ELSE 0 END) AS dipinjam,
                SUM(CASE WHEN status = 'Returned' THEN 1 ELSE 0 END) AS dikembalikan,
                SUM(CASE WHEN status = 'Rejected'      THEN 1 ELSE 0 END) AS ditolak
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

    // ── 2. Distinct categories from all barang in system (for dropdown) ────
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

    // ── 3. Top 5 Most Frequently Borrowed Items (lifetime, no filters) ──────────
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

    // ===== LOAN VS RETURN RATIO PIE CHART =====
    $ratioStart = isset($_GET['ratio_start']) ? $_GET['ratio_start'] : date('Y-m-d', strtotime('-29 days'));
    $ratioEnd = isset($_GET['ratio_end']) ? $_GET['ratio_end'] : date('Y-m-d');

    $stmtBorrowed = $conn->prepare("
        SELECT COUNT(*) as total
        FROM peminjaman p
        WHERE p.user_id = ?
          AND (
              p.status IN ('Borrowed', 'Partial Approved', 'Return in Process', 'Partially Returned')
              OR p.status LIKE 'Due%'
              OR p.status = 'Overdue'
          )
          AND DATE(p.tanggal_pinjam) BETWEEN ? AND ?
    ");
    $stmtBorrowed->bind_param('iss', $user_id, $ratioStart, $ratioEnd);
    $stmtBorrowed->execute();
    $borrowedCount = (int)$stmtBorrowed->get_result()->fetch_assoc()['total'];
    $stmtBorrowed->close();

    $stmtPartialApproved = $conn->prepare("
        SELECT COUNT(*) as total
        FROM peminjaman p
        WHERE p.user_id = ?
          AND p.status = 'Partial Approved'
          AND DATE(p.tanggal_pinjam) BETWEEN ? AND ?
    ");
    $stmtPartialApproved->bind_param('iss', $user_id, $ratioStart, $ratioEnd);
    $stmtPartialApproved->execute();
    $partialApprovedCount = (int)$stmtPartialApproved->get_result()->fetch_assoc()['total'];
    $stmtPartialApproved->close();

    $stmtReturned = $conn->prepare("
        SELECT COUNT(*) as total
        FROM peminjaman p
        WHERE p.user_id = ?
          AND p.status IN ('Returned', 'Partially Damaged', 'Fully Damaged', 'Completed')
          AND DATE(p.tanggal_pinjam) BETWEEN ? AND ?
    ");
    $stmtReturned->bind_param('iss', $user_id, $ratioStart, $ratioEnd);
    $stmtReturned->execute();
    $returnedCount = (int)$stmtReturned->get_result()->fetch_assoc()['total'];
    $stmtReturned->close();

    $data['loan_vs_return_ratio'] = [
        'borrowed' => $borrowedCount,
        'partial_approved' => $partialApprovedCount,
        'returned' => $returnedCount
    ];

    // Shared initial approval classification for approval and trend cards.
    $initialStatusJoin = "LEFT JOIN (
        SELECT
            peminjaman_id,
            SUM(CASE WHEN approval_status = 'Approved' THEN 1 ELSE 0 END) AS approved_count,
            SUM(CASE WHEN approval_status = 'Rejected' THEN 1 ELSE 0 END) AS rejected_count
        FROM peminjaman_units
        GROUP BY peminjaman_id
    ) pu_initial ON pu_initial.peminjaman_id = p.id";

    $initialApprovedCount = "COALESCE(pu_initial.approved_count, 0)";
    $initialRejectedCount = "COALESCE(pu_initial.rejected_count, 0)";

    $initialStatusCase = "CASE
        WHEN $initialApprovedCount > 0 AND $initialRejectedCount > 0 THEN 'Partial Approved'
        WHEN $initialApprovedCount > 0 THEN 'Approved'
        WHEN $initialRejectedCount > 0 THEN 'Rejected'
        WHEN p.status = 'Partial Approved' THEN 'Partial Approved'
        WHEN p.status = 'Waiting for Approval' THEN 'Pending Approval'
        WHEN p.status = 'Disetujui' AND p.tanggal_disetujui IS NOT NULL THEN 'Approved'
        WHEN p.tanggal_disetujui IS NOT NULL THEN 'Approved'
        WHEN p.status = 'Rejected' THEN 'Rejected'
        WHEN p.status IN ('Borrowed','Returned','Return in Process','Partially Returned','Partially Damaged','Fully Damaged','Completed')
             OR p.status LIKE 'Due%'
             OR p.status = 'Overdue' THEN 'Approved'
        ELSE NULL END";

    $initialStatusDateCol = "CASE
        WHEN $initialApprovedCount > 0 THEN COALESCE(p.tanggal_disetujui, p.created_at)
        WHEN $initialRejectedCount > 0 THEN p.created_at
        WHEN p.status = 'Partial Approved' THEN COALESCE(p.tanggal_disetujui, p.created_at)
        WHEN p.status = 'Waiting for Approval' THEN p.created_at
        WHEN p.status = 'Disetujui' AND p.tanggal_disetujui IS NOT NULL THEN p.tanggal_disetujui
        WHEN p.tanggal_disetujui IS NOT NULL THEN COALESCE(p.tanggal_disetujui, p.created_at)
        WHEN p.status = 'Rejected' THEN p.created_at
        WHEN p.status IN ('Borrowed','Returned','Return in Process','Partially Returned','Partially Damaged','Fully Damaged','Completed')
             OR p.status LIKE 'Due%'
             OR p.status = 'Overdue' THEN COALESCE(p.tanggal_disetujui, p.created_at)
        ELSE NULL END";

    // ===== APPROVAL STATUS PIE CHART =====
    $approvalStart = isset($_GET['approval_start']) ? $_GET['approval_start'] : date('Y-m-d', strtotime('-29 days'));
    $approvalEnd = isset($_GET['approval_end']) ? $_GET['approval_end'] : date('Y-m-d');
    $approvalEndTs = $approvalEnd . ' 23:59:59';

    $approvalCounts = [
        'Approved' => 0,
        'Partial Approved' => 0,
        'Rejected' => 0
    ];

    $sqlApproval = "SELECT $initialStatusCase AS status_group, COUNT(*) AS total
                    FROM peminjaman p
                    {$initialStatusJoin}
                    WHERE ($initialStatusCase) IS NOT NULL
                      AND ($initialStatusDateCol) >= ?
                      AND ($initialStatusDateCol) <= ?
                      AND p.user_id = ?
                    GROUP BY status_group";
    $stmtApproval = $conn->prepare($sqlApproval);
    $stmtApproval->bind_param('ssi', $approvalStart, $approvalEndTs, $user_id);
    $stmtApproval->execute();
    $resApproval = $stmtApproval->get_result();
    while ($row = $resApproval->fetch_assoc()) {
        $approvalCounts[$row['status_group']] = (int)$row['total'];
    }
    $stmtApproval->close();

    $data['approval_status'] = [
        'approve' => $approvalCounts['Approved'],
        'partial_approved' => $approvalCounts['Partial Approved'],
        'rejected' => $approvalCounts['Rejected']
    ];

    // ===== LOAN STATUS TREND =====
    $trendMonth = isset($_GET['trend_month']) ? $_GET['trend_month'] : date('Y-m');
    $trendMonthStart = $trendMonth . '-01';
    $trendMonthEnd = date('Y-m-t', strtotime($trendMonthStart)) . ' 23:59:59';

    $trendJoin = $initialStatusJoin;
    $trendCase = $initialStatusCase;
    $trendDateCol = $initialStatusDateCol;

    $baseline = [];
    $sqlB = "SELECT $trendCase AS status_group, COUNT(*) AS cnt
             FROM peminjaman p
             {$trendJoin}
             WHERE ($trendCase) IS NOT NULL
               AND ($trendDateCol) < ?
               AND p.user_id = ?
             GROUP BY status_group";
    $stmtB = $conn->prepare($sqlB);
    $stmtB->bind_param('si', $trendMonthStart, $user_id);
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
             {$trendJoin}
             WHERE ($trendCase) IS NOT NULL
               AND ($trendDateCol) >= ?
               AND ($trendDateCol) <= ?
               AND p.user_id = ?
             GROUP BY week_num, status_group
             ORDER BY week_num, status_group";
    $stmtW = $conn->prepare($sqlW);
    $stmtW->bind_param('ssi', $trendMonthStart, $trendMonthEnd, $user_id);
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
          AND stok_tersedia > 0
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
