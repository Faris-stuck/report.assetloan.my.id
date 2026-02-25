<?php
/**
 * API: User Dashboard Statistics
 * Purpose: Get user's peminjaman statistics
 * Endpoint: /api/user/dashboard-stats.php
 */

require_once "../koneksi.php";
require_once "../session-helper.php";
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    SessionValidator::requireRole(['user']);
    
    // Get user ID from session
    $user_id = SessionValidator::getUserId();
    if (!$user_id) {
        throw new Exception("User ID not found");
    }
    
    $stats = [];
    
    // 1. Peminjaman Menunggu Persetujuan
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total FROM peminjaman 
        WHERE user_id = ? AND status = 'Menunggu Persetujuan'
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stats['menunggu_persetujuan'] = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
    
    // 2. Peminjaman Disetujui
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total FROM peminjaman 
        WHERE user_id = ? AND (status = 'Disetujui' OR status = 'Menunggu Admin')
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stats['disetujui'] = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
    
    // 3. Peminjaman Ditolak
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total FROM peminjaman 
        WHERE user_id = ? AND status = 'Ditolak'
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stats['ditolak'] = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
    
    // 4. Sedang Dipinjam (active)
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total FROM peminjaman 
        WHERE user_id = ? AND (status = 'Sedang Dipinjam' OR status LIKE 'Due%' OR status = 'Overdue' OR status = 'Sebagian Dikembalikan' OR status = 'Proses Return')
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stats['sedang_dipinjam'] = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
    
    // 5. Sudah dikembalikan
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total FROM peminjaman 
        WHERE user_id = ? AND status = 'Dikembalikan'
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stats['dikembalikan'] = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
    
    // 6. Recent peminjaman
    $stmt = $conn->prepare("
        SELECT 
            id, kode_peminjaman, status, tanggal_pinjam, rencana_kembali
        FROM peminjaman 
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $recent = [];
    while ($row = $result->fetch_assoc()) {
        $recent[] = [
            'id' => $row['id'],
            'kode' => $row['kode_peminjaman'],
            'status' => computeDueStatus($row['status'], getNearestExpectedReturn($conn, $row['id']) ?? $row['rencana_kembali']),
            'tanggal_pinjam' => date('d/m/Y', strtotime($row['tanggal_pinjam'])),
            'rencana_kembali' => date('d/m/Y', strtotime($row['rencana_kembali']))
        ];
    }
    $stats['recent_peminjaman'] = $recent;
    
    echo json_encode([
        'status' => true,
        'data' => $stats,
        'user_id' => $user_id,
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
