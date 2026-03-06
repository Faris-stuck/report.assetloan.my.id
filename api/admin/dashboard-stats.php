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
    
    // Get kategori filter parameter
    $kategoriFilter = $_GET['kategori'] ?? '';
    
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
     * - If user A has 2 loans in "Sedang Dipinjam" status = count 2 (not 1)
     * - If user B has 3 loans in "Ditolak" status = count 3 (not 1)
     * 
     * Status mapping to display categories:
     * - "Menunggu Persetujuan": Only status = 'Menunggu Persetujuan'
     * - "Sedang Dipinjam": 'Sedang Dipinjam' + 'Sebagian Dikembalikan' + 'Proses Return' + 'Due*' + 'Overdue'
     * - "Dikembalikan": 'Dikembalikan' + 'Sebagian Rusak' + 'Semua Rusak' + 'Selesai'
     * - "Ditolak": Only status = 'Ditolak'
     */
    
    // 1. Menunggu Persetujuan (pending approval)
    $query1 = "SELECT COUNT(*) as total FROM peminjaman WHERE status = 'Menunggu Persetujuan'" . $dateWhereClause;
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
    
    // 2. Sedang Dipinjam (active loans - includes Due patterns and partial returns)
    $query2 = "SELECT COUNT(*) as total FROM peminjaman WHERE (status IN ('Sedang Dipinjam', 'Sebagian Dikembalikan', 'Proses Return') OR status LIKE 'Due%' OR status = 'Overdue')" . $dateWhereClause;
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
    
    // 3. Dikembalikan (all time aggregate - includes all returned items regardless of condition)
    $query3 = "SELECT COUNT(*) as total FROM peminjaman WHERE status IN ('Dikembalikan', 'Sebagian Rusak', 'Semua Rusak', 'Selesai')" . $dateWhereClause;
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
    
    // 4. Ditolak
    $query4 = "SELECT COUNT(*) as total FROM peminjaman WHERE status = 'Ditolak'" . $dateWhereClause;
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
    
    // 7. Top Barang Dipinjam (hitung total UNIT yang dipinjam dari detail_peminjaman)
    // Include: Sedang Dipinjam + Sebagian Dikembalikan
    // Build dynamic query based on kategori filter
    if (!empty($kategoriFilter) && $kategoriFilter !== 'all') {
        // Query with kategori filter
        $stmt = $conn->prepare("
            SELECT 
                b.id,
                b.nama_barang, 
                b.kategori,
                b.stok_tersedia, 
                COALESCE(SUM(dp.jumlah), 0) as jumlah_dipinjam
            FROM barang b
            LEFT JOIN detail_peminjaman dp ON b.id = dp.barang_id
            LEFT JOIN peminjaman p ON dp.peminjaman_id = p.id
            WHERE b.kategori = ? AND (p.status = 'Sedang Dipinjam' OR p.status LIKE 'Due%' OR p.status = 'Overdue' OR p.status = 'Sebagian Dikembalikan' OR p.status IS NULL)
            GROUP BY b.id, b.nama_barang, b.kategori, b.stok_tersedia
            HAVING COALESCE(SUM(dp.jumlah), 0) > 0
            ORDER BY jumlah_dipinjam DESC
            LIMIT 5
        ");
        if (!$stmt) {
            throw new Exception("Query 7 Error: " . $conn->error);
        }
        $stmt->bind_param('s', $kategoriFilter);
    } else {
        // Query without kategori filter (all categories)
        $stmt = $conn->prepare("
            SELECT 
                b.id,
                b.nama_barang, 
                b.kategori,
                b.stok_tersedia, 
                COALESCE(SUM(dp.jumlah), 0) as jumlah_dipinjam
            FROM barang b
            LEFT JOIN detail_peminjaman dp ON b.id = dp.barang_id
            LEFT JOIN peminjaman p ON dp.peminjaman_id = p.id
            WHERE (p.status = 'Sedang Dipinjam' OR p.status LIKE 'Due%' OR p.status = 'Overdue' OR p.status = 'Sebagian Dikembalikan' OR p.status IS NULL)
            GROUP BY b.id, b.nama_barang, b.kategori, b.stok_tersedia
            HAVING COALESCE(SUM(dp.jumlah), 0) > 0
            ORDER BY jumlah_dipinjam DESC
            LIMIT 5
        ");
        if (!$stmt) {
            throw new Exception("Query 7 Error: " . $conn->error);
        }
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $top_barang = [];
    while ($row = $result->fetch_assoc()) {
        $top_barang[] = [
            'id' => (int)$row['id'],
            'nama' => $row['nama_barang'],
            'kategori' => $row['kategori'],
            'jumlah_peminjaman' => (int)$row['jumlah_dipinjam'],
            'stok_tersedia' => (int)$row['stok_tersedia']
        ];
    }
    $stats['top_barang'] = $top_barang;
    $stmt->close();
    
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
    
    // 9. Data Barang: All items with dipinjam (Sedang Dipinjam + Sebagian Dikembalikan) vs tersedia count
    $stmt = $conn->prepare("
        SELECT 
            b.id,
            b.nama_barang, 
            b.stok_tersedia, 
            COALESCE(SUM(CASE WHEN (p.status = 'Sedang Dipinjam' OR p.status LIKE 'Due%' OR p.status = 'Overdue' OR p.status = 'Sebagian Dikembalikan') THEN dp.jumlah ELSE 0 END), 0) as jumlah_dipinjam
        FROM barang b
        LEFT JOIN detail_peminjaman dp ON b.id = dp.barang_id
        LEFT JOIN peminjaman p ON dp.peminjaman_id = p.id
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
    $perStatusQuery = "SELECT status_label AS status, COUNT(*) AS total FROM (
        SELECT {$_dueCase} AS status_label
        FROM peminjaman p
        LEFT JOIN (
            SELECT peminjaman_id, MIN(expected_return) AS nearest_return
            FROM peminjaman_units
            WHERE return_status NOT IN ('Dikembalikan','Rusak')
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
?>
