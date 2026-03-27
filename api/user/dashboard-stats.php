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

    // 1. Waiting for Approval
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total FROM peminjaman 
        WHERE user_id = ? AND status = 'Waiting for Approval'
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stats['menunggu_persetujuan'] = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

    // 2. Partial Approved
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total FROM peminjaman 
        WHERE user_id = ? AND status = 'Partial Approved'
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stats['sebagian_disetujui'] = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

    // 3. Rejected
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total FROM peminjaman 
        WHERE user_id = ? AND status = 'Rejected'
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stats['ditolak'] = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

    // 4. Borrowed (active)
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total FROM peminjaman 
        WHERE user_id = ? AND (status = 'Borrowed' OR status LIKE 'Due%' OR status = 'Overdue' OR status = 'Partially Returned' OR status = 'Return in Process')
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stats['sedang_dipinjam'] = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

    // 5. Returned
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total FROM peminjaman 
        WHERE user_id = ? AND status = 'Returned'
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
        $nearest_expected = getNearestExpectedReturn($conn, $row['id']);
        $recent[] = [
            'id' => $row['id'],
            'kode' => $row['kode_peminjaman'],
            'status' => computeDueStatus($row['status'], $nearest_expected ?? $row['rencana_kembali']),
            'tanggal_pinjam' => date('d/m/Y', strtotime($row['tanggal_pinjam'])),
            'rencana_kembali' => date('d/m/Y', strtotime($row['rencana_kembali'])),
            'expected_return_nearest' => $nearest_expected ? date('d/m/Y', strtotime($nearest_expected)) : date('d/m/Y', strtotime($row['rencana_kembali']))
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
