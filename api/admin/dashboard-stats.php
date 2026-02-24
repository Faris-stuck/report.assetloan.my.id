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
    
    $stats = [];
    
    // 1. Menunggu Persetujuan (pending approval)
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total FROM peminjaman WHERE status = 'Menunggu Persetujuan'
    ");
    if (!$stmt) {
        throw new Exception("Query 1 Error: " . $conn->error);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stats['menunggu_persetujuan'] = (int)($row['total'] ?? 0);
    $stmt->close();
    
    // 2. Sedang Dipinjam (active loans)
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total FROM peminjaman WHERE (status = 'Sedang Dipinjam' OR status LIKE 'Due%' OR status = 'Overdue')
    ");
    if (!$stmt) {
        throw new Exception("Query 2 Error: " . $conn->error);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stats['sedang_dipinjam'] = (int)($row['total'] ?? 0);
    $stmt->close();
    
    // 3. Dikembalikan (all time aggregate, not just today)
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total FROM peminjaman 
        WHERE status = 'Dikembalikan'
    ");
    if (!$stmt) {
        throw new Exception("Query 3 Error: " . $conn->error);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stats['dikembalikan'] = (int)($row['total'] ?? 0);
    $stmt->close();
    
    // 4. Ditolak
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total FROM peminjaman WHERE status = 'Ditolak'
    ");
    if (!$stmt) {
        throw new Exception("Query 4 Error: " . $conn->error);
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
        $recent[] = [
            'id' => (int)$row['id'],
            'kode' => $row['kode_peminjaman'],
            'nama' => $row['nama_peminjam'],
            'status' => $row['status'],
            'tanggal_pinjam' => ($row['tanggal_pinjam'] ? date('d/m/Y', strtotime($row['tanggal_pinjam'])) : '-'),
            'rencana_kembali' => ($row['rencana_kembali'] ? date('d/m/Y', strtotime($row['rencana_kembali'])) : '-')
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
