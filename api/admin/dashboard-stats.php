<?php

/**
 * API: Admin Dashboard Statistics
 * Purpose: Get statistics for admin dashboard from peminjaman database
 * Endpoint: /api/admin/dashboard-stats.php
 * Database: peminjaman
 */

require_once "../koneksi.php";
require_once "../session-helper.php";
header('Content-Type: application/json');

try {
    // Validate admin/manager role
    SessionValidator::requireRole(['admin', 'manager']);

    // Get date range filter parameters for Status Peminjaman
    $tanggalAwal = $_GET['tanggal_awal'] ?? '';
    $tanggalAkhir = $_GET['tanggal_akhir'] ?? '';

    // Build date range WHERE clause if dates are provided
    $dateWhereClause = '';
    $dateParams = [];
    $dateParamTypes = '';

    if (!empty($tanggalAwal) && !empty($tanggalAkhir)) {
        $dateWhereClause = " AND tanggal_pinjam BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)";
        $dateParams = [$tanggalAwal, $tanggalAkhir];
        $dateParamTypes = 'ss';
    } elseif (!empty($tanggalAwal)) {
        $dateWhereClause = " AND tanggal_pinjam >= ?";
        $dateParams = [$tanggalAwal];
        $dateParamTypes = 's';
    } elseif (!empty($tanggalAkhir)) {
        $dateWhereClause = " AND tanggal_pinjam <= DATE_ADD(?, INTERVAL 1 DAY)";
        $dateParams = [$tanggalAkhir];
        $dateParamTypes = 's';
    }

    $stats = [];

    /**
     * STATUS PEMINJAMAN CHART
     * 
     * Counts transactions (rows in peminjaman table), NOT distinct users
     * Each peminjaman record = 1 transaction
     * 
     * Examples:
     * - If user A has 2 loans in "Borrowed" status = count 2 (not 1)
     * - If user B has 3 loans in "Rejected" status = count 3 (not 1)
     * 
     * Status mapping to display categories:
     * - "Waiting for Approval": Only status = 'Waiting for Approval'
     * - "Borrowed": 'Borrowed' + 'Partial Approved' + 'Partially Returned' + 'Return in Process' + 'Due*' + 'Overdue'
     * - "Returned": 'Returned' + 'Partially Damaged' + 'Fully Damaged' + 'Completed'
     * - "Rejected": Only status = 'Rejected'
     */

    // 1. Waiting for Approval (pending approval)
    $query1 = "SELECT COUNT(*) as total FROM peminjaman WHERE status = 'Waiting for Approval'" . $dateWhereClause;
    $stmt = $conn->prepare($query1);
    if (!$stmt) {
        throw new Exception("Query 1 Error: " . $conn->error);
    }
    if (!empty($dateParams)) {
        $stmt->bind_param($dateParamTypes, ...$dateParams);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stats['menunggu_persetujuan'] = (int)($row['total'] ?? 0);
    $stmt->close();

    // 2. Borrowed (active loans - includes Due patterns and partial returns)
    $query2 = "SELECT COUNT(*) as total FROM peminjaman WHERE (status IN ('Borrowed', 'Partial Approved', 'Partially Returned', 'Return in Process') OR status LIKE 'Due%' OR status = 'Overdue')" . $dateWhereClause;
    $stmt = $conn->prepare($query2);
    if (!$stmt) {
        throw new Exception("Query 2 Error: " . $conn->error);
    }
    if (!empty($dateParams)) {
        $stmt->bind_param($dateParamTypes, ...$dateParams);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stats['sedang_dipinjam'] = (int)($row['total'] ?? 0);
    $stmt->close();

    // 3. Returned (all time aggregate - only canonical 'Returned' status)
    $query3 = "SELECT COUNT(*) as total FROM peminjaman WHERE status = 'Returned'" . $dateWhereClause;
    $stmt = $conn->prepare($query3);
    if (!$stmt) {
        throw new Exception("Query 3 Error: " . $conn->error);
    }
    if (!empty($dateParams)) {
        $stmt->bind_param($dateParamTypes, ...$dateParams);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stats['dikembalikan'] = (int)($row['total'] ?? 0);
    $stmt->close();

    // 4. Rejected
    $query4 = "SELECT COUNT(*) as total FROM peminjaman WHERE status = 'Rejected'" . $dateWhereClause;
    $stmt = $conn->prepare($query4);
    if (!$stmt) {
        throw new Exception("Query 4 Error: " . $conn->error);
    }
    if (!empty($dateParams)) {
        $stmt->bind_param($dateParamTypes, ...$dateParams);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stats['ditolak'] = (int)($row['total'] ?? 0);
    $stmt->close();

    // 5. Total barang tersedia
    $stmt = $conn->prepare("
        SELECT SUM(stok_tersedia) as total FROM barang
    ");
    if (!$stmt) {
        throw new Exception("Query 5 Error: " . $conn->error);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stats['barang_tersedia'] = (int)($row['total'] ?? 0);
    $stmt->close();

    // 6. Recent activity from peminjaman (all statuses, most recent first)
    $stmt = $conn->prepare("
        SELECT 
            p.id, p.kode_peminjaman, p.nama_peminjam,
            p.status, p.tanggal_pinjam, p.rencana_kembali, p.created_at
        FROM peminjaman p
        ORDER BY p.created_at DESC
        LIMIT 10
    ");
    if (!$stmt) {
        throw new Exception("Query 6 Error: " . $conn->error);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $recent = [];
    while ($row = $result->fetch_assoc()) {
        $nearest_expected = getNearestExpectedReturn($conn, $row['id']);
        $recent[] = [
            'id' => (int)$row['id'],
            'kode' => $row['kode_peminjaman'],
            'nama' => $row['nama_peminjam'],
            'status' => computeDueStatus($row['status'], $nearest_expected ?? $row['rencana_kembali']),
            'tanggal_pinjam' => ($row['tanggal_pinjam'] ? date('d/m/Y', strtotime($row['tanggal_pinjam'])) : '-'),
            'rencana_kembali' => ($row['rencana_kembali'] ? date('d/m/Y', strtotime($row['rencana_kembali'])) : '-'),
            'expected_return_nearest' => $nearest_expected ? date('d/m/Y', strtotime($nearest_expected)) : ($row['rencana_kembali'] ? date('d/m/Y', strtotime($row['rencana_kembali'])) : '-')
        ];
    }
    $stats['recent_actions'] = $recent;
    $stmt->close();

    // 7. Top 5 Most Frequently Borrowed Items (lifetime, no filters)
    $stmtTop = $conn->prepare("
        SELECT b.nama_barang, SUM(dp.jumlah) AS total_qty_dipinjam, MAX(p.created_at) AS last_borrowed
        FROM detail_peminjaman dp
        JOIN barang b ON b.id = dp.barang_id
        JOIN peminjaman p ON p.id = dp.peminjaman_id
        GROUP BY b.id, b.nama_barang
        ORDER BY total_qty_dipinjam DESC, last_borrowed DESC
        LIMIT 5
    ");
    if (!$stmtTop) {
        throw new Exception("Query 7 Error: " . $conn->error);
    }
    $stmtTop->execute();
    $topResult = $stmtTop->get_result();
    $top_barang = [];
    while ($r = $topResult->fetch_assoc()) {
        $top_barang[] = ['nama' => $r['nama_barang'], 'total_qty' => (int)$r['total_qty_dipinjam']];
    }
    $stats['top_barang'] = $top_barang;
    $stmtTop->close();

    // ── Top Borrowers (INDEPENDENT date range filter) ─────────────────────
    $tanggalAwalUser = $_GET['tanggal_awal_user'] ?? '';
    $tanggalAkhirUser = $_GET['tanggal_akhir_user'] ?? '';
    if (!empty($tanggalAwalUser) && !empty($tanggalAkhirUser)) {
        $stmtUser = $conn->prepare("SELECT nama_peminjam AS nama, COUNT(*) AS jumlah FROM peminjaman WHERE tanggal_pinjam BETWEEN ? AND ? GROUP BY user_id, nama_peminjam ORDER BY jumlah DESC LIMIT 10");
        $stmtUser->bind_param('ss', $tanggalAwalUser, $tanggalAkhirUser);
    } else {
        $stmtUser = $conn->prepare("SELECT nama_peminjam AS nama, COUNT(*) AS jumlah FROM peminjaman GROUP BY user_id, nama_peminjam ORDER BY jumlah DESC LIMIT 10");
    }
    $stmtUser->execute();
    $userResult = $stmtUser->get_result();
    $per_user = [];
    while ($r = $userResult->fetch_assoc()) {
        $per_user[] = ['nama' => $r['nama'], 'jumlah' => (int)$r['jumlah']];
    }
    $stats['per_user'] = $per_user;
    $stmtUser->close();

    // 8. Get all categories for filter dropdown
    $stmt = $conn->prepare("
        SELECT DISTINCT kategori FROM barang WHERE kategori IS NOT NULL AND kategori != '' ORDER BY kategori ASC
    ");
    if (!$stmt) {
        throw new Exception("Query 8 Error: " . $conn->error);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $categories = [];
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row['kategori'];
    }
    $stats['categories'] = $categories;
    $stmt->close();

    // 9. Data Barang: All items with dipinjam (counts all detail_peminjaman records, matching PIC API logic)
    $stmt = $conn->prepare("
        SELECT 
            b.id,
            b.nama_barang, 
            b.stok_tersedia, 
            IFNULL(COUNT(dp.id), 0) AS jumlah_dipinjam
        FROM barang b
        LEFT JOIN detail_peminjaman dp ON dp.barang_id = b.id
        GROUP BY b.id, b.nama_barang, b.stok_tersedia
        ORDER BY b.nama_barang ASC
    ");
    if (!$stmt) {
        throw new Exception("Query 9 Error: " . $conn->error);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $all_barang = [];
    while ($row = $result->fetch_assoc()) {
        $all_barang[] = [
            'id' => (int)$row['id'],
            'nama' => $row['nama_barang'],
            'jumlah_peminjaman' => (int)$row['jumlah_dipinjam'],
            'stok_tersedia' => (int)$row['stok_tersedia']
        ];
    }
    $stats['all_barang'] = $all_barang;
    $stmt->close();

    // 10. per_status: DATEDIFF-based due-date classification for the Borrowing Status chart
    // Active loans are bucketed using per-unit nearest expected_return (from peminjaman_units),
    // falling back to peminjaman.rencana_kembali when no unit data exists.
    // per_status: Due-date classification for the Borrowing Status chart.
    // All non-final statuses (including Partial Approved, Approved, Partially Returned)
    // are bucketed by due-date proximity using nearest expected_return from peminjaman_units.
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
    $perStatusQuery = "SELECT status_label AS status, COUNT(*) AS total FROM (
        SELECT {$_dueCase} AS status_label
        FROM peminjaman p
        LEFT JOIN (
            SELECT peminjaman_id, MIN(expected_return) AS nearest_return
            FROM peminjaman_units
            WHERE return_status NOT IN ('Returned','Damaged','Rejected')
            GROUP BY peminjaman_id
        ) pu_near ON p.id = pu_near.peminjaman_id
        WHERE 1=1" . str_replace('tanggal_pinjam', 'p.tanggal_pinjam', $dateWhereClause) . ") t GROUP BY status_label ORDER BY total DESC";
    $stmt = $conn->prepare($perStatusQuery);
    if (!$stmt) {
        throw new Exception("Query per_status Error: " . $conn->error);
    }
    if (!empty($dateParams)) {
        $stmt->bind_param($dateParamTypes, ...$dateParams);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $per_status = [];
    while ($row = $result->fetch_assoc()) {
        $per_status[] = ['status' => $row['status'], 'total' => (int)$row['total']];
    }
    $stats['per_status'] = $per_status;
    $stmt->close();

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

    $stats['loan_vs_return_ratio'] = [
        'borrowed' => $borrowedCount,
        'partial_approved' => $partialApprovedCount,
        'returned' => $returnedCount
    ];

    // ===== APPROVAL STATUS PIE CHART =====
    $approvalStart = isset($_GET['approval_start']) ? $_GET['approval_start'] : date('Y-m-d', strtotime('-29 days'));
    $approvalEnd = isset($_GET['approval_end']) ? $_GET['approval_end'] : date('Y-m-d');

    // Count Approve (Borrowed, Returned, etc. - everything that passed approval)
    $stmtApprove = $conn->prepare("SELECT COUNT(*) as total FROM peminjaman WHERE status NOT IN ('Waiting for Approval','Partial Approved','Rejected') AND tanggal_disetujui BETWEEN ? AND ?");
    $stmtApprove->bind_param('ss', $approvalStart, $approvalEnd);
    $stmtApprove->execute();
    $approveCount = (int)$stmtApprove->get_result()->fetch_assoc()['total'];
    $stmtApprove->close();

    // Count Partial Approved
    $stmtPartial = $conn->prepare("SELECT COUNT(*) as total FROM peminjaman WHERE status = 'Partial Approved' AND tanggal_disetujui BETWEEN ? AND ?");
    $stmtPartial->bind_param('ss', $approvalStart, $approvalEnd);
    $stmtPartial->execute();
    $partialCount = (int)$stmtPartial->get_result()->fetch_assoc()['total'];
    $stmtPartial->close();

    // Count Rejected (use created_at since tanggal_disetujui is NULL for rejected)
    $rejectedEnd = $approvalEnd . ' 23:59:59';
    $stmtRejected = $conn->prepare("SELECT COUNT(*) as total FROM peminjaman WHERE status = 'Rejected' AND created_at BETWEEN ? AND ?");
    $stmtRejected->bind_param('ss', $approvalStart, $rejectedEnd);
    $stmtRejected->execute();
    $rejectedCount = (int)$stmtRejected->get_result()->fetch_assoc()['total'];
    $stmtRejected->close();

    $stats['approval_status'] = [
        'approve' => $approveCount,
        'partial_approved' => $partialCount,
        'rejected' => $rejectedCount
    ];

    // ===== LOAN STATUS TREND =====
    $trendMonth = isset($_GET['trend_month']) ? $_GET['trend_month'] : date('Y-m');
    $trendMonthStart = $trendMonth . '-01';
    $trendMonthEnd = date('Y-m-t', strtotime($trendMonthStart)) . ' 23:59:59';

    $trendJoin = "LEFT JOIN (
        SELECT
            peminjaman_id,
            SUM(CASE WHEN approval_status = 'Approved' THEN 1 ELSE 0 END) AS approved_count,
            SUM(CASE WHEN approval_status = 'Rejected' THEN 1 ELSE 0 END) AS rejected_count
        FROM peminjaman_units
        GROUP BY peminjaman_id
    ) pu_initial ON pu_initial.peminjaman_id = p.id";

    $trendApprovedCount = "COALESCE(pu_initial.approved_count, 0)";
    $trendRejectedCount = "COALESCE(pu_initial.rejected_count, 0)";

    $trendCase = "CASE
        WHEN $trendApprovedCount > 0 AND $trendRejectedCount > 0 THEN 'Partial Approved'
        WHEN $trendApprovedCount > 0 THEN 'Approved'
        WHEN $trendRejectedCount > 0 THEN 'Rejected'
        WHEN p.status = 'Partial Approved' THEN 'Partial Approved'
        WHEN p.tanggal_disetujui IS NOT NULL THEN 'Approved'
        WHEN p.status = 'Rejected' THEN 'Rejected'
        WHEN p.status IN ('Borrowed','Returned','Return in Process','Partially Returned','Partially Damaged','Fully Damaged','Completed')
             OR p.status LIKE 'Due%'
             OR p.status = 'Overdue' THEN 'Approved'
        ELSE NULL END";

    $trendDateCol = "CASE
        WHEN $trendApprovedCount > 0 THEN COALESCE(p.tanggal_disetujui, p.created_at)
        WHEN $trendRejectedCount > 0 THEN p.created_at
        WHEN p.status = 'Partial Approved' THEN COALESCE(p.tanggal_disetujui, p.created_at)
        WHEN p.tanggal_disetujui IS NOT NULL THEN COALESCE(p.tanggal_disetujui, p.created_at)
        WHEN p.status = 'Rejected' THEN p.created_at
        WHEN p.status IN ('Borrowed','Returned','Return in Process','Partially Returned','Partially Damaged','Fully Damaged','Completed')
             OR p.status LIKE 'Due%'
             OR p.status = 'Overdue' THEN COALESCE(p.tanggal_disetujui, p.created_at)
        ELSE NULL END";

    // Baseline: totals BEFORE this month
    $baseline = [];
    $sqlB = "SELECT $trendCase AS status_group, COUNT(*) AS cnt
             FROM peminjaman p
             {$trendJoin}
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

    // Weekly: data within selected month
    $weekRaw = [];
    $sqlW = "SELECT LEAST(CEIL(DAY(($trendDateCol)) / 7.0), 4) AS week_num,
                    $trendCase AS status_group, COUNT(*) AS cnt
             FROM peminjaman p
             {$trendJoin}
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

    $stats['loan_status_trend'] = [
        'month' => $trendMonth,
        'baseline' => $baseline,
        'raw' => $weekRaw
    ];

    // 11. New Products This Month
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
    if (!$stmtNewProducts) {
        throw new Exception("Query 11 Error: " . $conn->error);
    }
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
    $stats['new_products'] = $new_products;
    $stmtNewProducts->close();

    // Return successful response with database connection info
    echo json_encode([
        'status' => true,
        'data' => $stats,
        'database' => 'peminjaman',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    $conn->close();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => false,
        'message' => 'Database error: ' . $e->getMessage(),
        'database' => 'peminjaman'
    ]);
}
