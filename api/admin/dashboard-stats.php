<?php
/**
 * API: Admin Dashboard Statistics
 * Purpose: Get statistics for admin dashboard
 * Endpoint: /api/admin/dashboard-stats.php
 */

require_once "../koneksi.php";
require_once "../session-helper.php";
header('Content-Type: application/json');

try {
    SessionValidator::requireRole(['admin']);
    
    $stats = [];
    
    // 1. Menunggu Persetujuan (pending approval)
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total FROM peminjaman WHERE status = 'Menunggu Persetujuan'
    ");
    $stmt->execute();
    $stats['menunggu_persetujuan'] = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
    
    // 2. Sedang Dipinjam (active loans)
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total FROM peminjaman WHERE status = 'Sedang Dipinjam'
    ");
    $stmt->execute();
    $stats['sedang_dipinjam'] = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
    
    // 3. Dikembalikan today
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total FROM peminjaman 
        WHERE status = 'Dikembalikan' AND DATE(tanggal_kembali) = CURDATE()
    ");
    $stmt->execute();
    $stats['dikembalikan_hari_ini'] = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
    
    // 4. Ditolak
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total FROM peminjaman WHERE status = 'Ditolak'
    ");
    $stmt->execute();
    $stats['ditolak'] = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
    
    // 5. Total barang tersedia
    $stmt = $conn->prepare("
        SELECT SUM(stok_tersedia) as total FROM barang
    ");
    $stmt->execute();
    $stats['barang_tersedia'] = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
    
    // 6. Recent activity (all statuses, most recent first)
    $stmt = $conn->prepare("
        SELECT 
            p.id, p.kode_peminjaman, p.nama_peminjam,
            p.status, p.tanggal_pinjam, p.rencana_kembali, p.created_at
        FROM peminjaman p
        ORDER BY p.created_at DESC
        LIMIT 10
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $recent = [];
    while ($row = $result->fetch_assoc()) {
        $recent[] = [
            'id' => $row['id'],
            'kode' => $row['kode_peminjaman'],
            'nama' => $row['nama_peminjam'],
            'status' => $row['status'],
            'tanggal_pinjam' => date('d/m/Y', strtotime($row['tanggal_pinjam']))
        ];
    }
    $stats['recent_actions'] = $recent;
    
    // 7. Barang paling sering dipinjam
    $stmt = $conn->prepare("
        SELECT 
            b.nama_barang, b.stok_tersedia, COUNT(*) as jumlah_peminjaman
        FROM detail_peminjaman dp
        JOIN barang b ON dp.barang_id = b.id
        GROUP BY dp.barang_id, b.nama_barang, b.stok_tersedia
        ORDER BY jumlah_peminjaman DESC
        LIMIT 5
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $top_barang = [];
    while ($row = $result->fetch_assoc()) {
        $top_barang[] = [
            'nama' => $row['nama_barang'],
            'jumlah_peminjaman' => $row['jumlah_peminjaman'],
            'stok_tersedia' => $row['stok_tersedia']
        ];
    }
    $stats['top_barang'] = $top_barang;
    
    echo json_encode([
        'status' => true,
        'data' => $stats,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => false,
        'message' => $e->getMessage()
    ]);
}
?>
