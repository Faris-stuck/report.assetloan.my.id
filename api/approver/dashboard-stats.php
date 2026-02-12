<?php
/**
 * API: Approver/Manager Dashboard Statistics
 * Purpose: Get statistics for manager dashboard
 * Endpoint: /api/approver/dashboard-stats.php
 */

require_once "../koneksi.php";
require_once "../session-helper.php";
header('Content-Type: application/json');

try {
    // Validate role
    SessionValidator::requireRole(['manager']);
    
    $stats = [];
    
    // 1. Menunggu Persetujuan (pending approval)
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total 
        FROM peminjaman 
        WHERE status = 'Menunggu Persetujuan'
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['menunggu_persetujuan'] = $result->fetch_assoc()['total'] ?? 0;
    
    // 2. Disetujui/Menunggu Admin (approved, waiting for admin)
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total 
        FROM peminjaman 
        WHERE status = 'Disetujui' OR status = 'Menunggu Admin'
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['disetujui'] = $result->fetch_assoc()['total'] ?? 0;
    
    // 3. Ditolak (rejected)
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total 
        FROM peminjaman 
        WHERE status = 'Ditolak'
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['ditolak'] = $result->fetch_assoc()['total'] ?? 0;
    
    // 4. Sedang Dipinjam (active loans)
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total 
        FROM peminjaman 
        WHERE status = 'Sedang Dipinjam'
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['sedang_dipinjam'] = $result->fetch_assoc()['total'] ?? 0;
    
    // 5. Recent peminjaman (last 5)
    $stmt = $conn->prepare("
        SELECT 
            p.id, p.kode_peminjaman, p.nama_peminjam, p.nrp, 
            p.status, p.tanggal_pinjam, p.rencana_kembali
        FROM peminjaman p
        ORDER BY p.created_at DESC
        LIMIT 5
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $recent = [];
    while ($row = $result->fetch_assoc()) {
        $recent[] = [
            'id' => $row['id'],
            'kode' => $row['kode_peminjaman'],
            'nama' => $row['nama_peminjam'],
            'nrp' => $row['nrp'],
            'status' => $row['status'],
            'tanggal_pinjam' => date('d/m/Y', strtotime($row['tanggal_pinjam'])),
            'rencana_kembali' => date('d/m/Y', strtotime($row['rencana_kembali']))
        ];
    }
    $stats['recent_peminjaman'] = $recent;
    
    // 6. Items pending approval (top requesters)
    $stmt = $conn->prepare("
        SELECT 
            p.nama_peminjam, 
            COUNT(*) as jumlah,
            GROUP_CONCAT(p.kode_peminjaman) as kode_list
        FROM peminjaman p
        WHERE p.status = 'Menunggu Persetujuan'
        GROUP BY p.nama_peminjam
        ORDER BY jumlah DESC
        LIMIT 5
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $top_pending = [];
    while ($row = $result->fetch_assoc()) {
        $top_pending[] = [
            'nama' => $row['nama_peminjam'],
            'jumlah' => $row['jumlah'],
            'kodes' => $row['kode_list']
        ];
    }
    $stats['top_pending'] = $top_pending;
    
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
